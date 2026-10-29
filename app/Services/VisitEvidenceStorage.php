<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisitEvidence;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VisitEvidenceStorage
{
    public function available(): bool
    {
        return Schema::hasTable('visit_evidences') && class_exists(\finfo::class);
    }

    public function upload(User $actor, string $batchKey, UploadedFile $file): VisitEvidence
    {
        $this->actor($actor);
        if (! $this->available()) {
            $this->fail('Los adjuntos no están disponibles en este momento.');
        }
        validator(['batch' => $batchKey], ['batch' => ['required', 'uuid']], ['batch.*' => 'La selección de archivos no es válida.'])->validate();
        $meta = (new EvidenceFileValidator)->inspect($file);
        $record = DB::transaction(function () use ($actor, $batchKey, $meta) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $pending = VisitEvidence::where('uploaded_by', $actor->id)->whereNull('visit_id')->get();
            $batch = $pending->where('batch_key', $batchKey);
            if ($batch->count() >= config('visit_evidence.max_files') || $batch->sum('size') + $meta['size'] > config('visit_evidence.max_visit_bytes')) {
                $this->fail('Puede seleccionar hasta 10 archivos y 40 MiB en total por visita.');
            }
            if ($pending->count() >= config('visit_evidence.max_staged_files') || $pending->sum('size') + $meta['size'] > config('visit_evidence.max_staged_bytes')) {
                $this->fail('Tiene demasiados archivos pendientes. Quite los que no necesite o consulte con soporte.');
            }
            if ($batch->contains('sha256', $meta['sha256'])) {
                $this->fail('Este archivo ya está seleccionado.');
            }
            $id = (string) Str::uuid();

            return VisitEvidence::create([...$meta, 'id' => $id, 'uploaded_by' => $actor->id, 'batch_key' => $batchKey, 'status' => 'uploading', 'disk' => config('visit_evidence.disk'), 'path' => 'files/'.$id.'.'.$meta['extension']]);
        });
        try {
            $disk = Storage::disk($record->disk);
            $written = $disk->putFileAs('files', $file, basename($record->path), ['visibility' => 'private']);
            if ($written !== $record->path || ! $this->intact($record)) {
                throw new \RuntimeException('Private evidence write failed.');
            }
            $record->update(['status' => 'staged', 'uploaded_at' => now()]);

            return $record;
        } catch (\Throwable $e) {
            // El registro se reserva ANTES del objeto: incluso un fallo de proceso
            // deja un archivo rastreable por la limpieza de pendientes.
            try {
                $this->deletePending($record->id);
            } catch (\Throwable) {
            }
            Log::warning('Evidence staging failed.', ['id' => $record->id, 'exception' => get_class($e)]);
            $this->fail('No se pudo guardar el archivo. Inténtelo de nuevo.');
        }
    }

    public function staged(User $actor, string $batchKey, array $ids, bool $lock = false): Collection
    {
        $this->actor($actor);
        validator(['evidence' => $ids], ['evidence' => ['array', 'max:'.config('visit_evidence.max_files')], 'evidence.*' => ['required', 'uuid', 'distinct']], ['evidence.*' => 'Revise los archivos seleccionados.'])->validate();
        $query = VisitEvidence::whereIn('id', $ids)->where('uploaded_by', $actor->id)->where('batch_key', $batchKey)->whereNull('visit_id')->where('status', 'staged')->orderBy('id');
        $files = ($lock ? $query->lockForUpdate() : $query)->get();
        if ($files->count() !== count($ids) || $files->sum('size') > config('visit_evidence.max_visit_bytes')) {
            $this->fail('Uno de los archivos no está disponible o no pertenece a su selección.');
        }

        return $files;
    }

    public function intact(VisitEvidence $evidence): bool
    {
        $disk = Storage::disk($evidence->disk);
        if (! $disk->exists($evidence->path) || $disk->size($evidence->path) !== $evidence->size) {
            return false;
        }
        $stream = $disk->readStream($evidence->path);
        if (! is_resource($stream)) {
            return false;
        }
        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);

            return hash_equals($evidence->sha256, hash_final($hash));
        } finally {
            fclose($stream);
        }
    }

    public function discard(User $actor, string $batchKey, string $id): void
    {
        $this->actor($actor);
        $file = VisitEvidence::whereKey($id)->where('uploaded_by', $actor->id)->where('batch_key', $batchKey)->whereNull('visit_id')->firstOrFail();
        $this->deletePending($file->id);
    }

    public function deletePending(string $id): void
    {
        DB::transaction(function () use ($id) {
            $file = VisitEvidence::whereKey($id)->whereNull('visit_id')->whereIn('status', ['uploading', 'staged'])->lockForUpdate()->first();
            if (! $file) {
                return;
            }
            $disk = Storage::disk($file->disk);
            if ($disk->exists($file->path) && (! $disk->delete($file->path) || $disk->exists($file->path))) {
                throw new \RuntimeException('Private cleanup failed.');
            }
            $file->delete();
        });
    }

    public function prune(bool $apply = false): array
    {
        if (! $this->available()) {
            return ['candidates' => 0, 'removed' => 0, 'failed' => 0];
        }
        $ids = VisitEvidence::whereNull('visit_id')->whereIn('status', ['uploading', 'staged'])->where('created_at', '<', now()->subHours(config('visit_evidence.staged_hours')))->pluck('id');
        $removed = 0;
        $failed = 0;
        if ($apply) {
            foreach ($ids as $id) {
                try {
                    $this->deletePending($id);
                    $removed++;
                } catch (\Throwable) {
                    $failed++;
                }
            }
        }

        return ['candidates' => $ids->count(), 'removed' => $removed, 'failed' => $failed];
    }

    private function actor(User $actor): void
    {
        abort_unless($actor->role === UserRole::PROMOTER && $actor->is_active, 403);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['evidence' => $message]);
    }
}
