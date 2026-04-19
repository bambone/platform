<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Concerns\ValidatesUtcDateRangeForDebugTools;
use App\Filament\Tenant\Resources\BookableServiceResource;
use App\Filament\Tenant\Support\SchedulingAdminNavigationPrerequisites;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use App\Models\BookableService;
use App\Scheduling\Enums\SchedulingScope;
use App\Scheduling\SlotEngineService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use UnitEnum;

class SlotDebugPage extends Page
{
    use ValidatesUtcDateRangeForDebugTools;

    protected static ?string $navigationLabel = 'Отладка слотов';

    protected static ?string $title = 'Отладка слотов (SlotEngine)';

    protected static ?string $slug = 'scheduling/slot-debug';

    protected static string|UnitEnum|null $navigationGroup = 'SchedulingTools';

    protected static ?int $navigationSort = 41;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bug-ant';

    protected string $view = 'filament.tenant.pages.slot-debug';

    public ?int $bookable_service_id = null;

    public string $range_from = '';

    public string $range_to = '';

    public function mount(): void
    {
        $this->range_from = now()->format('Y-m-d');
        $this->range_to = now()->addDay()->format('Y-m-d');
    }

    protected function utcDateRangeInvalidNotificationTitle(): string
    {
        return 'Отладка слотов';
    }

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'slotDebugWhatIs',
                [
                    'Те же расчёты слотов, что у публичного API: неделя, исключения, busy, буферы услуги.',
                    'Даты в UTC.',
                    '',
                    'Нужна услуга с записью и доступность по ресурсу; подсказки на форме ниже.',
                ],
                'Об отладке слотов',
            ),
        ];
    }

    public function bookableServicesIndexUrl(): string
    {
        return BookableServiceResource::getUrl();
    }

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

        return SchedulingAdminNavigationPrerequisites::tenantHasBookableServices(currentTenant());
    }

    /** @return Collection<int, BookableService> */
    #[Computed]
    public function tenantBookableServices(): Collection
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return collect();
        }

        return BookableService::query()
            ->where('scheduling_scope', SchedulingScope::Tenant)
            ->where('tenant_id', $tenant->id)
            ->orderBy('title')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function debugSlots(): array
    {
        if ($this->bookable_service_id === null) {
            return [];
        }

        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }

        $service = BookableService::query()
            ->where('scheduling_scope', SchedulingScope::Tenant)
            ->where('tenant_id', $tenant->id)
            ->whereKey($this->bookable_service_id)
            ->first();

        if ($service === null) {
            return [];
        }

        $range = $this->parseUtcDateRangeOrNull();
        if ($range === null) {
            return [];
        }
        [$from, $to] = $range;

        return app(SlotEngineService::class)->slotsForBookableService($service, $from, $to);
    }
}
