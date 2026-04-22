@php
    $t = tenant();
    $pushView = $t
        ? \App\TenantPush\TenantPushSettingsView::make(
            $t,
            app(\App\TenantPush\TenantPushFeatureGate::class),
            app(\App\TenantPush\TenantPushCrmRequestRecipientResolver::class),
        )
        : null;
    $iosState = app(\App\TenantPush\TenantPushIosReadinessResolver::class)->stateForRequest();
    $stepUi = function (\App\TenantPush\TenantPushStepStatus $s): string {
        return match ($s) {
            \App\TenantPush\TenantPushStepStatus::NotStarted => 'не начато',
            \App\TenantPush\TenantPushStepStatus::Partial => 'частично',
            \App\TenantPush\TenantPushStepStatus::Ready => 'готово',
        };
    };
@endphp
<x-filament-panels::page>
    @if($pushView)
        @php
            $pv = $pushView->settings->providerStatusEnum();
            $gs = $pushView->guidedSetup;
            $warnVerifiedNoDelivery = $pv === \App\TenantPush\TenantPushProviderStatus::Verified
                && ! $pushView->readyForEventDelivery
                && $pushView->gate->isFeatureEntitled();
        @endphp
        <div class="mb-6 space-y-4">
            <div class="rounded-xl border border-primary-200/70 bg-primary-50/40 p-4 text-sm text-gray-800 dark:border-primary-800/50 dark:bg-primary-950/30 dark:text-gray-100">
                <div class="font-medium text-primary-900 dark:text-primary-100">С чего начать</div>
                <p class="mt-1 text-gray-600 dark:text-gray-300">Настраиваем по шагам: сначала домен и HTTPS, затем OneSignal, затем — уведомления о заявке. PWA внизу в блоке «Дополнительно».</p>
                <ol class="mt-3 grid gap-2 sm:grid-cols-2">
                    <li class="rounded border border-primary-200/50 bg-white/60 px-3 py-2 dark:border-primary-800/30 dark:bg-gray-900/50">
                        <span class="text-gray-500 dark:text-gray-400">1. Доступ / тариф</span>
                        <span class="ml-1 font-medium">{{ $stepUi($gs->step1) }}</span>
                    </li>
                    <li class="rounded border border-primary-200/50 bg-white/60 px-3 py-2 dark:border-primary-800/30 dark:bg-gray-900/50">
                        <span class="text-gray-500 dark:text-gray-400">2. Домен (HTTPS)</span>
                        <span class="ml-1 font-medium">{{ $stepUi($gs->step2) }}</span>
                    </li>
                    <li class="rounded border border-primary-200/50 bg-white/60 px-3 py-2 dark:border-primary-800/30 dark:bg-gray-900/50">
                        <span class="text-gray-500 dark:text-gray-400">3. OneSignal</span>
                        <span class="ml-1 font-medium">{{ $stepUi($gs->step3) }}</span>
                    </li>
                    <li class="rounded border border-primary-200/50 bg-white/60 px-3 py-2 dark:border-primary-800/30 dark:bg-gray-900/50">
                        <span class="text-gray-500 dark:text-gray-400">4. Заявки</span>
                        <span class="ml-1 font-medium">{{ $stepUi($gs->step4) }}</span>
                    </li>
                </ol>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="grid gap-2 md:grid-cols-2">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Функция (тариф / коммерция):</span>
                        <span class="ml-1 font-medium">{{ $pushView->gate->isFeatureEntitled() ? 'доступна' : 'недоступна' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Редактирование:</span>
                        <span class="ml-1 font-medium">{{ $pushView->gate->canEditSettings ? 'да' : 'только просмотр' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Провайдер (проверка ключей):</span>
                        <span class="ml-1 font-medium">{{ $pushView->providerStatusLabel() }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Подписки целевых получателей (CRM):</span>
                        <span class="ml-1 font-medium">{{ $pushView->subscriptionStatusLabel() }}</span>
                    </div>
                    <div class="md:col-span-2">
                        <span class="text-gray-500 dark:text-gray-400">Готовность к доставке событий:</span>
                        <span class="ml-1 font-medium">{{ $pushView->readyForEventDelivery ? 'да' : 'нет' }}</span>
                    </div>
                </div>
            </div>

            @if($warnVerifiedNoDelivery)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100">
                    {{ $pushView->readinessHint() }}
                </div>
            @else
                <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                    {{ $pushView->readinessHint() }}
                </div>
            @endif

            @if($iosState !== \App\TenantPush\TenantPushIosReadinessState::NotApplicable)
                <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="font-medium text-gray-900 dark:text-gray-100">iPhone / iPad (Safari)</div>
                    @if($iosState === \App\TenantPush\TenantPushIosReadinessState::IosNotSupported)
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Для web push нужна iOS/iPadOS 16.4 или новее.</p>
                    @elseif($iosState === \App\TenantPush\TenantPushIosReadinessState::IosReadyButNotInstalled)
                        <ol class="mt-2 list-decimal space-y-1 pl-5 text-gray-600 dark:text-gray-400">
                            <li>Откройте публичный сайт в Safari.</li>
                            <li>Поделиться → «На экран Домой».</li>
                            <li>Откройте сайт с иконки на главном экране.</li>
                            <li>Разрешите уведомления, затем снова войдите в кабинет — мы зафиксируем привязку.</li>
                        </ol>
                    @else
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Похоже, сайт открыт как установленное приложение. Можно переходить к разрешению уведомлений в OneSignal.</p>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <form wire:submit="save">
        {{ $this->form }}

        @if($pushView?->gate->canEditSettings)
            @php
                $gAction = $this->guidedStateForActions();
                $s = $pushView->settings;
            @endphp
            <div class="mt-6 space-y-3 rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="font-medium text-gray-900 dark:text-gray-100">Проверка и тест (OneSignal)</div>
                <p class="text-gray-600 dark:text-gray-400">Кнопки используют <strong>уже сохранённые</strong> в системе App ID и App API Key. После смены ключа сначала нажмите «Сохранить».</p>
                <div class="flex flex-wrap items-start gap-2">
                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="verifyOnesignal"
                        :disabled="!$gAction->canVerifyOnesignal"
                    >
                        Проверить OneSignal
                    </x-filament::button>
                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="sendTestPush"
                        :disabled="!$gAction->canSendTestPush"
                    >
                        Тестовый push
                    </x-filament::button>
                </div>
                @if($gAction->verifyActionDisabledMessage !== '')
                    <p class="text-sm text-amber-800 dark:text-amber-200/90">{{ $gAction->verifyActionDisabledMessage }}</p>
                @endif
                @if($gAction->testPushActionDisabledMessage !== '' && $gAction->testPushActionDisabledMessage !== $gAction->verifyActionDisabledMessage)
                    <p class="text-sm text-amber-800 dark:text-amber-200/90">{{ $gAction->testPushActionDisabledMessage }}</p>
                @endif
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <span>Статус проверки: {{ $pushView->providerStatusLabel() }}</span>
                    @if(filled($s->onesignal_last_verification_error))
                        <span> — последняя ошибка (фрагмент): {{ \Illuminate\Support\Str::limit((string) $s->onesignal_last_verification_error, 200) }}</span>
                    @endif
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    @if(filled($s->test_push_last_sent_at))
                        <span>Тест: отправка {{ $s->test_push_last_sent_at }} — {{ (string) ($s->test_push_last_result_status ?? '—') }}</span>
                    @else
                        <span>Тест: ещё не запускалась</span>
                    @endif
                </div>
            </div>
        @endif

        @if($pushView?->gate->canEditSettings)
            <div class="mt-6 flex flex-wrap gap-3">
                <x-filament::button type="submit">
                    Сохранить
                </x-filament::button>
            </div>
        @endif
    </form>
</x-filament-panels::page>
