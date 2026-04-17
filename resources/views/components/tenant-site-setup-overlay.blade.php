@props(['payload' => null])

@if($payload)
    @php
        $actionUrl = $payload['session_action_url'] ?? null;
        $canSnooze = ! empty($payload['can_snooze']);
        $canNotNeeded = ! empty($payload['can_not_needed']);
        $launchCritical = ! empty($payload['launch_critical']);
        $onTarget = ! empty($payload['on_target_route']);
        $canCompleteHere = ! empty($payload['can_complete_here']);
        $targetUrl = $payload['target_url'] ?? null;
        $targetUrl = is_string($targetUrl) ? $targetUrl : '';
        $primaryNav = ! empty($payload['primary_is_target_navigation']) && $targetUrl !== '';
        $targetTitle = $payload['target_title'] ?? ($payload['current_title'] ?? '');
        /** Первичный сценарий «Пропустить шаг» — не дублируем «Позже» (оба ослабляют текущий шаг). */
        $primaryIsSkipStep = $actionUrl && ! $canCompleteHere && ! $primaryNav;
    @endphp
    @if(! $onTarget)
        <div
            id="tenant-site-setup-bar"
            class="fi-ts-setup-bar fi-ts-setup-bar-fixed"
            role="region"
            aria-label="Быстрый запуск сайта"
        >
            <div class="fi-ts-setup-bar-inner">
                <div class="fi-ts-setup-bar-copy">
                    <p class="fi-ts-setup-bar-titleline">
                        <span class="fi-ts-setup-bar-label">Быстрый запуск:</span>
                        <span class="fi-ts-setup-bar-target">{{ $targetTitle }}</span>
                        @if(!empty($payload['steps_total']))
                            <span class="fi-ts-setup-bar-meta">
                                ({{ ($payload['step_index'] ?? 0) + 1 }}/{{ $payload['steps_total'] }})
                            </span>
                        @endif
                        @if($launchCritical)
                            <span class="fi-ts-setup-bar-critical">· критично</span>
                        @endif
                    </p>
                    <p class="fi-ts-setup-bar-sub">
                        Следующий шаг: «{{ $targetTitle }}». Перейдите на экран или откройте обзор запуска.
                    </p>
                </div>
                @include('components.partials.tenant-site-setup-overlay-actions')
            </div>
        </div>
    @else
        {{-- DOM для локальной карточки: вставляется JS рядом с секцией/полем, см. tenant-admin-site-setup.js --}}
        <template id="tenant-site-setup-inline-template">
            <div class="fi-ts-setup-inline-mount fi-ts-setup-inline-card">
                <div class="fi-ts-setup-inline-card-head">
                    <span class="fi-ts-setup-inline-badge">Быстрый запуск</span>
                    <span class="fi-ts-setup-inline-title">{{ $payload['current_title'] ?? $targetTitle }}</span>
                    @if(!empty($payload['steps_total']))
                        <span class="fi-ts-setup-inline-step">· {{ ($payload['step_index'] ?? 0) + 1 }}/{{ $payload['steps_total'] }}</span>
                    @endif
                    @if($launchCritical)
                        <span class="fi-ts-setup-inline-critical">· критично</span>
                    @endif
                </div>
                @if(! $canCompleteHere)
                    <p class="fi-ts-setup-inline-hint">
                        Откройте нужный блок или форму на этой странице, чтобы завершить шаг.
                    </p>
                @else
                    @php
                        $nextHint = (string) ($payload['guided_next_hint'] ?? 'save_then_next');
                    @endphp
                    @if($nextHint === 'auto_after_save')
                        <p class="fi-ts-setup-inline-hint fi-ts-setup-inline-hint-muted">
                            После сохранения шаг в прогрессе отметится автоматически. Кнопка «Дальше» только переводит очередь guided и не сохраняет настройки.
                        </p>
                    @elseif($nextHint === 'change_then_next')
                        <p class="fi-ts-setup-inline-hint fi-ts-setup-inline-hint-muted">
                            После изменения можно нажать «Дальше» — она переводит к следующему шагу очереди и сама по себе не подтверждает шаг в прогрессе.
                        </p>
                    @else
                        <p class="fi-ts-setup-inline-hint fi-ts-setup-inline-hint-muted">
                            Заполните поле и сохраните страницу (кнопка «Сохранить» у формы), затем нажмите «Дальше» — эта кнопка лишь переключает очередь guided, без сохранения данных.
                        </p>
                    @endif
                @endif
                @include('components.partials.tenant-site-setup-overlay-actions')
            </div>
        </template>
    @endif
    <script type="application/json" id="tenant-site-setup-payload">@json($payload)</script>
@endif
