@props(['payload' => null])

@if($payload)
    {{-- Single root: JS берёт последний [data-tenant-site-setup-root]; payload/bar/template — data-*, без дублирующихся id. --}}
    <div data-tenant-site-setup-root class="fi-ts-setup-root">
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
            data-tenant-site-setup="bar"
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
        <template data-tenant-site-setup="inline-template">
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

    @if($actionUrl)
        {{-- Один экземпляр на странице (вне <template>, чтобы id не дублировался при клонировании карточки). --}}
        <dialog
            id="fi-ts-setup-not-needed-dialog"
            class="fi-ts-setup-not-needed-dialog"
            aria-labelledby="fi-ts-setup-not-needed-title"
        >
            <div class="fi-ts-setup-not-needed-dialog-panel">
                <h2 id="fi-ts-setup-not-needed-title" class="fi-ts-setup-not-needed-dialog-title">
                    Подтвердить «Не требуется»
                </h2>
                <p class="fi-ts-setup-not-needed-dialog-lead">
                    Вы исключаете текущий шаг из обязательного прогресса запуска. Это осознанное решение — ниже кратко, что изменится.
                </p>
                <ul class="fi-ts-setup-not-needed-dialog-list">
                    <li>Пункт будет отмечен как <strong>не требуется</strong> и уйдёт из очереди быстрого запуска.</li>
                    <li>Контент и настройки на сайте <strong>не удаляются</strong> — фиксируется только статус пункта в чеклисте.</li>
                    <li>В <strong>обзоре запуска</strong> позже можно вернуть пункт в работу и пройти шаг снова.</li>
                </ul>
                <p class="fi-ts-setup-not-needed-dialog-note">
                    Если шаг ещё важен для вашего проекта, лучше нажать «Позже» или завершить поле и «Сохранить», затем «Дальше».
                </p>
                <div class="fi-ts-setup-not-needed-dialog-actions">
                    <button
                        type="button"
                        class="fi-ts-setup-btn fi-ts-setup-btn-secondary"
                        onclick="document.getElementById('fi-ts-setup-not-needed-dialog')?.close()"
                    >
                        <span class="fi-ts-setup-btn-label">Отмена</span>
                    </button>
                    <form method="post" action="{{ $actionUrl }}" class="fi-ts-setup-not-needed-dialog-submit-form">
                        @csrf
                        <input type="hidden" name="action" value="not_needed" />
                        <button type="submit" class="fi-ts-setup-btn fi-ts-setup-btn-accent">
                            <span class="fi-ts-setup-btn-label">Да, исключить пункт</span>
                        </button>
                    </form>
                </div>
            </div>
        </dialog>
    @endif
    <script type="application/json" data-tenant-site-setup="payload">@json($payload)</script>
    </div>
@endif
