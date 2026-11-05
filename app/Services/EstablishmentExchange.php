<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\EstablishmentImport;
use App\Models\Pharmacy;
use App\Models\PharmacyChange;
use App\Models\User;
use App\Support\CanonicalData;
use App\Support\CoordinatorEstablishmentQuery;
use App\Support\NicaraguaGeography;
use App\Support\PromoterCatalogOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Manifiestos privados, identificadores estables y aplicación atómica con comparación previa. */
class EstablishmentExchange
{
    public function __construct(private EstablishmentWorkbook $workbook, private CoordinatorEstablishments $manager) {}

    private function authorize(User $actor): void
    {
        abort_unless($actor->role === UserRole::COORDINATOR && $actor->is_active, 403);
    }

    public function export(User $actor, array $filters, bool $complete = false): string
    {
        $this->authorize($actor);
        $path = $this->temporaryPath();
        $query = CoordinatorEstablishmentQuery::for($actor, $complete ? [] : $filters)->orderBy('id');
        $count = 0;
        $rows = function () use ($query, &$count) {
            foreach ($query->lazyById(250) as $record) {
                $count++;
                yield $record->only(EstablishmentWorkbook::COLUMNS);
            }
        };
        try {
            $this->workbook->write($path, $rows());
            app(AuditLogger::class)->record($actor, 'establishment.exported', $actor, ['scope' => $complete ? 'complete' : 'filtered', 'filters' => $complete ? [] : array_intersect_key($filters, array_flip(['search', 'department', 'city', 'category', 'status', 'promoter'])), 'rows' => $count]);
        } catch (\Throwable $error) {
            @unlink($path);
            throw $error;
        }

        return $path;
    }

    public function preview(User $actor, string $path): EstablishmentImport
    {
        $this->authorize($actor);
        $rows = $this->workbook->read($path);
        $seenIds = [];
        $seenReferences = [];
        $seenLocations = [];
        $manifest = [];
        foreach ($rows as $line) {
            $input = $line['values'];
            $entry = ['row' => $line['row'], 'id' => null, 'reference' => null, 'classification' => 'error', 'before' => [], 'after' => [], 'errors' => [], 'input' => $input, 'expected' => null];
            try {
                $id = trim((string) ($input['id'] ?? ''));
                $reference = strtoupper(trim((string) ($input['source_reference'] ?? '')));
                Validator::make(['id' => $id, 'source_reference' => $reference], [
                    'id' => ['nullable', 'integer', 'min:1'],
                    'source_reference' => ['nullable', 'string', 'max:30'],
                ])->validate();
                $entry['id'] = $id !== '' ? (int) $id : null;
                $entry['reference'] = $reference;
                if ($id !== '' && isset($seenIds[(int) $id]) || $reference !== '' && isset($seenReferences[$reference])) {
                    $this->fail('El archivo repite un identificador o una referencia original.');
                }
                if ($id !== '') {
                    $seenIds[(int) $id] = true;
                }
                if ($reference !== '') {
                    $seenReferences[$reference] = true;
                }
                $existing = $id !== '' ? Pharmacy::find((int) $id) : ($reference !== '' ? Pharmacy::whereRaw('UPPER(source_reference) = ?', [$reference])->first() : null);
                if ($id !== '' && ! $existing) {
                    $this->fail('El ID no existe. Deje el ID vacío únicamente para un establecimiento nuevo con referencia propia.');
                }
                if ($existing && $reference !== strtoupper((string) $existing->source_reference)) {
                    $this->fail('La referencia original no corresponde al ID. No está permitido modificarla.');
                }
                if (! $existing && $reference === '') {
                    $this->fail('Los establecimientos nuevos necesitan una referencia única estable de hasta 30 caracteres.');
                }
                if (! $existing) {
                    Validator::make(['reference' => $reference], ['reference' => ['regex:/^[A-Z0-9][A-Z0-9._-]*$/D']])->validate();
                }
                if ((string) ($input['neighborhood'] ?? '') !== (string) ($existing?->neighborhood ?? '')) {
                    $this->fail('La columna neighborhood conserva información histórica y no puede modificarse por importación. Utilice Dirección detallada para datos nuevos.');
                }
                $target = $existing ?? new Pharmacy(['is_active' => true]);
                if (! $existing) {
                    Validator::make($input, ['name' => ['required', 'string', 'max:255'], 'address' => ['required', 'string', 'max:1000']] + NicaraguaGeography::rules((string) $input['department']))->validate();
                    $locationKey = CanonicalData::hash(array_map(fn ($value) => PromoterCatalogOptions::commercialKey((string) $value), array_intersect_key($input, array_flip(['name', 'department', 'city', 'address']))));
                    if (isset($seenLocations[$locationKey])) {
                        $this->fail('El archivo propone más de un establecimiento nuevo para la misma ubicación. Verifique las filas antes de continuar.');
                    }
                    $seenLocations[$locationKey] = true;
                    if ($this->samePhysicalLocation($input)) {
                        $this->fail('La ubicación coincide con un establecimiento existente. Verifique sus IDs antes de crear otro; no se consolidaron registros.');
                    }
                }
                [$before, $after] = $this->manager->changes($actor, $target, $input);
                $entry = array_replace($entry, ['id' => $existing?->id, 'reference' => $existing?->source_reference ?? $reference,
                    'classification' => $existing ? ($after === [] ? 'unchanged' : 'modified') : 'new',
                    'before' => $before, 'after' => $after, 'expected' => $existing ? $this->manager->fingerprint($existing) : null]);
            } catch (ValidationException $error) {
                $entry['errors'] = array_merge(...array_values($error->errors()));
            }
            $manifest[] = $entry;
        }

        return DB::transaction(function () use ($actor, $path, $manifest) {
            $batch = EstablishmentImport::create(['id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'status' => 'preview',
                'file_sha256' => hash_file('sha256', $path), 'manifest_sha256' => CanonicalData::hash($manifest), 'manifest' => $manifest]);
            app(AuditLogger::class)->record($actor, 'establishment.import_previewed', $actor, ['batch' => $batch->id, 'rows' => count($manifest), 'file_sha256' => $batch->file_sha256]);

            return $batch;
        });
    }

