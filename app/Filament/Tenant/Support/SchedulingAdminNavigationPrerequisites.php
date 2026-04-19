<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Support;

use App\Models\BookableService;
use App\Models\CalendarConnection;
use App\Models\CalendarSubscription;
use App\Models\SchedulingResource;
use App\Models\Tenant;
use App\Scheduling\Enums\SchedulingScope;

/**
 * Условия показа пунктов меню «Запись» без тупиков: календари, доступность, инструменты.
 */
final class SchedulingAdminNavigationPrerequisites
{
    public static function calendarIntegrationsEnabledForTenant(?Tenant $tenant): bool
    {
        return $tenant !== null && (bool) $tenant->calendar_integrations_enabled;
    }

    public static function tenantHasSchedulingResources(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        return SchedulingResource::query()
            ->where('scheduling_scope', SchedulingScope::Tenant)
            ->where('tenant_id', $tenant->id)
            ->exists();
    }

    public static function tenantHasBookableServices(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        return BookableService::query()
            ->where('scheduling_scope', SchedulingScope::Tenant)
            ->where('tenant_id', $tenant->id)
            ->exists();
    }

    public static function tenantHasCalendarConnections(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        return CalendarConnection::query()
            ->where('scheduling_scope', SchedulingScope::Tenant)
            ->where('tenant_id', $tenant->id)
            ->exists();
    }

    public static function tenantHasCalendarSubscriptions(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        return CalendarSubscription::query()
            ->whereHas('calendarConnection', function ($q) use ($tenant): void {
                $q->where('scheduling_scope', SchedulingScope::Tenant)
                    ->where('tenant_id', $tenant->id);
            })
            ->exists();
    }

    /**
     * Создание сопоставления занятости имеет смысл только при включённых интеграциях и хотя бы одной подписке.
     */
    public static function tenantCanCreateOccupancyMapping(?Tenant $tenant): bool
    {
        return self::calendarIntegrationsEnabledForTenant($tenant)
            && self::tenantHasCalendarSubscriptions($tenant);
    }
}
