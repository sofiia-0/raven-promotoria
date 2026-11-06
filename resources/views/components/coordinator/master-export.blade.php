@props(['hasFilters' => true])

<details class="coordinator-master-export" wire:ignore.self>
    <summary class="raven-button raven-button-secondary"><x-promoter.icon name="file" /> Excel maestro</summary>
    <div class="coordinator-master-body">
        <p>Libro de consulta con establecimientos y responsables. Para importar cambios, utilice la plantilla de Excel de Establecimientos.</p>
        <div class="coordinator-master-actions">
            <div>
                <button type="button" class="raven-button raven-button-secondary" wire:click="exportFilteredMaster"
                        wire:loading.attr="disabled" wire:target="exportFilteredMaster, exportCompleteMaster" @disabled(! $hasFilters)>Descargar con filtros</button>
                <small>{{ $hasFilters ? 'Todos los resultados del listado filtrado y sus relaciones vigentes.' : 'Aplique al menos un filtro para habilitar esta descarga.' }}</small>
            </div>
            <div>
                <button type="button" class="raven-button raven-button-secondary" wire:click="exportCompleteMaster"
                        wire:loading.attr="disabled" wire:target="exportFilteredMaster, exportCompleteMaster"
                        wire:confirm="Se exportarán todos los establecimientos, incluidos los inactivos, y todas sus relaciones, incluido el historial. ¿Desea continuar?">Descargar completo</button>
                <small>Incluye establecimientos inactivos y el historial de responsables y coberturas.</small>
            </div>
        </div>
    </div>
</details>
