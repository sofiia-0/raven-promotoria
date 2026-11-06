@use('App\Models\PharmacyRequest')
@php
    $statusLabels = [PharmacyRequest::PENDING => 'Pendiente', PharmacyRequest::APPROVED => 'Aprobada', PharmacyRequest::REJECTED => 'Rechazada'];
    $statusBadges = [PharmacyRequest::PENDING => 'raven-badge-info', PharmacyRequest::APPROVED => 'raven-badge-success', PharmacyRequest::REJECTED => 'raven-badge-danger'];
    $requestTitle = fn ($item) => $item->type === PharmacyRequest::TYPE_ALTA
        ? \App\Support\EstablishmentPresentation::title($item->data['name'] ?? 'Nuevo establecimiento', $item->data['department'] ?? null)
        : ($item->pharmacy?->display_name ?? 'Establecimiento');
@endphp
<div class="coordinator-requests raven-stack">
    @if(session('success'))
        <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>
    @endif

    @if($selected)
        <section class="raven-card coordinator-review" aria-labelledby="review-title" wire:key="review-{{ $selected->id }}">
            <div class="coordinator-section-head">
                <div>
                    <div class="coordinator-badges">
                        <span class="raven-badge {{ $selected->type === PharmacyRequest::TYPE_ALTA ? 'raven-badge-info' : 'raven-badge-rose' }}">{{ $selected->type === PharmacyRequest::TYPE_ALTA ? 'Solicitud de alta' : 'Solicitud de baja' }}</span>
                        <span class="raven-badge {{ $statusBadges[$selected->status] ?? 'raven-badge-neutral' }}">{{ $statusLabels[$selected->status] ?? $selected->status }}</span>
                    </div>
                    <h2 id="review-title" class="raven-section-title coordinator-review-title">{{ $requestTitle($selected) }}</h2>
                    <p class="coordinator-list-meta">Solicitada por {{ $selected->promoter?->name ?? 'Promotora' }} el {{ $selected->created_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</p>
                </div>
                <button type="button" class="raven-button raven-button-secondary" wire:click="closeReview">Cerrar revisión</button>
            </div>

            @error('review') <div class="raven-alert raven-alert-danger" role="alert">{{ $message }}</div> @enderror

            <dl class="coordinator-detail-list">
                @if($selected->type === PharmacyRequest::TYPE_ALTA)
                    @foreach(['client_type' => 'Tipo de establecimiento', 'category' => 'Categoría', 'department' => 'Departamento', 'city' => 'Municipio', 'address' => 'Dirección', 'phone' => 'Teléfono', 'email' => 'Correo'] as $field => $label)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>
                                {{ filled($selected->data[$field] ?? null) ? $selected->data[$field] : 'Sin dato' }}
                                @if(isset($selected->data['catalog_proposals'][$field]))
                                    <span class="raven-badge raven-badge-rose">Valor nuevo propuesto</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                @elseif($selected->pharmacy)
                    <div><dt>Ubicación</dt><dd><x-promoter.location :department="$selected->pharmacy->department" :city="$selected->pharmacy->city" :address="$selected->pharmacy->address" :neighborhood="$selected->pharmacy->neighborhood" /></dd></div>
                    <div><dt>Responsables y coberturas</dt><dd>{{ $selected->pharmacy->activeAssignments->pluck('promoter.name')->filter()->unique()->join(', ') ?: 'Sin asignación vigente' }}</dd></div>
                    <div><dt>Estado de la ficha</dt><dd>{{ $selected->pharmacy->is_active ? 'Activa' : 'Inactiva' }}</dd></div>
                @endif
                <div class="coordinator-detail-wide">
                    <dt>Motivo de la solicitud</dt>
                    <dd>
                        @if(isset($selected->data['reason_category']))
                            <strong>{{ $selected->data['reason_category']['label'] }}</strong>@if($selected->data['reason_category']['is_provisional'] ?? false) · Provisional @endif
                        @endif
                        @if(! isset($selected->data['reason_category']) || $selected->reason !== $selected->data['reason_category']['label'])
                            <span class="coordinator-block">{{ $selected->reason }}</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if($selected->type === PharmacyRequest::TYPE_ALTA && $duplicates->isNotEmpty())
                <div class="raven-alert raven-alert-warning coordinator-review-warning" role="status">
                    <p><strong>Posibles duplicados en el fichero.</strong> Revise que no se trate de un establecimiento ya registrado:</p>
                    <ul>
                        @foreach($duplicates as $duplicate)
                            <li wire:key="duplicate-{{ $duplicate->id }}">{{ $duplicate->display_name }} · {{ $duplicate->display_locality ?: 'Sin ubicación' }} · {{ $duplicate->display_address ?: 'Dirección por completar' }} · {{ $duplicate->is_active ? ($duplicate->activeAssignments->pluck('promoter.name')->filter()->unique()->join(', ') ?: 'Sin promotora') : 'Inactiva' }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($selected->type === PharmacyRequest::TYPE_BAJA && $pendingPlanned > 0)
                <div class="raven-alert raven-alert-warning coordinator-review-warning" role="status">
                    Hay {{ $pendingPlanned }} {{ $pendingPlanned === 1 ? 'visita planificada' : 'visitas planificadas' }} desde hoy en cronogramas enviados. Al aprobar la baja ya no podrán registrarse.
                </div>
            @endif

            @if($selected->status === PharmacyRequest::PENDING)
                <form class="coordinator-review-form" wire:submit="approve">
                    <div class="raven-form-grid">
                        @if($selected->type === PharmacyRequest::TYPE_ALTA)
                            <div class="raven-field">
                                <label class="raven-label" for="review-assignee">Promotora responsable</label>
                                <select id="review-assignee" class="raven-select" wire:model="assigneeId">
                                    @foreach($promoters as $promoter)
                                        <option value="{{ $promoter->id }}">{{ $promoter->name }}{{ $promoter->id === $selected->promoter_id ? ' (solicitante)' : '' }}</option>
                                    @endforeach
                                </select>
                                <span class="raven-field-help">Al aprobar, el establecimiento queda asignado a esta promotora.</span>
                                @error('assigneeId') <span class="raven-field-error">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <div class="raven-field coordinator-detail-wide">
                            <label class="raven-label" for="review-note">Nota de revisión</label>
                            <textarea id="review-note" class="raven-textarea" rows="3" maxlength="1000" wire:model="reviewNote"
                                      aria-describedby="review-note-help"></textarea>
                            <span id="review-note-help" class="raven-field-help">Opcional al aprobar; obligatoria al rechazar. La promotora la verá en su historial.</span>
                            @error('reviewNote') <span class="raven-field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="raven-form-actions">
                        <button type="submit" class="raven-button raven-button-primary" wire:loading.attr="disabled" wire:target="approve, reject"
                                wire:confirm="{{ $selected->type === PharmacyRequest::TYPE_ALTA ? '¿Aprobar el alta? El establecimiento se agregará al fichero.' : '¿Aprobar la baja? El establecimiento quedará inactivo.' }}">
                            {{ $selected->type === PharmacyRequest::TYPE_ALTA ? 'Aprobar alta' : 'Aprobar baja' }}
                        </button>
                        <button type="button" class="raven-button raven-button-danger" wire:click="reject" wire:loading.attr="disabled" wire:target="approve, reject"
                                wire:confirm="¿Rechazar la solicitud? La promotora verá la nota de revisión.">Rechazar</button>
                    </div>
                </form>
            @else
                <dl class="coordinator-detail-list coordinator-review-outcome">
                    <div><dt>Resuelta por</dt><dd>{{ $selected->reviewer?->name ?? 'Sin dato' }} · {{ $selected->reviewed_at?->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>Nota de revisión</dt><dd>{{ $selected->review_note ?: 'Sin nota' }}</dd></div>
                </dl>
            @endif
        </section>
    @endif

    <section class="raven-card" aria-label="Filtrar solicitudes">
        <div class="coordinator-filters coordinator-filters--requests">
            <div class="raven-field">
                <label class="raven-label" for="requests-search">Buscar</label>
                <input id="requests-search" class="raven-input" type="search" placeholder="Establecimiento o promotora"
                       wire:model.live.debounce.350ms="search">
            </div>
            <div class="raven-field">
                <label class="raven-label" for="requests-status">Estado</label>
                <select id="requests-status" class="raven-select" wire:model.live="status">
                    <option value="">Todos</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}{{ $value === PharmacyRequest::PENDING ? ' ('.$pendingCount.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="raven-field">
                <label class="raven-label" for="requests-type">Tipo</label>
                <select id="requests-type" class="raven-select" wire:model.live="type">
                    <option value="">Altas y bajas</option>
                    <option value="{{ PharmacyRequest::TYPE_ALTA }}">Altas</option>
                    <option value="{{ PharmacyRequest::TYPE_BAJA }}">Bajas</option>
                </select>
            </div>
        </div>
    </section>

    <div class="coordinator-results-head">
        <p class="coordinator-results-count">{{ $requests->total() }} {{ $requests->total() === 1 ? 'solicitud' : 'solicitudes' }}</p>
        <span class="coordinator-muted">{{ $status === PharmacyRequest::PENDING ? 'Ordenadas de la más antigua a la más reciente' : 'Más recientes primero' }}</span>
    </div>

    @if($requests->isEmpty())
        <section class="raven-card coordinator-empty">
            <p class="raven-card-title">No hay solicitudes con estos filtros</p>
            <p class="raven-card-description">Cuando una promotora solicite un alta o una baja, aparecerá aquí.</p>
        </section>
    @else
        <section class="raven-card coordinator-table-shell" tabindex="0">
            <table class="coordinator-table">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Establecimiento</th>
                        <th scope="col">Promotora</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="coordinator-sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $item)
                        <tr wire:key="request-row-{{ $item->id }}" @if($selectedId === $item->id) aria-current="true" class="is-selected" @endif>
                            <td>{{ $item->created_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</td>
                            <td><span class="raven-badge {{ $item->type === PharmacyRequest::TYPE_ALTA ? 'raven-badge-info' : 'raven-badge-rose' }}">{{ $item->type === PharmacyRequest::TYPE_ALTA ? 'Alta' : 'Baja' }}</span></td>
                            <td>
                                <span class="coordinator-list-title">{{ $requestTitle($item) }}</span>
                                <span class="coordinator-list-meta">{{ $item->type === PharmacyRequest::TYPE_ALTA ? \App\Support\EstablishmentPresentation::locality($item->data['department'] ?? null, $item->data['city'] ?? null) : ($item->pharmacy?->display_locality ?? '') }}</span><span class="coordinator-list-meta">{{ $item->type === PharmacyRequest::TYPE_ALTA ? \App\Support\EstablishmentPresentation::address($item->data['address'] ?? null, $item->data['neighborhood'] ?? null, $item->data['department'] ?? null, $item->data['city'] ?? null) : ($item->pharmacy?->display_address ?? '') }}</span>
                            </td>
                            <td>{{ $item->promoter?->name ?? 'Promotora' }}</td>
                            <td>
                                <span class="raven-badge {{ $statusBadges[$item->status] ?? 'raven-badge-neutral' }}">{{ $statusLabels[$item->status] ?? $item->status }}</span>
                                @if($item->reviewer)<span class="coordinator-list-meta">{{ $item->reviewer->name }}</span>@endif
                            </td>
                            <td class="coordinator-table-actions">
                                <button type="button" class="raven-button raven-button-secondary" wire:click="select({{ $item->id }})">
                                    {{ $item->status === PharmacyRequest::PENDING ? 'Revisar' : 'Ver detalle' }}<span class="coordinator-sr-only"> {{ $requestTitle($item) }}</span>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        <div class="coordinator-pagination">{{ $requests->links() }}</div>
    @endif
</div>
