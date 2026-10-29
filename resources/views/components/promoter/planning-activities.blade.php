@props(['activities','schedule','today','context' => 'day'])
                        <ul class="promoter-planning-list">
                            @foreach($activities as $activity)
                                <li class="promoter-planning-item {{ $activity->scheduled_date->lt($today) ? 'is-past' : '' }}" wire:key="planning-{{ $context }}-{{ $activity->id }}">
                                    <div class="promoter-planning-date">
                                        <span>{{ $activity->scheduled_date->locale('es')->translatedFormat('D') }}</span>
                                        <strong>{{ $activity->scheduled_date->format('j') }}</strong>
                                    </div>
                                    <div class="promoter-planning-body">
                                        <div class="promoter-planning-status">
                                            <span class="raven-badge {{ $activity->activity_type === \App\Enums\ActivityType::VISITA ? 'raven-badge-info' : 'raven-badge-neutral' }}">{{ $activity->activity_type->label() }}</span>
                                            @if(! $schedule->isDraft() && $progress = $activity->progress())
                                                <span class="raven-badge {{ $progress->badge() }}">{{ $progress->label() }}</span>
                                            @endif
                                            @if($activity->reschedule_count > 0)
                                                <span class="raven-badge raven-badge-rose">Reprogramada</span>
                                            @endif
                                        </div>
                                        @if($activity->pharmacy)
                                            <p class="promoter-planning-title">{{ $activity->pharmacy->display_name }}</p>
                                            @if($activity->pharmacy)<x-promoter.location :department="$activity->pharmacy->department" :city="$activity->pharmacy->city" :neighborhood="$activity->pharmacy->neighborhood" :address="$activity->pharmacy->address" />@endif
                                        @endif
                                        @if($activity->notes)
                                            <p class="promoter-dashboard-help">{{ $activity->notes }}</p>
                                        @endif
                                    </div>
                                    @if($schedule->isDraft())
                                        <button type="button" class="raven-button raven-button-ghost"
                                                wire:click="removeActivity({{ $activity->id }})"
                                                wire:confirm="¿Eliminar esta actividad del cronograma?"
                                                aria-label="Eliminar actividad del {{ $activity->scheduled_date->format('d/m') }}">
                                            Quitar
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
