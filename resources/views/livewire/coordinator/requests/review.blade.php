@use('App\Models\PharmacyRequest')
@use('App\Services\SchedulePlanner')

@php
    $isAlta = $selected->type === PharmacyRequest::TYPE_ALTA;
    $pending = $selected->status === PharmacyRequest::PENDING;
    $proposal = $selected->data ?? [];
    $pharmacy = $selected->pharmacy;
    $submitted = $selected->created_at?->timezone(SchedulePlanner::TIMEZONE);
    $reviewed = $selected->reviewed_at?->timezone(SchedulePlanner::TIMEZONE);
    $category = $proposal['reason_category']['label'] ?? null;
    $observations = $category && trim($selected->reason) !== trim($category) ? $selected->reason : null;
    $missing = collect(['name' => 'nombre', 'department' => 'departamento', 'city' => 'municipio', 'address' => 'dirección'])
        ->filter(fn ($label, $field) => blank($proposal[$field] ?? null));
@endphp

<article class="requests-detail" aria-labelledby="request-title" wire:key="review-{{ $selected->id }}">
    <header class="raven-card requests-detail-head" data-tone="{{ $selected->type }}">
        <div class="requests-detail-nav">
            @if($standalone)
                <a href="{{ route('coordinator.requests.index', $queueParameters) }}" class="raven-button raven-button-ghost requests-back">Volver a solicitudes</a>
            @else
                <button type="button" class="raven-button raven-button-ghost requests-back" wire:click="closeReview">Cerrar revisión</button>
            @endif
            <span class="requests-kicker">Solicitud #{{ $selected->id }}</span>
        </div>
        <div class="requests-identification">
            <span class="requests-icon"><x-promoter.icon :name="$isAlta ? 'add' : 'remove'" /></span>
            <div>
                <p class="requests-kicker">{{ $isAlta ? 'Solicitud de alta' : 'Solicitud de baja' }}</p>
                @if($standalone)
                    <h1 id="request-title" class="raven-page-title">{{ $selected->establishmentTitle() }}</h1>
                @else
                    <h2 id="request-title" class="raven-section-title">{{ $selected->establishmentTitle() }}</h2>
                @endif
                @if(filled($selected->establishmentLocality()))<p>{{ $selected->establishmentLocality() }}</p>@endif
            </div>
            <x-coordinator.request-badge :value="$selected->status" />
        </div>
        <dl class="requests-facts">
            <div><dt>Promotora solicitante</dt><dd>{{ $selected->promoter?->name ?? 'Sin dato' }}</dd></div>
            <div><dt>Fecha de envío</dt><dd>@if($submitted)<time datetime="{{ $submitted->toAtomString() }}">{{ $submitted->format('d/m/Y H:i') }}</time>@else Sin dato @endif</dd></div>
        </dl>
    </header>

    <div class="requests-detail-grid">
        <div class="requests-detail-main">
            <section class="raven-card requests-section" aria-labelledby="request-establishment">
                <p class="requests-kicker">{{ $isAlta ? 'Datos propuestos' : 'Ficha actual' }}</p>
                <h2 id="request-establishment" class="raven-section-title">{{ $isAlta ? 'Información del establecimiento' : 'Establecimiento registrado' }}</h2>
                @if($isAlta || $pharmacy)
                    <dl class="requests-data">
                        @foreach(['client_type' => 'Tipo de establecimiento', 'category' => 'Categoría comercial', 'department' => 'Departamento', 'city' => 'Municipio', 'address' => 'Dirección detallada', 'phone' => 'Teléfono', 'email' => 'Correo electrónico'] as $field => $label)
                            @php($value = $isAlta ? ($proposal[$field] ?? null) : ($field === 'address' ? $pharmacy->display_address : $pharmacy->{$field}))
                            <div @class(['requests-data-wide' => $field === 'address'])>
                                <dt>{{ $label }}</dt>
                                <dd>{{ $field === 'category' && $value === 'N/A' ? 'No aplica' : (filled($value) ? $value : 'Sin dato') }}</dd>
                                @if($isAlta && isset($proposal['catalog_proposals'][$field]))<dd class="requests-proposed">Valor nuevo propuesto</dd>@endif
                            </div>
                        @endforeach
                        @unless($isAlta)
                            <div><dt>Estado de la ficha</dt><dd>{{ $pharmacy->is_active ? 'Activa' : 'Inactiva' }}</dd></div>
                            <div><dt>Responsables y coberturas</dt><dd>{{ $pharmacy->activeAssignments->pluck('promoter.name')->filter()->unique()->join(', ') ?: 'Sin asignación vigente' }}</dd></div>
                        @endunless
                    </dl>
                @else
                    <p class="requests-note">La solicitud no tiene una ficha de establecimiento disponible.</p>
                @endif
            </section>

            <section class="raven-card requests-section requests-reason" aria-labelledby="request-reason">
                <div class="requests-section-title">
                    <span class="requests-icon"><x-promoter.icon name="identity" /></span>
                    <h2 id="request-reason" class="raven-section-title">{{ $isAlta ? 'Motivo de alta' : 'Motivo de baja' }}</h2>
                </div>
                <p class="requests-text">{{ $selected->reasonLabel() ?: 'Sin motivo registrado' }}</p>
                @if(filled($observations))
                    <h3>Observaciones de la promotora</h3>
                    <p class="requests-text">{{ $observations }}</p>
                @endif
            </section>
        </div>

        <aside class="requests-detail-side" aria-label="Revisión e historial">
            @if($pending && ($duplicates->isNotEmpty() || $pendingPlanned > 0 || ($isAlta && $missing->isNotEmpty()) || (! $isAlta && ! $pharmacy)))
                <section class="requests-warnings" aria-labelledby="request-warnings">
                    <div class="requests-section-title">
                        <span class="requests-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.4 4.8a1.8 1.8 0 0 1 3.2 0l7.2 13.4A1.8 1.8 0 0 1 19.2 21H4.8a1.8 1.8 0 0 1-1.6-2.8l7.2-13.4Z"/><path d="M12 9v4m0 4h.01"/></svg>
                        </span>
                        <h2 id="request-warnings">Antes de resolver</h2>
                    </div>
                    @if($duplicates->isNotEmpty())
                        <div>
                            <h3>Posibles duplicados en el fichero</h3>
                            <p>Compare estas fichas con la propuesta.</p>
                            <ul>
                                @foreach($duplicates as $duplicate)
                                    <li wire:key="duplicate-{{ $duplicate->id }}">
                                        <a href="{{ route('coordinator.establishments.show', $duplicate->id) }}">{{ $duplicate->display_name }}</a>
                                        <span>{{ $duplicate->display_locality ?: 'Sin ubicación' }} · {{ $duplicate->display_address ?: 'Dirección por completar' }}</span>
                                        <span>{{ $duplicate->is_active ? ($duplicate->activeAssignments->pluck('promoter.name')->filter()->unique()->join(', ') ?: 'Sin promotora') : 'Inactiva' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if($isAlta && $missing->isNotEmpty())
                        <div><h3>Datos incompletos</h3><p>La propuesta no incluye {{ $missing->values()->join(', ', ' ni ') }}.</p></div>
                    @elseif(! $isAlta && ! $pharmacy)
                        <div><h3>Ficha no disponible</h3><p>Consulte el fichero antes de tomar una decisión.</p></div>
                    @endif
                    @if($pendingPlanned > 0)
                        <div><h3>Visitas pendientes</h3><p>Hay {{ $pendingPlanned }} {{ $pendingPlanned === 1 ? 'visita planificada' : 'visitas planificadas' }} desde hoy en cronogramas enviados. Si aprueba la baja, esas visitas ya no se podrán registrar.</p></div>
                    @endif
                </section>
            @endif

            @if($pending)
                <form class="raven-card requests-section requests-decision" wire:submit="approve" aria-labelledby="request-decision">
                    <p class="requests-kicker">Decisión de Coordinación</p>
                    <h2 id="request-decision" class="raven-section-title">Resolver solicitud</h2>
                    <p class="requests-note">{{ $isAlta ? 'Al aprobar, se creará la ficha y se asignará a la promotora que usted seleccione.' : 'Al aprobar, la ficha quedará inactiva y conservará su historial.' }}</p>
                    @error('review')<p class="raven-alert raven-alert-danger" role="alert">{{ $message }}</p>@enderror
                    @if($isAlta)
                        <label class="raven-field" for="review-assignee">
                            <span>Promotora responsable</span>
                            <select id="review-assignee" class="raven-select" wire:model="assigneeId" required @error('assigneeId') aria-invalid="true" aria-describedby="review-assignee-error" @enderror>
                                <option value="">Seleccione una promotora</option>
                                @foreach($promoters as $promoter)
                                    <option value="{{ $promoter->id }}">{{ $promoter->name }}{{ $promoter->id === $selected->promoter_id ? ' (solicitante)' : '' }}</option>
                                @endforeach
                            </select>
                            @error('assigneeId')<span id="review-assignee-error" class="raven-field-error">{{ $message }}</span>@enderror
                        </label>
                    @endif
                    <label class="raven-field" for="review-note">
                        <span>Nota de revisión</span>
                        <textarea id="review-note" class="raven-textarea" rows="3" maxlength="1000" wire:model="reviewNote" aria-describedby="review-note-help @error('reviewNote') review-note-error @enderror" @error('reviewNote') aria-invalid="true" @enderror></textarea>
                        <span id="review-note-help" class="raven-field-help">Opcional al aprobar. Al rechazar, explique el motivo con al menos 10 caracteres. La promotora podrá consultar la nota.</span>
                        @error('reviewNote')<span id="review-note-error" class="raven-field-error">{{ $message }}</span>@enderror
                    </label>
                    <div class="requests-decision-actions">
                        <button type="submit" class="raven-button raven-button-primary" wire:loading.attr="disabled" wire:target="approve, reject"
                                wire:confirm="{{ $isAlta ? '¿Aprobar el alta? El establecimiento se agregará al fichero.' : '¿Aprobar la baja? El establecimiento quedará inactivo.' }}">
                            <x-promoter.icon name="check" />{{ $isAlta ? 'Aprobar alta' : 'Aprobar baja' }}
                        </button>
                        <button type="button" class="raven-button raven-button-danger-outline requests-reject" wire:click="reject" wire:loading.attr="disabled" wire:target="approve, reject"
                                wire:confirm="¿Rechazar la solicitud? La promotora verá la nota de revisión."><x-promoter.icon name="remove" />Rechazar</button>
                    </div>
                </form>
            @else
                <section class="raven-card requests-section requests-outcome" aria-labelledby="request-outcome">
                    <div class="requests-section-title">
                        <span class="requests-icon"><x-promoter.icon name="history" /></span>
                        <h2 id="request-outcome" class="raven-section-title">Historial de revisión</h2>
                    </div>
                    <dl class="requests-data">
                        <div><dt>Resultado</dt><dd><x-coordinator.request-badge :value="$selected->status" /></dd></div>
                        <div><dt>Revisó</dt><dd>{{ $selected->reviewer?->name ?? 'Sin dato' }}</dd></div>
                        <div><dt>Fecha de revisión</dt><dd>@if($reviewed)<time datetime="{{ $reviewed->toAtomString() }}">{{ $reviewed->format('d/m/Y H:i') }}</time>@else Sin dato @endif</dd></div>
                        <div class="requests-data-wide"><dt>Nota de revisión</dt><dd class="requests-text">{{ $selected->review_note ?: 'Sin nota de revisión' }}</dd></div>
                    </dl>
                    @if($selected->status === PharmacyRequest::APPROVED && $pharmacy)
                        <a class="raven-button raven-button-secondary requests-action" href="{{ route('coordinator.establishments.show', $pharmacy->id) }}">Ver ficha del establecimiento <x-promoter.icon name="arrow" /></a>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</article>
