@props(['page', 'label'])

@if($page->hasPages())
    <nav class="follow-pagination" aria-label="Páginas de {{ $label }}">
        <button class="raven-button raven-button-secondary" type="button" wire:click="previousPage('{{ $page->getPageName() }}')" wire:loading.attr="disabled" @disabled($page->onFirstPage())>Anterior</button>
        <span>Página {{ $page->currentPage() }}</span>
        <button class="raven-button raven-button-secondary" type="button" wire:click="nextPage('{{ $page->getPageName() }}')" wire:loading.attr="disabled" @disabled(! $page->hasMorePages())>Siguiente</button>
    </nav>
@endif
