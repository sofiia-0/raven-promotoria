@props(['model' => 'pharmacyId', 'searchModel' => 'pharmacySearch', 'options', 'selected' => null, 'hasMore' => false, 'onSelect' => null, 'required' => true, 'multiple' => false, 'selectedIds' => []])
@php
    $pickerId = 'pharmacy-picker-'.$model;
    $identifiers = \App\Support\EstablishmentPresentation::selectionIdentifiers($options);
    $selectedLabel = $selected ? $selected->display_name.(isset($identifiers[$selected->id]) ? ' — '.$identifiers[$selected->id] : '') : '';
@endphp
<div class="raven-field promoter-pharmacy-picker {{ $multiple ? 'is-multiple' : '' }}" wire:key="{{ $pickerId }}" data-selected-label="{{ $selectedLabel }}"
    x-data="{
        selected: $wire.entangle(@js($model)).live,
        text: '', open: false, active: -1,
        editing: false, searching: false, timer: null, version: 0,
        items() { return this.$refs.list ? [...this.$refs.list.querySelectorAll('[role=option]')] : []; },
        input(value) {
            this.editing = true; this.open = true; this.active = -1;
            if (this.selected !== '') this.selected = '';
            clearTimeout(this.timer); this.searching = true;
            const version = ++this.version;
            this.timer = setTimeout(async () => {
                try { await this.$wire.set(@js($searchModel), value); }
                finally { if (version === this.version) this.searching = false; }
            }, 250);
        },
        async choose(button) {
            if (!button || this.searching) return;
            if (@js($multiple)) {
                clearTimeout(this.timer); ++this.version;
                this.searching = true; this.open = true;
                try { await this.$wire[@js($onSelect)](Number(button.dataset.id)); }
                finally { this.searching = false; this.open = true; this.$refs.input.focus(); }
                return;
            }
            if (@js($onSelect)) {
                clearTimeout(this.timer); ++this.version;
                this.searching = true; this.open = false;
                try { await this.$wire[@js($onSelect)](Number(button.dataset.id)); this.editing = false; this.text = ''; this.selected = ''; }
                finally { this.searching = false; this.$refs.input.focus(); }
                return;
            }
            clearTimeout(this.timer); ++this.version;
            this.editing = false; this.text = button.dataset.label;
            this.selected = button.dataset.id; this.open = false; this.active = -1;
            this.$wire.set(@js($searchModel), '');
            this.$refs.input.focus(); this.open = false;
        },
        clear() {
            clearTimeout(this.timer); ++this.version;
            this.editing = false; this.searching = false; this.selected = ''; this.text = '';
            this.$wire.set(@js($searchModel), ''); this.active = -1; this.open = true; this.$refs.input.focus();
        },
        move(direction) {
            this.open = true; if (this.searching) return;
            const items = this.items(); if (!items.length) { this.active = -1; return; }
            this.active = this.active < 0 ? (direction > 0 ? 0 : items.length - 1) : (this.active + direction + items.length) % items.length;
            this.$nextTick(() => items[this.active]?.scrollIntoView({block: 'nearest'}));
        },
        init() {
            this.text = this.$el.dataset.selectedLabel || '';
            this.$watch('selected', value => { if (!value && !this.editing) { this.text = ''; this.open = false; } });
        },
        destroy() { clearTimeout(this.timer); }
    }"
    @click.outside="open = false" @focusout="if (!$el.contains($event.relatedTarget)) open = false">
    <label class="raven-label" for="{{ $pickerId }}">Farmacia <span class="promoter-required" aria-hidden="true">*</span></label>
    <div class="promoter-picker-control">
        <x-promoter.icon name="search" />
        <input id="{{ $pickerId }}" x-ref="input" class="raven-input" type="text" x-model="text"
            value="{{ $selectedLabel }}" autocomplete="off" role="combobox" aria-autocomplete="list"
            @required($required) aria-required="{{ $required ? 'true' : 'false' }}" aria-controls="{{ $pickerId }}-list" :aria-expanded="open.toString()"
            :aria-activedescendant="open && active >= 0 ? items()[active]?.id : null"
            aria-describedby="{{ $pickerId }}-help {{ $pickerId }}-feedback" aria-invalid="{{ $errors->has($model) ? 'true' : 'false' }}"
            placeholder="Busque por nombre o ubicación"
            @focus="open = true" @input="input($event.target.value)"
            @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
            @keydown.enter.prevent="if (open) choose(items()[active >= 0 ? active : (items().length === 1 ? 0 : -1)])"
            @keydown.escape.stop.prevent="open = false; active = -1" @keydown.tab="open = false">
        <button class="promoter-picker-clear" type="button" x-show="selected !== '' || text !== ''" x-cloak @click="clear()" aria-label="{{ $multiple ? 'Borrar búsqueda' : 'Quitar selección de farmacia' }}">×</button>
    </div>
    <p id="{{ $pickerId }}-help" class="raven-field-help">Nombre, municipio, departamento, barrio, dirección o sucursal. @if($multiple) Marque las opciones que necesite; la búsqueda permanecerá abierta.@else Seleccione un resultado.@if($onSelect) Se añadirá a su selección antes de guardar.@endif @endif</p>
    <div class="promoter-picker-results" x-show="open" x-cloak>
        @if($multiple)<div class="promoter-multi-picker-heading"><span role="status" aria-live="polite" aria-atomic="true">{{ count($selectedIds) }} seleccionados</span><button type="button" class="promoter-subtle-link" @click="open = false; $refs.input.focus(); open = false">Cerrar lista</button></div>@endif
        <p class="raven-field-help promoter-picker-status" role="status" x-show="searching">Buscando establecimientos…</p>
        <ul id="{{ $pickerId }}-list" x-ref="list" role="listbox" @if($multiple) aria-multiselectable="true" @endif aria-label="Establecimientos asignados" :aria-busy="searching.toString()">
            @forelse($options as $option)
                <li role="presentation" wire:key="picker-option-{{ $option->id }}">
                    <button id="{{ $pickerId }}-option-{{ $option->id }}" type="button" role="option" tabindex="-1"
                        data-id="{{ $option->id }}" data-label="{{ $option->display_name }}{{ isset($identifiers[$option->id]) ? ' — '.$identifiers[$option->id] : '' }}"
                        @if($multiple) aria-selected="{{ in_array($option->id, $selectedIds, true) ? 'true' : 'false' }}" @else :aria-selected="(selected === @js((string) $option->id)).toString()" @endif :disabled="searching"
                        :class="{'is-highlighted': active === {{ $loop->index }}}"
                        @mousedown.prevent @click="choose($el)">
                        @if($multiple)<span class="promoter-multi-check" aria-hidden="true">{{ in_array($option->id, $selectedIds, true) ? '✓' : '' }}</span>@endif
                        <strong>{{ $option->display_name }}@if(isset($identifiers[$option->id])) <span class="promoter-picker-identifier">— {{ $identifiers[$option->id] }}</span>@endif</strong>
                        <span>{{ $option->display_locality ?: 'Ubicación por completar' }}</span>
                        <small>{{ $option->display_address ?: 'Dirección detallada por completar' }}</small>
                    </button>
                </li>
            @empty
                <li role="presentation"><p class="promoter-picker-status" role="status" x-show="!searching">No se encontraron establecimientos asignados.</p></li>
            @endforelse
        </ul>
        @if($hasMore)<p class="raven-field-help promoter-picker-status">Se muestran 20 resultados. Afine la búsqueda.</p>@endif
    </div>
    @if($selected)
        <div class="promoter-picker-selected" role="status">
            <x-promoter.icon name="check" /><div><strong>{{ $selectedLabel }}</strong>
                <x-promoter.location :department="$selected->department" :city="$selected->city" :neighborhood="$selected->neighborhood" :address="$selected->address" />
            </div>
        </div>
    @endif
    <div id="{{ $pickerId }}-feedback">@error($model)<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror</div>
</div>
