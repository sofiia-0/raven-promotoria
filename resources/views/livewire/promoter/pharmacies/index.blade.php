<div class="promoter-pharmacy-module">
    <section class="raven-card promoter-filter-card" aria-label="Buscar farmacias">
        <div class="promoter-filters">
            <div class="raven-field promoter-filter-search">
                <label class="raven-label" for="promoter-search">Buscar establecimiento</label>
                <input id="promoter-search" class="raven-input" type="search" placeholder="Nombre, departamento, municipio o referencia" wire:model.live.debounce.350ms="search">
            </div>
            <div class="raven-field">
                <label class="raven-label" for="promoter-department">Departamento</label>
                <select id="promoter-department" class="raven-select" wire:model.live="department"><option value="">Todos</option>@foreach($departments as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
            </div>
            <div class="raven-field">
                <label class="raven-label" for="promoter-category">Categoría</label>
                <select id="promoter-category" class="raven-select" wire:model.live="category"><option value="">Todas</option>@foreach($categories as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
            </div>
            <button type="button" class="raven-button raven-button-secondary" wire:click="clearFilters">Limpiar filtros</button>
        </div>
    </section>
    <div class="promoter-results-head" role="status" aria-live="polite">
        <p class="promoter-results-count">{{ $pharmacies->total() }} {{ $pharmacies->total() === 1 ? 'establecimiento' : 'establecimientos' }}</p>
        <span class="promoter-results-help" wire:loading.remove wire:target="search,department,category,clearFilters,gotoPage,nextPage,previousPage">Asignaciones vigentes</span>
        <span class="promoter-results-help" wire:loading wire:target="search,department,category,clearFilters,gotoPage,nextPage,previousPage">Actualizando resultados…</span>
    </div>
    @if($pharmacies->count() === 0)
        <section class="raven-card raven-empty"><h2 class="raven-card-title">No se encontraron establecimientos</h2><p class="raven-card-description">Revise los filtros o consulte sus asignaciones con Coordinadora.</p></section>
    @else
        <div class="promoter-pharmacy-grid">
            @foreach($pharmacies as $pharmacy)
                <article class="raven-card promoter-pharmacy-card" wire:key="pharmacy-{{ $pharmacy->id }}">
                    <div class="promoter-pharmacy-card-head">
                        <h2 class="raven-card-title">{{ $pharmacy->display_name }}</h2>
                        <span class="raven-badge raven-badge-success">Activa</span>
                    </div>
                    <x-promoter.location :city="$pharmacy->city" :department="$pharmacy->department" :neighborhood="$pharmacy->neighborhood" :address="$pharmacy->address" :reference="$pharmacy->branch_summary" />
                    <dl class="promoter-card-meta">
                        <div><dt>Tipo</dt><dd>{{ $pharmacy->client_type ?: 'Sin especificar' }}</dd></div>
                        <div><dt>Categoría</dt><dd>{{ $pharmacy->category ?: 'Sin especificar' }}</dd></div>
                        @if($pharmacy->frequency !== null)<div><dt>Frecuencia del fichero</dt><dd>{{ $pharmacy->frequency }}</dd></div>@endif
                    </dl>
                    <div class="promoter-card-actions">
                        <a class="raven-button raven-button-secondary" href="{{ route('promoter.pharmacies.show', $pharmacy->id) }}">Editar y ver historial <span aria-hidden="true">↗</span></a>
                        <a class="promoter-subtle-link" href="{{ route('promoter.requests.index', ['pharmacy' => $pharmacy->id]) }}">Solicitar baja</a>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="promoter-pagination">{{ $pharmacies->links('components.promoter.pagination') }}</div>
    @endif
</div>
