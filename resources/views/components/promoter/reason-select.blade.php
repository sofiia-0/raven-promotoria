@props(['model','label','value'=>'','options','required'=>false])
@php
    $ids = $options->map(fn($option)=>(string)$option->id)->all();
    $labels = $options->mapWithKeys(fn($option)=>[(string)$option->id=>$option->name])->all();
@endphp
<div class="promoter-reason-selector">
    <x-promoter.searchable-select :model="$model" :label="$label" :value="$value" :options="$ids" :labels="$labels" :required="$required" clearable />
</div>
