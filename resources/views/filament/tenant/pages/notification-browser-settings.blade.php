@php
    $panelUrl = rtrim(\Filament\Facades\Filament::getPanel('admin')->getUrl(), '/');
    $tenant = currentTenant();
    $config = [
        'tenantId' => $tenant?->id,
        'userId' => auth()->id(),
        'vapidUrl' => $panelUrl.'/notification-browser/vapid-public',
        'preferencesLoadUrl' => $panelUrl.'/notification-browser/preferences',
        'preferencesSaveUrl' => $panelUrl.'/notification-browser/preferences',
        'watermarkUrl' => $panelUrl.'/notification-browser/crm-watermark',
        'pushStoreUrl' => $panelUrl.'/notification-push/subscriptions',
        'pushDestroyUrl' => $panelUrl.'/notification-push/subscriptions',
        'swUrl' => asset('tenant-notification-sw.js'),
        'csrf' => csrf_token(),
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Системные уведомления</x-slot>
            <x-slot name="description">
                Нативные уведомления ОС при новой заявке (Web Push и service worker). Работают в защищённом контексте (HTTPS).
            </x-slot>

            <div class="prose prose-sm dark:prose-invert max-w-none text-gray-600 dark:text-gray-400">
                <p>
                    Звук при новой заявке можно включить отдельно: он воспроизводится только при <strong>открытой</strong> вкладке кабинета и после явного действия в этом разделе (политика autoplay в браузерах).
                </p>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    id="tenant-notify-btn-permission"
                    class="fi-btn fi-btn-color-primary fi-btn-size-md inline-flex items-center justify-center gap-x-1 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm ring-1 transition duration-75 fi-color-custom bg-custom-600 text-white ring-custom-600 hover:bg-custom-500 focus-visible:ring-2 dark:bg-custom-500 dark:ring-offset-gray-900"
                >
                    Запросить разрешение и подписаться
                </button>
                <button
                    type="button"
                    id="tenant-notify-btn-unsubscribe"
                    class="fi-btn fi-btn-color-gray fi-btn-size-md inline-flex items-center justify-center gap-x-1 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition duration-75 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:hover:bg-white/10"
                >
                    Отключить push на этом устройстве
                </button>
            </div>

            <p id="tenant-notify-status" class="mt-3 text-sm text-gray-600 dark:text-gray-400" role="status"></p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Звук при открытой вкладке</x-slot>
            <x-slot name="description">
                Короткий сигнал при новой заявке, если вкладка открыта и звук включён.
            </x-slot>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    id="tenant-notify-btn-sound-toggle"
                    class="fi-btn fi-btn-color-gray fi-btn-size-md inline-flex items-center justify-center gap-x-1 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition duration-75 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:hover:bg-white/10"
                >
                    Включить звук (и разблокировать воспроизведение)
                </button>
                <button
                    type="button"
                    id="tenant-notify-btn-test-sound"
                    class="fi-btn fi-btn-color-gray fi-btn-size-md inline-flex items-center justify-center gap-x-1 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition duration-75 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:hover:bg-white/10"
                >
                    Проверить звук
                </button>
            </div>
            <p id="tenant-notify-sound-status" class="mt-3 text-sm text-gray-600 dark:text-gray-400" role="status"></p>
        </x-filament::section>
    </div>

    @vite('resources/js/tenant-admin-notifications.js')
    <script type="application/json" id="tenant-notify-page-config">@json($config)</script>
</x-filament-panels::page>
