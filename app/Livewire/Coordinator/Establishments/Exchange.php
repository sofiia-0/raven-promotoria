<?php

namespace App\Livewire\Coordinator\Establishments;

use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\EstablishmentImport;
use App\Services\EstablishmentExchange;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Exchange extends Component
{
    use ResolvesCoordinator, WithFileUploads, WithPagination;

    #[Locked]
    public array $filters = [];

    #[Locked]
    public ?string $batchId = null;

    #[Locked]
    public ?string $manifestHash = null;

    public $workbook;

    public bool $onlyErrors = false;

    public function mount(array $filters = []): void
    {
        $this->coordinator();
        $this->filters = array_filter(array_intersect_key($filters, array_flip(['search', 'department', 'city', 'category', 'status', 'promoter'])), fn ($value) => is_string($value) && mb_strlen($value) <= 255);
    }

    public function preview(): void
    {
        $actor = $this->coordinator();
        $this->validate(['workbook' => ['required', 'file', 'mimes:xlsx', 'max:10240']], [], ['workbook' => 'archivo Excel']);
        $this->reset(['batchId', 'manifestHash', 'onlyErrors']);
        $stream = null;
        try {
            $stream = $this->workbook->readStream();
            $batch = app(EstablishmentExchange::class)->previewStream($actor, $stream);
            $this->batchId = $batch->id;
            $this->manifestHash = $batch->manifest_sha256;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            $this->workbook->delete();
            $this->reset('workbook');
        }
        $this->resetPage();
    }

    public function apply(): void
    {
        abort_unless($this->batchId && $this->manifestHash, 422);
        app(EstablishmentExchange::class)->apply($this->coordinator(), $this->batchId, $this->manifestHash);
        session()->flash('success', 'La importación se aplicó correctamente y quedó registrada en el historial.');
    }

    public function exportFiltered(): BinaryFileResponse
    {
        return $this->download(false);
    }

    public function exportComplete(): BinaryFileResponse
    {
        return $this->download(true);
    }

    private function download(bool $complete): BinaryFileResponse
    {
        $path = app(EstablishmentExchange::class)->export($this->coordinator(), $this->filters, $complete);

        return response()->download($path, 'raven-establecimientos-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    public function render(): View
    {
        $actor = $this->coordinator();
        $batch = $this->batchId ? EstablishmentImport::where('actor_id', $actor->id)->findOrFail($this->batchId) : null;
        $all = collect($batch?->manifest ?? []);
        $visible = $this->onlyErrors ? $all->filter(fn ($entry) => $entry['errors'] !== [])->values() : $all;
        $rows = new LengthAwarePaginator($visible->forPage($this->getPage(), 25)->values(), $visible->count(), 25, $this->getPage());

        return view('livewire.coordinator.establishments.exchange', ['batch' => $batch, 'rows' => $rows, 'counts' => $all->countBy('classification'),
            'recent' => EstablishmentImport::where('actor_id', $actor->id)->latest()->limit(10)->get(['id', 'status', 'created_at', 'applied_at'])]);
    }
}
