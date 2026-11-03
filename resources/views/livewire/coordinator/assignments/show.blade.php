<div class="raven-stack">
    <div>
        <a class="raven-button raven-button-secondary" href="{{ route('coordinator.assignments.index') }}">Volver a Asignaciones</a>
    </div>

    @if(session('success'))
        <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('selection') <div class="raven-alert raven-alert-danger" role="alert">{{ $message }}</div> @enderror

    <section class="raven-card" aria-labelledby="pharmacy-title">
        <div class="coordinator-section-head">
            <div>
                <div class="coordinator-badges">
                    <span class="raven-badge {{ $pharmacy->is_active ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $pharmacy->is_active ? 'Activa' : 'Inactiva' }}</span>
                </div>
                <h2 id="pharmacy-title" class="raven-section-title coordinator-review-title">{{ $pharmacy->display_name }}</h2>
                <p class="coordinator-list-meta">{{ $pharmacy->location_summary ?: 'Ubicación sin especificar' }}</p>
            </div>
        </div>
        <dl class="coordinator-detail-list">
            <div><dt>Promotora actual</dt><dd>{{ $pharmacy->currentAssignment?->promoter?->name ?? 'Sin promotora' }}</dd></div>
            <div><dt>Asignada desde</dt><dd>{{ $pharmacy->currentAssignment?->assigned_at?->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt>Tipo y categoría</dt><dd>{{ collect([$pharmacy->client_type, $pharmacy->category])->filter()->join(' · ') ?: 'Sin especificar' }}</dd></div>
            <div><dt>Referencia del fichero</dt><dd>{{ $pharmacy->source_reference ?: 'Sin referencia' }}</dd></div>
        </dl>

        @if($pharmacy->is_active)
            <form class="coordinator-review-form" wire:submit="assign">
                <div class="coordinator-batch-actions">
                    <div class="raven-field">
                        <label class="raven-label" for="single-target">{{ $pharmacy->currentAssignment ? 'Reasignar a' : 'Asignar a' }}</label>
                        <select id="single-target" class="raven-select" wire:model="targetPromoterId">
                            <option value="">Seleccione una promotora</option>
                            @foreach($promoters as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                        @error('targetPromoterId') <span class="raven-field-error">{{ $message }}</span> @enderror
                    </div>
                    <button type="submit" class="raven-button raven-button-primary" wire:loading.attr="disabled"
                            wire:confirm="¿Confirmar la asignación? Se terminará la asignación vigente.">Guardar</button>
                    @if($pharmacy->currentAssignment)
                        <button type="button" class="raven-button raven-button-secondary" wire:click="unassign" wire:loading.attr="disabled"
                                wire:confirm="¿Quitar la promotora de este establecimiento?">Quitar asignación</button>
                    @endif
                </div>
            </form>
        @else
            <p class="raven-card-description coordinator-review-form">Un establecimiento inactivo no se asigna. Se conserva su historial.</p>
        @endif
    </section>

    <section class="raven-card" aria-labelledby="assignment-history-title">
        <h2 id="assignment-history-title" class="raven-section-title">Historial de asignaciones</h2>
        @if($pharmacy->assignments->isEmpty())
            <p class="raven-card-description">Este establecimiento nunca tuvo promotora asignada.</p>
        @else
            <ul class="coordinator-list">
                @foreach($pharmacy->assignments as $assignment)
                    <li class="coordinator-list-row" wire:key="assignment-{{ $assignment->id }}">
                        <div>
                            <p class="coordinator-list-title">{{ $assignment->promoter?->name ?? 'Promotora' }}</p>
                            <p class="coordinator-list-meta">
                                {{ $assignment->assigned_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}
                                → {{ $assignment->ended_at?->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') ?? 'vigente' }}
                            </p>
                        </div>
                        <span class="raven-badge {{ $assignment->ended_at ? 'raven-badge-neutral' : 'raven-badge-success' }}">{{ $assignment->ended_at ? 'Terminada' : 'Vigente' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if($changes->isNotEmpty())
        <section class="raven-card" aria-labelledby="assignment-changes-title">
            <h2 id="assignment-changes-title" class="raven-section-title">Cambios hechos desde Coordinación</h2>
            <ul class="coordinator-list">
                @foreach($changes as $change)
                    <li class="coordinator-list-row" wire:key="change-{{ $change->id }}">
                        <p class="coordinator-list-title">{{ $change->action === 'assignment.ended' ? 'Se quitó la asignación' : 'Se cambió la promotora' }}</p>
                        <span class="coordinator-list-meta">{{ $change->actor?->name ?? 'Usuario' }} · {{ $change->created_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
