@php
    $q = $this->quota;
@endphp

@if ($q === null)
    <x-filament-widgets::widget class="hidden"></x-filament-widgets::widget>
@else
    <x-filament-widgets::widget>
        <x-filament::section
            :class="$this->isCriticalLayout()
                ? 'fi-storage-quota-widget fi-storage-quota-widget--critical ring-2 ring-danger-500/40'
                : 'fi-storage-quota-widget'"
        >
            <x-slot name="heading">
                Хранилище
            </x-slot>
            <x-slot name="description">
                @if ($q->status === \App\Tenant\StorageQuota\TenantStorageQuotaStatus::Exceeded)
                    Лимит исчерпан — новые загрузки заблокированы до расширения квоты.
                @elseif ($q->status === \App\Tenant\StorageQuota\TenantStorageQuotaStatus::Critical10)
                    Осталось менее {{ $q->criticalThresholdPercent }}% места — скоро загрузки могут стать недоступны.
                @elseif ($q->status === \App\Tenant\StorageQuota\TenantStorageQuotaStatus::Warning20)
                    Осталось менее {{ $q->warningThresholdPercent }}% свободного места.
                @else
                    Использование файлового пространства клиента.
                @endif
            </x-slot>

            <div class="space-y-3">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="text-2xl font-semibold text-gray-950 dark:text-white">
                        {{ \Illuminate\Support\Number::fileSize($q->usedBytes, precision: 1) }}
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        из {{ \Illuminate\Support\Number::fileSize($q->effectiveQuotaBytes, precision: 1) }}
                    </span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="presentation">
                    @include('filament.tenant.partials.storage-quota-progress-meter', [
                        'usedPercent' => $q->usedPercent,
                        'tier' => $q->progressBarTier(),
                        'variant' => 'widget',
                    ])
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="text-gray-600 dark:text-gray-400">
                        Свободно: {{ \Illuminate\Support\Number::fileSize($q->freeBytes, precision: 1) }}
                    </span>
                    <a href="{{ \App\Filament\Tenant\Pages\StorageMonitoringPage::getUrl() }}" class="text-primary-600 underline dark:text-primary-400">
                        Подробнее
                    </a>
                </div>
            </div>
        </x-filament::section>
    </x-filament-widgets::widget>
@endif
