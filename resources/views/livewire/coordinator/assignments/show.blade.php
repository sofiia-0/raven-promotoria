<div class="raven-stack">
    <div><a class="raven-button raven-button-secondary" href="{{ route('coordinator.assignments.index') }}">Volver a Asignaciones</a></div>
    @if(session('success'))<div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>@endif
    @error('selection')<div class="raven-alert raven-alert-danger" role="alert">{{ $message }}</div>@enderror
    <section class="raven-card" aria-labelledby="pharmacy-title">
        <div class="coordinator-section-head"><div>
            <span class="raven-badge {{ $pharmacy->is_active ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $pharmacy->is_active ? 'Activa' : 'Inactiva' }}</span>
            <h2 id="pharmacy-title" class="raven-section-title coordinator-review-title">{{ $pharmacy->display_name }}</h2>
            <x-promoter.location :department="$pharmacy->department" :city="$pharmacy->city" :address="$pharmacy->address" :neighborhood="$pharmacy->neighborhood" />
        </div></div>
        <dl class="coordinator-detail-list">
            <div><dt>Tipo y categoría</dt><dd>{{ collect([$pharmacy->client_type, $pharmacy->category])->filter()->join(' · ') ?: 'Sin especificar' }}</dd></div>
            <div><dt>Referencia del fichero</dt><dd>{{ $pharmacy->source_reference ?: 'Sin referencia' }}</dd></div>
        </dl>
        <h3 class="raven-section-title coordinator-review-title">Responsables y coberturas actuales</h3>
        <ul class="coordinator-list">
            @forelse($pharmacy->assignments->filter(fn($relation) => !$relation->ended_at && ($relation->assignment_kind === 'responsible' || $relation->coverage_ends_on?->toDateString() >= \App\Services\SchedulePlanner::today()->toDateString())) as $relation)
                <li class="coordinator-list-row coordinator-assignment-row" wire:key="active-assignment-{{ $relation->id }}">
                    <div><p class="coordinator-list-title">{{ $relation->promoter?->name }}</p>
                        <p class="coordinator-list-meta">{{ $relation->assignment_kind === 'coverage' ? 'Cobertura temporal' : 'Responsable habitual' }} · {{ $relation->period_status }}</p>
                        @if($relation->assignment_kind === 'coverage')<p class="coordinator-list-meta">{{ $relation->coverage_starts_on?->format('d/m/Y') }} – {{ $relation->coverage_ends_on?->format('d/m/Y') }} · días incluidos</p>@endif
                    </div>
                    <button type="button" class="raven-button raven-button-secondary" wire:click="endAssignment({{ $relation->id }})" wire:loading.attr="disabled" wire:confirm="¿Finalizar únicamente esta relación? Las demás asignaciones y el historial se conservarán.">{{ $relation->assignment_kind === 'coverage' ? 'Finalizar cobertura' : 'Retirar responsable' }}<span class="coordinator-sr-only"> {{ $relation->promoter?->name }}</span></button>
                </li>
            @empty<li class="coordinator-list-meta">No hay responsables ni coberturas vigentes o programadas.</li>@endforelse
        </ul>
        @if($pharmacy->is_active)<x-coordinator.assignment-form :pharmacy="$pharmacy" :promoters="$promoters" :operation="$operation" />
        @else<p class="raven-card-description coordinator-review-form">Un establecimiento inactivo no se asigna. Se conserva su historial.</p>@endif
    </section>
    <section class="raven-card" aria-labelledby="assignment-history-title">
        <h2 id="assignment-history-title" class="raven-section-title">Historial de asignaciones</h2>
        <ul class="coordinator-list">
            @forelse($pharmacy->assignments as $relation)
                <li class="coordinator-list-row coordinator-assignment-row" wire:key="assignment-{{ $relation->id }}">
                    <div><p class="coordinator-list-title">{{ $relation->promoter?->name }} · {{ $relation->assignment_kind === 'coverage' ? 'Cobertura temporal' : 'Responsable habitual' }}</p>
                        <p class="coordinator-list-meta">@if($relation->assignment_kind === 'coverage'){{ $relation->coverage_starts_on?->format('d/m/Y') }} – {{ $relation->coverage_ends_on?->format('d/m/Y') }} · @endif Alta: {{ $relation->assigned_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}@if($relation->ended_at) · Finalizada: {{ $relation->ended_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}@endif</p>
                        <p class="coordinator-list-meta">Registrada por {{ $relation->creator?->name ?? 'Sin dato histórico' }}@if($relation->ended_by) · Finalizada por {{ $relation->ender?->name }}@endif</p>
                    </div><span class="raven-badge {{ $relation->operational ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $relation->period_status }}</span>
                </li>
            @empty<li class="coordinator-list-meta">Este establecimiento nunca tuvo asignaciones.</li>@endforelse
        </ul>
    </section>
    <section class="raven-card" aria-labelledby="pharmacy-visits-title">
        <div class="coordinator-section-head"><h2 id="pharmacy-visits-title" class="raven-section-title">Visitas de este establecimiento</h2><a class="raven-button raven-button-secondary" href="{{ route('coordinator.visits.index', ['establecimiento'=>$pharmacy->id]) }}">Consultar todo el historial</a></div>
        <p class="raven-card-description">Últimos diez registros, independientemente de la responsable actual.</p>
        <ul class="coordinator-list">@forelse($recentVisits as $visit)
            <li class="coordinator-list-row"><div><p class="coordinator-list-title">{{ $visit->promoter?->name }}</p><p class="coordinator-list-meta">{{ $visit->visited_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }} · {{ $visit->status->label() }}</p></div><a class="raven-button raven-button-ghost" href="{{ route('coordinator.visits.show',['visit'=>$visit->id]) }}">Ver registro</a></li>
        @empty<li class="coordinator-list-meta">No hay visitas registradas.</li>@endforelse</ul>
    </section>
    @if($changes->isNotEmpty())
        <section class="raven-card" aria-labelledby="assignment-changes-title"><h2 id="assignment-changes-title" class="raven-section-title">Cambios hechos desde Coordinación</h2>
            <ul class="coordinator-list">@foreach($changes as $change)
                <li class="coordinator-list-row coordinator-assignment-row"><p class="coordinator-list-title">{{ ['assignment.added'=>'Se añadió una responsable','assignment.replaced'=>'Se sustituyó una responsable','assignment.changed'=>'Se cambió la promotora','assignment.ended'=>'Se retiró una responsable','coverage.created'=>'Se creó una cobertura','coverage.ended'=>'Se finalizó una cobertura'][$change->action] ?? 'Cambio de asignación' }}</p><span class="coordinator-list-meta">{{ $change->actor?->name ?? 'Sin dato histórico' }} · {{ $change->created_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</span></li>
            @endforeach</ul>
        </section>
    @endif
</div>
