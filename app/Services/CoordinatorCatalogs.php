<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\CommercialCatalogOption;
use App\Models\PromoterReasonCategory;
use App\Models\User;
use App\Models\VisitReason;
use App\Support\PromoterCatalogOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Catálogos operativos. No actualiza respuestas ni ejecuta sincronizaciones iniciales. */
class CoordinatorCatalogs
{
    public const LABELS = [
        'checklist' => 'Actividades del checklist',
        'visit_reason' => 'Motivos de visita no realizada',
        'visit_objective' => 'Objetivos generales de visita',
        'request_alta' => 'Motivos de alta',
        'request_baja' => 'Motivos de baja',
        'client_type' => 'Tipos de establecimiento',
        'category' => 'Categorías de establecimiento',
    ];

    public function query(string $domain): Builder
    {
        abort_unless(isset(self::LABELS[$domain]), 404);

        return match ($domain) {
            'checklist' => ChecklistItem::query(),
            'visit_reason' => VisitReason::query(),
            'client_type', 'category' => CommercialCatalogOption::query()->where('domain', $domain),
            default => PromoterReasonCategory::query()->where('domain', $domain),
        };
    }

    public function save(User $actor, string $domain, ?int $id, array $input, ?string $expected = null): Model
    {
        abort_unless($actor->role === UserRole::COORDINATOR && $actor->is_active, 403);
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:'.($domain === 'visit_reason' ? 120 : 150)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['required', 'boolean'],
            'is_other' => ['sometimes', 'boolean'],
            'is_provisional' => ['sometimes', 'boolean'],
        ])->validate();
        $data['name'] = PromoterCatalogOptions::clean($data['name']);
        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => 'Escriba el nombre de la opción.']);
        }

        return DB::transaction(function () use ($actor, $domain, $id, $data, $expected) {
            $query = $this->query($domain);
            $record = $id ? (clone $query)->lockForUpdate()->findOrFail($id) : null;
            if ($record && ($expected === null || ! hash_equals($this->fingerprint($record), $expected))) {
                throw ValidationException::withMessages(['catalog' => 'La opción cambió durante la edición. Vuelva a abrirla antes de guardar.']);
            }
            $key = PromoterCatalogOptions::commercialKey($data['name']);
            $duplicate = (clone $query)->when($id, fn ($q) => $q->whereKeyNot($id))->get()
                ->contains(fn ($item) => PromoterCatalogOptions::commercialKey($item->name) === $key);
            if ($duplicate) {
                throw ValidationException::withMessages(['name' => 'Ya existe esta opción, con independencia de mayúsculas y tildes. Edite o reactive la opción existente.']);
            }
            $before = $record?->getAttributes();
            // Estas tablas históricas guardan relaciones sin fotografía del texto.
            // Una nueva denominación necesita otro ID; el anterior queda inactivo.
            $meaningChanged = $record && ($record->name !== $data['name'] || ($domain === 'visit_reason' && $record->needs_explanation !== ($data['is_other'] ?? false)));
            if ($meaningChanged && in_array($domain, ['checklist', 'visit_reason'], true)) {
                $used = $domain === 'checklist'
                    ? DB::table('visit_checklist')->where('checklist_item_id', $id)->exists()
                    : DB::table('visits')->where('visit_reason_id', $id)->exists()
                        || DB::table('activity_reschedules')->where('visit_reason_id', $id)->exists();
                if ($used) {
                    $record->update(['is_active' => false]);
                    $old = $record;
                    $record = null;
                    app(AuditLogger::class)->record($actor, 'catalog.version_retired', $old, ['before' => $before, 'after' => $old->getAttributes()]);
                }
            }
            $values = array_intersect_key($data, array_flip(['name', 'sort_order', 'is_active']));
            if ($domain === 'visit_reason') {
                $values['requires_explanation'] = $data['is_other'] ?? false;
            }
            if (in_array($domain, ['client_type', 'category'], true)) {
                $values += ['domain' => $domain, 'normalized_name' => $key];
            } elseif (in_array($domain, PromoterReasonCategories::DOMAINS, true)) {
                $values += ['domain' => $domain, 'is_other' => $data['is_other'] ?? false, 'is_provisional' => $data['is_provisional'] ?? false];
                if (! $record) {
                    $values['code'] = (string) Str::uuid();
                } elseif ($record->is_other !== $values['is_other']) {
                    // Un código identifica también el requisito de explicación.
                    throw ValidationException::withMessages(['is_other' => 'Cree otra opción para cambiar el requisito de explicación.']);
                }
            }
            $record ??= $query->getModel()->newInstance();
            $record->fill($values)->save();
            app(AuditLogger::class)->record($actor, $before ? 'catalog.updated' : 'catalog.created', $record, [
                'domain' => $domain, 'before' => $before, 'after' => $record->getAttributes(),
            ]);

            return $record;
        });
    }

    public function fingerprint(Model $record): string
    {
        $values = [];
        foreach (['id', 'domain', 'code', 'name', 'description', 'sort_order', 'is_active', 'is_other', 'is_provisional', 'normalized_name', 'requires_explanation'] as $field) {
            $values[$field] = $record->getAttribute($field);
        }

        return hash('sha256', $record->getTable().json_encode($values, JSON_THROW_ON_ERROR));
    }
}
