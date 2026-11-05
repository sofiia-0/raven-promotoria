@props(['pharmacy', 'promoters', 'operation', 'compact' => false, 'targetPromoterId' => ''])
<form class="coordinator-review-form" wire:submit="assign">
    <div class="raven-form-grid">
        @unless($compact)
        <div class="raven-field coordinator-detail-wide">
            <label class="raven-label" for="assignment-operation">Acción</label>
            <select id="assignment-operation" class="raven-select" wire:model.live="operation">
                <option value="add">Añadir responsable habitual</option>
                <option value="replace">Sustituir una responsable</option>
                <option value="coverage">Crear cobertura temporal</option>
            </select>
            @error('operation')<span class="raven-field-error">{{ $message }}</span>@enderror
        </div>
        @endunless
        @if($operation === 'replace')
            <div class="raven-field coordinator-detail-wide">
                <label class="raven-label" for="assignment-source">Responsable que se sustituirá</label>
                <select id="assignment-source" class="raven-select" wire:model="sourceAssignmentId">
                    <option value="">Seleccione la asignación concreta</option>
                    @foreach($pharmacy->assignments->where('assignment_kind', 'responsible')->whereNull('ended_at') as $relation)
                        <option value="{{ $relation->id }}">{{ $relation->promoter?->name }} · desde {{ $relation->assigned_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y') }} · #{{ $relation->id }}</option>
                    @endforeach
                </select>
                <p class="raven-field-help">Solo se finalizará esta relación. Las demás responsables conservarán su asignación.</p>
                @error('sourceAssignmentId')<span class="raven-field-error">{{ $message }}</span>@enderror
            </div>
        @endif
        <div class="coordinator-detail-wide">
            <x-promoter.searchable-select model="targetPromoterId"
                :label="$operation === 'coverage' ? 'Promotora de cobertura' : ($operation === 'replace' ? 'Nueva responsable' : 'Promotora que se añadirá')"
                :value="$targetPromoterId" :options="$promoters->map(fn ($person) => (string) $person->id)"
                :labels="$promoters->pluck('name', 'id')->all()" required />
        </div>
        @if($operation === 'coverage')
            <div class="raven-field">
                <label class="raven-label" for="coverage-start">Primer día de cobertura</label>
                <input id="coverage-start" class="raven-input" type="date" wire:model="coverageStartsOn">
                @error('coverageStartsOn')<span class="raven-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="raven-field">
                <label class="raven-label" for="coverage-end">Último día de cobertura</label>
                <input id="coverage-end" class="raven-input" type="date" wire:model="coverageEndsOn">
                @error('coverageEndsOn')<span class="raven-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="raven-field coordinator-detail-wide">
                <label class="raven-label" for="coverage-reason">Motivo de la cobertura (opcional)</label>
                <textarea id="coverage-reason" class="raven-textarea" wire:model="coverageReason" maxlength="500" rows="2"></textarea>
                @error('coverageReason')<span class="raven-field-error">{{ $message }}</span>@enderror
            </div>
            <p class="raven-field-help coordinator-detail-wide">Ambos días están incluidos, en horario de Managua. Al terminar el último día cesa el acceso por esta cobertura; las responsables habituales y las visitas registradas se conservan.</p>
        @endif
    </div>
    <div class="raven-form-actions">
        @if($operation === 'replace')
            <button class="raven-button raven-button-primary" type="submit" wire:loading.attr="disabled" wire:confirm="¿Sustituir únicamente la responsable seleccionada? Se conservarán las demás asignaciones y las visitas anteriores.">Confirmar sustitución</button>
        @else
            <button class="raven-button raven-button-primary" type="submit" wire:loading.attr="disabled">{{ $operation === 'coverage' ? 'Guardar cobertura' : 'Añadir responsable' }}</button>
        @endif
    </div>
</form>
