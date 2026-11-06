<div class="assign-inline">
    @if(session('success'))<p class="raven-alert raven-alert-success" role="status">{{ session('success') }}</p>@endif
    @if($editorOpen && !$operationCompleted)
        @include('livewire.coordinator.assignments.form-panel')
    @endif
</div>
