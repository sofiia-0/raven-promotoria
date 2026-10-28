@if ($paginator->hasPages())
<nav class="promoter-pager" aria-label="Paginación">
    <p>Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</p>
    <div>
        <button type="button" class="raven-button raven-button-secondary" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" @disabled($paginator->onFirstPage())>Anterior</button>
        <button type="button" class="raven-button raven-button-secondary" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" @disabled(! $paginator->hasMorePages())>Siguiente</button>
    </div>
</nav>
@endif
