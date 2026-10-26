<div class="promoter-pharmacy-module">
    <section class="raven-card" aria-label="Buscar farmacias">
        <div class="promoter-filters">
            <div class="raven-field promoter-filter-search">
                <label class="raven-label" for="promoter-search">Buscar</label>
                <input id="promoter-search" class="raven-input" type="search"
                       placeholder="Nombre, ciudad, barrio o dirección"
                       wire:model.live.debounce.350ms="search">
            </div>
            <div class="raven-field">
                <label class="raven-label" for="promoter-department">Departamento</label>
                <select id="promoter-department" class="raven-select" wire:model.live="department">
                    <option value="">Todos</option>
                    @foreach($departments as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="raven-field">
                <label class="raven-label" for="promoter-category">Categoría</label>
                <select id="promoter-category" class="raven-select" wire:model.live="category">
                    <option value="">Todas</option>
                    @foreach($categories as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" class="raven-button raven-button-secondary"
                    wire:click="clearFilters">Limpiar</button>
        </div>
    </section>

    <div class="promoter-results-head">
        <p class="promoter-results-count">{{ $pharmacies->total() }} establecimiento(s) encontrado(s)</p>
        <span class="promoter-results-help">Solo tus asignaciones vigentes</span>
    </div>

    @if($pharmacies->count() === 0)
        <section class="raven-card raven-empty">
            <h2 class="raven-card-title">No encontramos establecimientos</h2>
            <p class="raven-card-description">Revisá los filtros o consultá con coordinación si todavía no tenés asignaciones.</p>
        </section>
    @else
        <div class="promoter-pharmacy-grid">
            @foreach($pharmacies as $pharmacy)
                <article class="raven-card promoter-pharmacy-card" wire:key="pharmacy-{{ $pharmacy->id }}">
                    <div class="promoter-pharmacy-card-head">
                        <h2 class="raven-card-title">{{ $pharmacy->name }}</h2>
                        <span class="raven-badge raven-badge-success">Activa</span>
                    </div>

                    <dl class="promoter-detail-list">
                        <div>
                            <dt>Tipo</dt>
                            <dd>{{ $pharmacy->client_type ?: 'Sin especificar' }}</dd>
                        </div>
                        <div>
                            <dt>Categoría</dt>
                            <dd>{{ $pharmacy->category ?: 'Sin especificar' }}</dd>
                        </div>
                        <div>
                            <dt>Ubicación</dt>
                            <dd>{{ collect([$pharmacy->city, $pharmacy->department])->filter()->join(', ') ?: 'Sin especificar' }}</dd>
                        </div>
                        @if($pharmacy->neighborhood)
                            <div>
                                <dt>Barrio / zona</dt>
                                <dd>{{ $pharmacy->neighborhood }}</dd>
                            </div>
                        @endif
                        @if($pharmacy->address)
                            <div>
                                <dt>Dirección</dt>
                                <dd>{{ $pharmacy->address }}</dd>
                            </div>
                        @endif
                        @if($pharmacy->frequency !== null)
                            <div>
                                <dt>Frecuencia del fichero</dt>
                                <dd>{{ $pharmacy->frequency }}</dd>
                            </div>
                        @endif
                    </dl>
                </article>
            @endforeach
        </div>
        <div class="promoter-pagination">{{ $pharmacies->links() }}</div>
    @endif
</div>
