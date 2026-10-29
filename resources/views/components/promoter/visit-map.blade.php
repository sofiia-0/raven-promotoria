@props(['visit'=>null,'url'=>null,'live'=>false,'authorized'=>false])
@php $mapId = $live ? 'registration-map' : 'recorded-visit-map'; @endphp
<section class="{{ $live ? 'promoter-registration-map' : 'raven-card promoter-visit-map' }}" aria-labelledby="{{ $mapId }}-title"
    wire:key="{{ $mapId }}" data-map-mode="{{ $live ? 'register' : 'detail' }}" data-authorized="{{ $authorized ? '1' : '0' }}"
    data-latitude="{{ $visit?->getRawOriginal('latitude') }}" data-longitude="{{ $visit?->getRawOriginal('longitude') }}" data-accuracy="{{ $visit?->location_accuracy }}"
    x-data="{
        authorized: false, busy: false, problem: '', root: null,
        init() { this.root = this.$el; this.authorized = this.root.dataset.authorized === '1'; },
        latitude() { return this.root.dataset.mapMode === 'register' ? this.$wire.latitude : this.root.dataset.latitude; },
        longitude() { return this.root.dataset.mapMode === 'register' ? this.$wire.longitude : this.root.dataset.longitude; },
        valid() {
            const lat=this.latitude(),lon=this.longitude();
            return lat !== null && lon !== null && String(lat).trim() !== '' && String(lon).trim() !== '' && Number.isFinite(Number(lat)) && Number.isFinite(Number(lon)) && Math.abs(Number(lat)) <= 90 && Math.abs(Number(lon)) <= 180;
        },
        accuracyValue() { const n=this.root.dataset.mapMode === 'register' ? this.$wire.locationAccuracy : this.root.dataset.accuracy; return n !== null && String(n).trim() !== '' && Number.isFinite(Number(n)) && Number(n) >= 0 ? Number(n) : null; },
        mapUrl() {
            if(!this.valid()) return null;
            const lat=Number(this.latitude()),lon=Number(this.longitude());
            const bbox=[Math.max(-180,lon-.006),Math.max(-90,lat-.004),Math.min(180,lon+.006),Math.min(90,lat+.004)].join(',');
            return 'https://www.openstreetmap.org/export/embed.html?'+new URLSearchParams({bbox,layer:'mapnik',marker:lat+','+lon});
        },
        async authorize() { if(this.busy)return;this.busy=true;this.problem='';try{this.authorized=await this.$wire.authorizeVisitMap() === true;}catch(e){this.problem='No se pudo guardar la autorización. Inténtelo de nuevo.';}finally{this.busy=false;} },
        async revoke() { if(this.busy)return;this.busy=true;try{if(await this.$wire.revokeVisitMap() === true)this.authorized=false;}catch(e){this.problem='No se pudo retirar la autorización. Inténtelo de nuevo.';}finally{this.busy=false;} }
    }" x-show="valid()" x-cloak>
    <div class="promoter-evidence-heading"><div><h2 id="{{ $mapId }}-title" class="raven-section-title">{{ $live ? 'Mapa de la ubicación capturada' : 'Ubicación registrada' }}</h2>
        <p x-show="accuracyValue() !== null" x-text="'Precisión aproximada indicada por el dispositivo: ± '+accuracyValue()+' m.'">@if(!$live && $visit?->location_accuracy !== null && $visit->location_accuracy >= 0)Precisión aproximada indicada por el dispositivo: ± {{ $visit->location_accuracy }} m.@endif</p>
    </div><x-promoter.icon name="location" /></div>
    <p class="raven-card-description">La ubicación es información complementaria; no acredita por sí sola la realización de la visita.</p>
    <div class="promoter-map-consent" x-show="!authorized"><p>OpenStreetMap recibirá las coordenadas mostradas y su dirección IP. La autorización se recordará durante esta sesión para los mapas de sus visitas. No se cargará contenido externo antes de autorizarlo.</p><button type="button" class="raven-button raven-button-secondary" @click="authorize()" :disabled="busy">Autorizar mapas de OpenStreetMap</button></div>
    <template x-if="authorized &amp;&amp; valid()"><div class="promoter-map-loaded"><iframe :src="mapUrl()" title="Mapa de la ubicación de la visita" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-same-origin allow-popups" class="promoter-map-frame"></iframe><div class="promoter-map-footer"><span>© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a></span><button type="button" class="raven-button raven-button-ghost promoter-map-revoke" @click="revoke()" :disabled="busy">Retirar autorización de esta sesión</button></div></div></template>
    <p class="raven-field-error" role="alert" x-text="problem" x-show="problem" x-cloak></p>
</section>
