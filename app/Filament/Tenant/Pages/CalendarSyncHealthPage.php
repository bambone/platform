<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Support\SchedulingAdminNavigationPrerequisites;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use App\Models\CalendarConnection;
use App\Scheduling\Enums\SchedulingScope;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use UnitEnum;

class CalendarSyncHealthPage extends Page
{
    protected static ?string $navigationLabel = 'Синхронизация календарей';

    protected static ?string $title = 'Состояние синхронизации календарей';

    protected static ?string $slug = 'scheduling/calendar-sync-health';

    protected static string|UnitEnum|null $navigationGroup = 'SchedulingCalendars';

    protected static ?int $navigationSort = 32;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected string $view = 'filament.tenant.pages.calendar-sync-health';

    public static function canAccess(): bool
    {
        $tenant = currentTenant();

        return $tenant !== null
            && $tenant->scheduling_module_enabled
            && Gate::allows('manage_scheduling');
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! static::$shouldRegisterNavigation) {
            return false;
        }

        $tenant = currentTenant();

        return SchedulingAdminNavigationPrerequisites::calendarIntegrationsEnabledForTenant($tenant)
            && SchedulingAdminNavigationPrerequisites::tenantHasCalendarConnections($tenant);
    }

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'calendarSyncHealthWhatIs',
                [
                    'Сводка по подключённым календарям: последний успешный sync и ошибки.',
                    '',
                    'Детали подписки и действие «Синхр. busy» — в карточке подключения.',
                ],
                'Справка по синхронизации календарей',
            ),
        ];
    }

    /**
     * @return Collection<int, CalendarConnection>
     */
    #[Computed]
    public function connections()
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return CalendarConnection::query()->whereRaw('1 = 0')->get();
        }

        return CalendarConnection::query()
            ->where('scheduling_scope', SchedulingScope::Tenant)
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('is_active')
            ->orderBy('display_name')
            ->get();
    }
}
