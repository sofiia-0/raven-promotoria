<?php

namespace App\Livewire\Coordinator\Concerns;

use App\Services\EstablishmentExchange;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait DownloadsEstablishmentMaster
{
    abstract protected function establishmentFilters(): array;

    public function exportFilteredMaster(): BinaryFileResponse
    {
        return $this->downloadMaster(false);
    }

    public function exportCompleteMaster(): BinaryFileResponse
    {
        return $this->downloadMaster(true);
    }

    private function downloadMaster(bool $complete): BinaryFileResponse
    {
        $path = app(EstablishmentExchange::class)->exportMaster($this->coordinator(), $this->establishmentFilters(), $complete);

        return response()->download($path, 'raven-maestro-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }
}
