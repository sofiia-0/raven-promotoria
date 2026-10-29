@props(['ids','files','batchKey'])
<section class="promoter-evidence-upload" aria-labelledby="evidence-upload-title" wire:key="evidence-upload-{{ $batchKey }}" data-upload-url="{{ route('promoter.visits.evidence.upload') }}" data-batch-key="{{ $batchKey }}" data-csrf-token="{{ csrf_token() }}"
    x-data="{
        busy: false, problem: '', thumbs: {},
        state() { window.dispatchEvent(new CustomEvent('evidence-state', {detail: {busy: this.busy, problem: this.problem !== ''}})); },
        async uploadFiles(list) {
            if (this.busy) return;
            const incoming = [...list]; if (!incoming.length) return;
            const existing = [...this.$root.querySelectorAll('[data-evidence-size]')];
            if (incoming.length + existing.length > 10 || incoming.some(f => f.size > 10485760) || incoming.reduce((n,f)=>n+f.size,0) + existing.reduce((n,e)=>n+Number(e.dataset.evidenceSize),0) > 41943040) {
                this.problem = 'Seleccione hasta 10 archivos, de 10 MiB como máximo cada uno y 40 MiB en total.'; this.$refs.files.value = ''; this.$refs.camera.value = ''; this.state(); return;
            }
            this.busy = true; this.problem = ''; this.state();
            try {
                for (const file of incoming) {
                    const form = new FormData(); form.append('file', file); form.append('batch_key', this.$root.dataset.batchKey);
                    const response = await fetch(this.$root.dataset.uploadUrl, {method: 'POST', credentials: 'same-origin', headers: {'X-CSRF-TOKEN': this.$root.dataset.csrfToken, 'Accept':'application/json'}, body: form});
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(data.errors?.evidence?.[0] || data.errors?.file?.[0] || (response.status === 413 ? 'El archivo supera el tamaño admitido por el servidor.' : response.status === 419 ? 'La sesión ha caducado. Recargue la página antes de continuar.' : data.message || 'No se pudo cargar el archivo.'));
                    if (data.previewable) this.thumbs[data.id] = URL.createObjectURL(new Blob([file], {type: data.mime}));
                    const accepted = await this.$wire.addEvidenceUpload(data.id);
                    if (accepted !== true) throw new Error('No se pudo añadir el archivo a su selección. Inténtelo de nuevo.');
                }
            } catch (error) { this.problem = error.message || 'No se pudo cargar el archivo. Inténtelo de nuevo.'; }
            finally { this.busy = false; this.$refs.files.value = ''; this.$refs.camera.value = ''; this.state(); }
        },
        async remove(id) { if(this.busy) return; this.busy=true;this.state();try{const removed = await this.$wire.removeEvidenceUpload(id);if(removed !== true) throw new Error('No se pudo quitar el archivo. Inténtelo de nuevo.');if(this.thumbs[id]){URL.revokeObjectURL(this.thumbs[id]);delete this.thumbs[id];}}catch(error){this.problem='No se pudo quitar el archivo. Inténtelo de nuevo.';}finally{this.busy=false;this.state();} },
        destroy() {Object.values(this.thumbs).forEach(url => URL.revokeObjectURL(url));}
    }">
    <div class="promoter-evidence-heading"><div><h3 id="evidence-upload-title">Evidencias <span>(opcional)</span></h3><p>Adjunte fotografías o documentos de esta visita.</p></div><x-promoter.icon name="file" /></div>
    <label for="visit-evidence-files" class="promoter-sr-only">Seleccionar varias evidencias</label>
    <input id="visit-evidence-files" class="promoter-sr-only" tabindex="-1" type="file" x-ref="files" multiple accept=".jpg,.jpeg,.png,.webp,.heic,.heif,.pdf,.docx,.xlsx,.pptx,.zip,.txt" @change="uploadFiles($event.target.files)">
    <label for="visit-evidence-camera" class="promoter-sr-only">Tomar una fotografía</label>
    <input id="visit-evidence-camera" class="promoter-sr-only" tabindex="-1" type="file" x-ref="camera" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" capture="environment" @change="uploadFiles($event.target.files)">
    <div class="promoter-evidence-actions"><button type="button" class="raven-button raven-button-secondary" @click="$refs.files.click()" :disabled="busy"><x-promoter.icon name="add" />Elegir archivos</button><button type="button" class="raven-button raven-button-secondary" @click="$refs.camera.click()" :disabled="busy">Cámara</button></div>
    <p class="raven-field-help">Hasta 10 archivos · 10 MiB por archivo · 40 MiB por visita. Imágenes, PDF, DOCX, XLSX, PPTX, ZIP o TXT.</p>
    <p class="raven-field-help" x-show="busy" role="status">Cargando archivos. Espere antes de registrar la visita…</p>
    <div class="raven-alert raven-alert-danger" x-show="problem" x-cloak role="alert"><p x-text="problem"></p><button type="button" class="raven-button raven-button-secondary" @click="problem = ''; state()">Continuar sin los archivos rechazados</button></div>
    <ul class="promoter-evidence-selection" aria-label="Archivos seleccionados" aria-live="polite">
        @foreach($ids as $id)@php $file = $files->get($id); @endphp
            <li wire:key="evidence-{{ $id }}" data-evidence-size="{{ $file?->size ?? 0 }}"><div class="promoter-evidence-thumbnail"><template x-if="thumbs[@js($id)]"><img :src="thumbs[@js($id)]" alt="Vista previa del archivo seleccionado"></template><span x-show="!thumbs[@js($id)]"><x-promoter.icon name="file" /></span></div><div><strong>{{ $file?->original_name ?? 'Archivo no disponible' }}</strong><span>{{ $file?->displaySize() ?? 'Quite el archivo y vuelva a seleccionarlo.' }}</span></div><button type="button" class="promoter-evidence-remove" @click="remove(@js($id))" :disabled="busy" aria-label="Quitar {{ $file?->original_name ?? 'archivo' }}">Quitar</button></li>
        @endforeach
    </ul>
    <p class="raven-field-help" role="status">{{ count($ids) }} {{ count($ids) === 1 ? 'archivo seleccionado' : 'archivos seleccionados' }}. Se asociarán al registrar la visita.</p>
</section>
