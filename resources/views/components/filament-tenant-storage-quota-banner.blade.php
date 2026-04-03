@php
    use App\Filament\Tenant\Pages\StorageMonitoringPage;
    use App\Tenant\StorageQuota\TenantStorageQuotaService;
    use App\Tenant\StorageQuota\TenantStorageQuotaStatus;

    $tenant = currentTenant();
    $q = $tenant ? app(TenantStorageQuotaService::class)->forTenant($tenant) : null;
@endphp

@if ($q !== null && $q->status !== TenantStorageQuotaStatus::Ok)
    @php
        $isExceeded = $q->status === TenantStorageQuotaStatus::Exceeded;
        $isCritical = $q->status === TenantStorageQuotaStatus::Critical10;
        $wrap = $isExceeded
            ? 'border-red-200 bg-red-50 text-red-950 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-50'
            : ($isCritical
                ? 'border-orange-200 bg-orange-50 text-orange-950 dark:border-orange-900/50 dark:bg-orange-950/40 dark:text-orange-50'
                : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-50');
        if ($isExceeded) {
            $msg = 'Лимит хранилища исчерпан. Новые загрузки заблокированы. Для расширения обратитесь к администратору.';
        } elseif ($isCritical) {
            $msg = 'Осталось менее 10% доступного хранилища. Новые загрузки скоро могут стать недоступны. Для расширения обратитесь к администратору.';
        } else {
            $msg = 'Осталось менее 20% доступного хранилища. Рекомендуем заранее расширить лимит.';
        }
    @endphp
    <div class="fi-storage-quota-banner mx-4 mb-4 rounded-lg border p-4 text-sm leading-relaxed sm:mx-6 {{ $wrap }}" role="status">
        <div class="font-medium">{{ $msg }}</div>
        @if ($q->isStaleSync)
            <p class="mt-2 text-xs opacity-90">Показатели могут быть устаревшими (давно не было синхронизации с хранилищем).</p>
        @endif
        <p class="mt-2">
            <a href="{{ StorageMonitoringPage::getUrl() }}" class="underline font-medium">Открыть мониторинг и лимиты</a>
        </p>
    </div>
@endif
