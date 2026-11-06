<nav class="coordinator-pager" aria-label="Paginación">
    <p class="coordinator-pager-summary" role="status">
        @if($paginator->total())
            @php
                $first = $paginator->firstItem() ?? 0;
                $last = $paginator->lastItem() ?? 0;
            @endphp
            Mostrando {{ $first === $last ? $first : $first.'–'.$last }} de {{ $paginator->total() }}
            {{ $paginator->total() === 1 ? $record : $records }}
        @else
            0 {{ $records }}
        @endif
    </p>
    @if($paginator->hasPages())
        <div class="coordinator-pager-controls" role="group" aria-label="Cambiar de página">
            <div class="coordinator-pager-steps">
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled" @disabled($paginator->onFirstPage())>Anterior</button>
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled" @disabled(! $paginator->hasMorePages())>Siguiente</button>
            </div>
            <div class="coordinator-pager-numbers">
                @foreach($elements as $element)
                    @if(is_string($element))
                        <span class="coordinator-pager-gap" aria-hidden="true">…</span>
                    @else
                        @foreach($element as $page => $url)
                            @if($page === $paginator->currentPage())
                                <span class="coordinator-pager-current" aria-current="page" aria-label="Página {{ $page }}">{{ $page }}</span>
                            @else
                                <button type="button" wire:key="pager-{{ $paginator->getPageName() }}-{{ $page }}"
                                        wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                        wire:loading.attr="disabled" aria-label="Ir a la página {{ $page }}">{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</nav>
