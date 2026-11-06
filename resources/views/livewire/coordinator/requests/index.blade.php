@use('App\Models\PharmacyRequest')
@use('App\Services\SchedulePlanner')

@php
    $states = [
        PharmacyRequest::PENDING => ['Pendientes', 'clock'],
        PharmacyRequest::APPROVED => ['Aprobadas', 'check'],
        PharmacyRequest::REJECTED => ['Rechazadas', 'remove'],
    ];
@endphp

<div class="requests-workspace">
    @if(session('success'))
        <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>
    @endif

    @if($selected)
        @include('livewire.coordinator.requests.review')
    @endif

    @unless($standalone)
        <header class="requests-heading">
            <div>
                <p class="requests-kicker">Bandeja de revisión</p>
                <h1 class="raven-page-title">Solicitudes de alta y baja</h1>
                <p>Revise las propuestas de las promotoras. El fichero solo cambia cuando usted aprueba.</p>
            </div>
            <x-coordinator.team-motif />
        </header>

        <section class="requests-inbox" aria-label="Filtrar solicitudes">
            <div class="requests-states" role="group" aria-label="Estado de solicitud, totales generales">
                @foreach($states as $value => [$label, $icon])
                    <button type="button" class="requests-state" data-tone="{{ $value }}" aria-pressed="{{ $status === $value ? 'true' : 'false' }}" wire:click="$set('status', '{{ $value }}')">
                        <span class="requests-icon"><x-promoter.icon :name="$icon" /></span><span>{{ $label }}</span><strong>{{ number_format($counts->get($value, 0), 0, ',', '.') }}</strong>
                    </button>
                @endforeach
                <button type="button" class="requests-state requests-state-all" aria-pressed="{{ $status === '' ? 'true' : 'false' }}" wire:click="$set('status', '')">
                    <span class="requests-icon"><x-promoter.icon name="file" /></span><span>Todas</span>
                </button>
            </div>
            <div class="requests-filters">
                <label class="raven-field" for="requests-search">
                    <span>Buscar</span>
                    <span class="requests-search"><x-promoter.icon name="search" /><input id="requests-search" class="raven-input" type="search" placeholder="Establecimiento o promotora" wire:model.live.debounce.350ms="search"></span>
                </label>
                <label class="raven-field" for="requests-type">
                    <span>Tipo de solicitud</span>
                    <select id="requests-type" class="raven-select" wire:model.live="type">
                        <option value="">Altas y bajas</option>
                        <option value="{{ PharmacyRequest::TYPE_ALTA }}">Altas</option>
                        <option value="{{ PharmacyRequest::TYPE_BAJA }}">Bajas</option>
                    </select>
                </label>
                <label class="raven-field" for="requests-period">
                    <span>Período de envío</span>
                    <input id="requests-period" class="raven-input" type="month" wire:model.live="period">
                </label>
            </div>
        </section>

        <section class="raven-card requests-results" aria-labelledby="requests-results-title">
            <header class="requests-results-head">
                <h2 id="requests-results-title">{{ $requests->total() }} {{ $requests->total() === 1 ? 'solicitud' : 'solicitudes' }}</h2>
                <p>{{ $status === PharmacyRequest::PENDING ? 'En orden de llegada' : 'Más recientes primero' }}</p>
            </header>

            @if($requests->isEmpty())
                <x-coordinator.empty kind="inbox" title="No hay solicitudes con estos filtros" description="Cambie el estado o ajuste la búsqueda para ampliar los resultados." />
            @else
                <div class="requests-table-frame">
                    <table class="requests-list" role="table" aria-label="Solicitudes de alta y baja">
                        <thead role="rowgroup">
                            <tr role="row">
                                <th scope="col" role="columnheader">Establecimiento</th>
                                <th scope="col" role="columnheader">Promotora y motivo</th>
                                <th scope="col" role="columnheader">Envío</th>
                                <th scope="col" role="columnheader">Estado</th>
                                <th scope="col" role="columnheader"><span class="coordinator-sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody role="rowgroup">
                            @foreach($requests as $item)
                                @php($sentAt = $item->created_at?->timezone(SchedulePlanner::TIMEZONE))
                                <tr role="row" wire:key="request-row-{{ $item->id }}">
                                    <td class="requests-establishment" role="cell">
                                        <div class="requests-establishment-info">
                                            <x-coordinator.record-name :name="$item->establishmentTitle()" :locality="$item->establishmentLocality() ?: 'Ubicación sin registrar'" class="requests-row-title" />
                                            <x-coordinator.request-badge :value="$item->type" />
                                        </div>
                                    </td>
                                    <td class="requests-sender" role="cell">
                                        <strong>{{ $item->promoter?->name ?? 'Promotora sin registrar' }}</strong>
                                        @if(filled($item->reasonLabel()))<p>{{ $item->reasonLabel() }}</p>@endif
                                    </td>
                                    <td class="requests-date" role="cell">
                                        @if($sentAt)<time datetime="{{ $sentAt->toAtomString() }}"><span>{{ $sentAt->format('d/m/Y') }}</span><small>{{ $sentAt->format('H:i') }}</small></time>
                                        @else<span>Sin fecha</span>@endif
                                    </td>
                                    <td class="requests-row-status" role="cell"><x-coordinator.request-badge :value="$item->status" /></td>
                                    <td class="requests-row-action" role="cell">
                                        <a class="requests-action" href="{{ route('coordinator.requests.show', ['requestId' => $item->id] + $queueParameters) }}">
                                            {{ $item->status === PharmacyRequest::PENDING ? 'Revisar' : 'Ver detalle' }}
                                            <x-promoter.icon name="arrow" /><span class="coordinator-sr-only"> {{ $item->establishmentTitle() }}</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            {{ $requests->onEachSide(1)->links('livewire.coordinator.pagination', ['record' => 'solicitud', 'records' => 'solicitudes']) }}
        </section>
    @endunless
</div>
