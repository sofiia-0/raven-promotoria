@props(['model', 'label', 'value' => '', 'options' => [], 'required' => false, 'disabled' => false])
@php
    $items = collect($options)->values()->all();
    $controlId = 'geo-'.$model;
@endphp
<div class="raven-field promoter-combobox"
    wire:key="{{ $controlId }}-{{ md5(json_encode($items)) }}"
    x-data="{
        options: @js($items),
        selected: $wire.entangle(@js($model)).live,
        query: @js($value), open: false, active: -1,
        normalize(value) { return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); },
        filtered() {
            const term = this.query === this.selected ? '' : this.normalize(this.query);
            return this.options.filter(option => this.normalize(option).includes(term));
        },
        choose(option) {
            if (!option) return;
            this.selected = option; this.query = option; this.open = false; this.active = -1;
            this.$refs.input.focus();
            this.open = false;
        },
        move(direction) {
            this.open = true;
            const count = this.filtered().length;
            if (!count) { this.active = -1; return; }
            this.active = this.active < 0 ? (direction > 0 ? 0 : count - 1) : (this.active + direction + count) % count;
            this.$nextTick(() => document.getElementById(@js($controlId).concat('-option-', this.active))?.scrollIntoView({block: 'nearest'}));
        },
        init() {
            this.$watch('selected', value => { if (!this.open) this.query = value || ''; });
        }
    }"
    @click.outside="open = false"
    @focusout="if (!$el.contains($event.relatedTarget)) { open = false; query = selected || ''; }">
    <label class="raven-label" for="{{ $controlId }}">{{ $label }} @if($required)<span class="promoter-required" aria-hidden="true">*</span>@endif</label>
    <div class="promoter-combobox-control">
        <input id="{{ $controlId }}" x-ref="input" class="raven-input" type="text" value="{{ $value }}" x-model="query"
            role="combobox" autocomplete="off" aria-autocomplete="list"
            aria-controls="{{ $controlId }}-list" :aria-expanded="open.toString()"
            :aria-activedescendant="open && active >= 0 ? @js($controlId).concat('-option-', active) : null"
            aria-describedby="feedback-{{ $model }}" aria-invalid="{{ $errors->has($model) ? 'true' : 'false' }}"
            placeholder="{{ $disabled ? 'Seleccione primero el departamento' : 'Escriba para buscar' }}"
            @required($required) @disabled($disabled)
            @focus="open = true; active = -1"
            @input="open = true; active = -1"
            @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
            @keydown.enter.prevent="if (open && active >= 0) choose(filtered()[active]); else if (open && filtered().length === 1) choose(filtered()[0])"
            @keydown.escape.stop.prevent="open = false; query = selected || ''; active = -1"
            @keydown.tab="open = false; query = selected || ''">
        <span class="promoter-combobox-chevron" aria-hidden="true">⌄</span>
        <ul id="{{ $controlId }}-list" class="promoter-combobox-list" role="listbox" aria-label="{{ $label }}" x-show="open" x-cloak>
            <template x-for="(option, index) in filtered()" :key="option">
                <li role="presentation"><button type="button" role="option" tabindex="-1"
                    :id="@js($controlId).concat('-option-', index)" :aria-selected="(selected === option).toString()"
                    :class="{'is-highlighted': active === index}" @mousedown.prevent @click="choose(option)" x-text="option"></button></li>
            </template>
        </ul>
        <p class="promoter-combobox-empty" role="status" x-show="open && filtered().length === 0" x-cloak>No se encontraron opciones.</p>
    </div>
    <div id="feedback-{{ $model }}">@error($model)<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror</div>
</div>
