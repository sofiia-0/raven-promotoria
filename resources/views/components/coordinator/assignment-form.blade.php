@props(['promoters', 'operation', 'targetPromoterId' => '', 'source' => null])
<form class="assign-form" wire:submit="assign">
    <div class="assign-form-grid">
        @if($operation === 'replace' && $source)
            <p class="assign-form-wide assign-source">Sale <strong>{{ $source->promoter?->name ?? 'Promotora sin identificar' }}</strong>. Las demás responsables conservarán su asignación.</p>
        @endif
        @error('sourceAssignmentId')<p class="assign-form-wide raven-field-error" role="alert">{{ $message }}</p>@enderror
        <div class="assign-form-wide">
            <x-promoter.searchable-select model="targetPromoterId"
                :label="$operation === 'coverage' ? 'Promotora de cobertura' : ($operation === 'replace' ? 'Entra como responsable' : 'Promotora que se añadirá')"
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
            <div class="raven-field assign-form-wide">
                <label class="raven-label" for="coverage-reason">Motivo de la cobertura (opcional)</label>
                <textarea id="coverage-reason" class="raven-textarea" wire:model="coverageReason" maxlength="500" rows="2"></textarea>
                @error('coverageReason')<span class="raven-field-error">{{ $message }}</span>@enderror
            </div>
            <p class="raven-field-help assign-form-wide">Ambos días están incluidos, en horario de Managua. Al terminar el último día cesa el acceso por esta cobertura; las responsables habituales y las visitas registradas se conservan.</p>
        @endif
    </div>
    <div class="assign-form-actions">
        @if($operation === 'replace')
            <button class="raven-button raven-button-primary" type="submit" wire:loading.attr="disabled" wire:confirm="¿Sustituir únicamente la responsable seleccionada? Se conservarán las demás asignaciones y las visitas anteriores.">Confirmar sustitución</button>
        @else
            <button class="raven-button raven-button-primary" type="submit" wire:loading.attr="disabled">{{ $operation === 'coverage' ? 'Guardar cobertura' : 'Añadir responsable' }}</button>
        @endif
    </div>
</form>
