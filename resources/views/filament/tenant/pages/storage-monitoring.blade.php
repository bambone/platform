@php
    /** @var \App\Filament\Tenant\Pages\StorageMonitoringPage $this */
    $q = $this->quotaData;
@endphp

<x-filament-panels::page>
    @if ($q === null)
        <p class="text-sm text-gray-600 dark:text-gray-400">Нет данных о хранилище.</p>
    @else
        <div class="space-y-8">
            @if ($q->isStaleSync)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-100">
                    Данные о занятом месте могут быть неполными: давно не выполнялась синхронизация с хранилищем.
                </div>
            @endif
            @if ($q->lastSyncErrorMessage)
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-100">
                    Последняя попытка синхронизации завершилась ошибкой. Показаны ранее известные значения.
                </div>
            @endif

            <x-filament::section>
                <x-slot name="heading">Использование хранилища</x-slot>
                <x-slot name="description">Файлы сайта, медиа и служебные снимки в вашем пространстве на сервере.</x-slot>

                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Выделено</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::fileSize($q->effectiveQuotaBytes, precision: 1) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Использовано</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::fileSize($q->usedBytes, precision: 1) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Осталось</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::fileSize($q->freeBytes, precision: 1) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Статус</dt>
                        <dd class="mt-1">
                            <x-filament::badge :color="match ($q->status) {
                                \App\Tenant\StorageQuota\TenantStorageQuotaStatus::Ok => 'success',
                                \App\Tenant\StorageQuota\TenantStorageQuotaStatus::Warning20 => 'warning',
                                \App\Tenant\StorageQuota\TenantStorageQuotaStatus::Critical10 => 'danger',
                                \App\Tenant\StorageQuota\TenantStorageQuotaStatus::Exceeded => 'danger',
                            }">
                                {{ \App\Filament\Tenant\Pages\StorageMonitoringPage::statusLabel($q->status) }}
                            </x-filament::badge>
                        </dd>
                    </div>
                </dl>

                <div class="mt-6">
                    <div class="mb-1 flex justify-between text-xs text-gray-600 dark:text-gray-400">
                        <span>Заполнено</span>
                        <span>{{ number_format($q->usedPercent, 1) }}%</span>
                    </div>
                    <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        @php
                            $bar = match ($q->progressBarTier()) {
                                'exceeded' => 'bg-red-600',
                                'danger' => 'bg-red-500',
                                'warning' => 'bg-amber-500',
                                default => 'bg-primary-500',
                            };
                            $w = min(100, $q->usedPercent);
                        @endphp
                        <div class="{{ $bar }} h-3 rounded-full transition-all" style="width: {{ $w }}%"></div>
                    </div>
                </div>
            </x-filament::section>

            @if (! empty($q->lastScanSummary))
                <x-filament::section>
                    <x-slot name="heading">Разбивка (последний скан)</x-slot>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Публичные файлы</dt>
                            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::fileSize((int) ($q->lastScanSummary['public_bytes'] ?? 0), precision: 1) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Приватные файлы</dt>
                            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::fileSize((int) ($q->lastScanSummary['private_bytes'] ?? 0), precision: 1) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Объектов</dt>
                            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ (int) ($q->lastScanSummary['object_count'] ?? 0) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Скан</dt>
                            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $q->lastSyncedFromStorageAt?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </x-filament::section>
            @endif

            <x-filament::section>
                <x-slot name="heading">История событий</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="py-2 pr-4 font-medium text-gray-950 dark:text-white">Дата</th>
                                <th class="py-2 pr-4 font-medium text-gray-950 dark:text-white">Событие</th>
                                <th class="py-2 font-medium text-gray-950 dark:text-white">Детали</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->recentEvents as $ev)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-4 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $ev->created_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</td>
                                    <td class="py-2 pr-4">{{ \App\Filament\Tenant\Pages\StorageMonitoringPage::eventTypeLabel($ev->type) }}</td>
                                    <td class="py-2 text-gray-600 dark:text-gray-400">
                                        @if (is_array($ev->payload) && $ev->payload !== [])
                                            <code class="text-xs break-all">{{ \Illuminate\Support\Str::limit(json_encode($ev->payload, JSON_UNESCAPED_UNICODE), 400) }}</code>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-gray-500 dark:text-gray-400">Событий пока нет.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Справка</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $this->expansionHint() }}</p>
                @if ($this->supportMailto())
                    <p class="mt-3">
                        <a href="{{ $this->supportMailto() }}" class="text-primary-600 underline dark:text-primary-400">Написать в поддержку</a>
                    </p>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
