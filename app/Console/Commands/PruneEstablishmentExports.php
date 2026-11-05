<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('raven:establishment-exports:prune {--apply : Eliminar las exportaciones temporales con más de 24 horas}')]
#[Description('Simula o elimina únicamente XLSX temporales vencidos; no modifica el catálogo ni manifiestos de auditoría.')]
class PruneEstablishmentExports extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $directory = storage_path('app/private/establishment-exchange');
        $candidates = 0;
        $removed = 0;
        if (is_dir($directory)) {
            $root = realpath(storage_path('app/private'));
            $resolved = realpath($directory);
            if (! $root || ! $resolved || is_link($directory) || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
                $this->error('El directorio temporal no es seguro. No se eliminó ningún archivo.');

                return self::FAILURE;
            }
            foreach (glob($directory.'/*.xlsx') ?: [] as $path) {
                if (! is_link($path) && is_file($path) && preg_match('/^[a-f0-9-]{36}\.xlsx$/D', basename($path)) && filemtime($path) < now()->subDay()->getTimestamp()) {
                    $candidates++;
                    if ($this->option('apply') && unlink($path)) {
                        $removed++;
                    }
                }
            }
        }
        $this->line('Exportaciones temporales vencidas: '.$candidates.'. Eliminadas: '.$removed.'.');

        return self::SUCCESS;
    }
}
