<?php

namespace App\Filament\Tenant\Pages;

use App\Models\BookableService;
use App\Scheduling\Enums\SchedulingScope;
use App\Scheduling\SlotEngineService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use UnitEnum;

class SlotDebugPage extends Page
{
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

    public static function canAccess(): bool
    {
        $tenant = currentTenant();

        return $tenant !== null
            && $tenant->scheduling_module_enabled
            && Gate::allows('manage_scheduling');
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

        $from = Carbon::parse($this->range_from.' 00:00:00', 'UTC');
        $to = Carbon::parse($this->range_to.' 23:59:59', 'UTC');

        return app(SlotEngineService::class)->slotsForBookableService($service, $from, $to);
    }
}
