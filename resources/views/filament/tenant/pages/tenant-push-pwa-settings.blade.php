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
@endphp
<x-filament-panels::page>
    @if($pushView)
        @php
            $pv = $pushView->settings->providerStatusEnum();
            $warnVerifiedNoDelivery = $pv === \App\TenantPush\TenantPushProviderStatus::Verified
                && ! $pushView->readyForEventDelivery
                && $pushView->gate->isFeatureEntitled();
        @endphp
        <div class="mb-6 space-y-4">
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
            <div class="mt-6 flex flex-wrap gap-3">
                <x-filament::button type="submit">
                    Сохранить
                </x-filament::button>
            </div>
        @endif
    </form>
</x-filament-panels::page>