    public function apply(User $actor, string $batchId, string $confirmedHash): EstablishmentImport
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $batchId, $confirmedHash) {
            $batch = EstablishmentImport::where('actor_id', $actor->id)->lockForUpdate()->findOrFail($batchId);
            if (! hash_equals($batch->manifest_sha256, $confirmedHash) || ! hash_equals($confirmedHash, CanonicalData::hash($batch->manifest))) {
                $this->fail('La vista previa no coincide con la confirmación. Genere una nueva vista previa.');
            }
            if ($batch->status === 'applied') {
                return $batch;
            }
            if ($batch->status !== 'preview' || $batch->created_at->lt(now()->subHours(48))) {
                $this->fail('La vista previa ha vencido. Cargue nuevamente el archivo.');
            }
            if (collect($batch->manifest)->contains(fn ($entry) => $entry['errors'] !== [])) {
                $this->fail('Corrija todas las filas con errores antes de aplicar. No se modificó ningún establecimiento.');
            }
            // Serializa importaciones entre sí y luego bloquea los IDs en orden estable.
            Pharmacy::orderBy('id')->lockForUpdate()->first();
            $locked = Pharmacy::whereIn('id', array_filter(array_column($batch->manifest, 'id')))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($batch->manifest as $entry) {
                $pharmacy = $entry['id'] ? $locked->get($entry['id']) : null;
                if ($entry['id'] && (! $pharmacy || ! hash_equals($entry['expected'], $this->manager->fingerprint($pharmacy)))) {
                    $this->fail('La fila '.$entry['row'].' cambió después de la vista previa. No se aplicó ninguna fila.');
                }
                if (! $pharmacy && (Pharmacy::whereRaw('UPPER(source_reference) = ?', [strtoupper($entry['reference'])])->exists() || $this->samePhysicalLocation($entry['input']))) {
                    $this->fail('La fila '.$entry['row'].' coincide ahora con un establecimiento existente. Genere otra vista previa.');
                }
                [, $after] = $this->manager->changes($actor, $pharmacy ?? new Pharmacy(['is_active' => true]), $entry['input']);
                if (! hash_equals(CanonicalData::hash($after), CanonicalData::hash($entry['after']))) {
                    $this->fail('Las opciones de catálogo o los datos de la fila '.$entry['row'].' cambiaron. Genere otra vista previa.');
                }
                if ($pharmacy) {
                    $this->manager->update($actor, $pharmacy->id, $entry['input'], $entry['expected']);
                } else {
                    $pharmacy = Pharmacy::create($after + ['source_reference' => $entry['reference'], 'is_active' => true]);
                    PharmacyChange::create(['pharmacy_id' => $pharmacy->id, 'actor_id' => $actor->id, 'before' => [], 'after' => $pharmacy->only([...CoordinatorEstablishments::FIELDS, 'source_reference'])]);
                    app(AuditLogger::class)->record($actor, 'establishment.created_from_import', $pharmacy, ['batch' => $batch->id, 'row' => $entry['row']]);
                }
                app(AuditLogger::class)->record($actor, 'establishment.import_row_applied', $pharmacy, ['batch' => $batch->id, 'row' => $entry['row'], 'classification' => $entry['classification'], 'before' => $entry['before'], 'after' => $entry['after']]);
            }
            $batch->update(['status' => 'applied', 'applied_at' => now()]);
            app(AuditLogger::class)->record($actor, 'establishment.import_applied', $actor, ['batch' => $batch->id, 'rows' => count($batch->manifest), 'file_sha256' => $batch->file_sha256]);

            return $batch;
        }, 3);
    }

    private function samePhysicalLocation(array $input): bool
    {
        $key = fn ($value) => PromoterCatalogOptions::commercialKey((string) $value);

        return Pharmacy::query()->get(['name', 'department', 'city', 'address'])
            ->contains(fn ($existing) => $key($existing->department) === $key($input['department']) && $key($existing->city) === $key($input['city'])
                && $key($existing->display_name) === $key($input['name']) && $key($existing->address) === $key($input['address']));
    }

    private function temporaryPath(): string
    {
        $directory = storage_path('app/private/establishment-exchange');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        return $directory.'/'.Str::uuid().'.xlsx';
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['workbook' => $message]);
    }
}
