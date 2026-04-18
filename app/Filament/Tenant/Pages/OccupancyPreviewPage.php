<?php

namespace App\Filament\Tenant\Pages;

use App\Models\SchedulingResource;
use App\Models\SchedulingTarget;
use App\Scheduling\Enums\SchedulingScope;
use App\Scheduling\Occupancy\SchedulingOccupancyPreviewService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use UnitEnum;

class OccupancyPreviewPage extends Page
{
    protected static ?string $navigationLabel = 'Превью занятости';

    protected static ?string $title = 'Превью занятости (internal + external)';

    protected static ?string $slug = 'scheduling/occupancy-preview';

    protected static string|UnitEnum|null $navigationGroup = 'SchedulingTools';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected string $view = 'filament.tenant.pages.occupancy-preview';

    public ?int $scheduling_resource_id = null;

    public ?int $scheduling_target_id = null;

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
     * @return array{internal: list<array{start: string, end: string}>, external: list<array{start: string, end: string, is_tentative: bool}>}
     */
    #[Computed]
    public function previewPayload(): array
    {
        $tenant = currentTenant();
        if ($tenant === null || $this->scheduling_resource_id === null) {
            return ['internal' => [], 'external' => []];
        }

        $resource = SchedulingResource::query()
            ->where('scheduling_scope', SchedulingScope::Tenant)
            ->where('tenant_id', $tenant->id)
            ->whereKey($this->scheduling_resource_id)
            ->first();

        if ($resource === null) {
            return ['internal' => [], 'external' => []];
        }

        $target = null;
        if ($this->scheduling_target_id !== null) {
            $target = SchedulingTarget::query()
                ->where('scheduling_scope', SchedulingScope::Tenant)
                ->where('tenant_id', $tenant->id)
                ->whereKey($this->scheduling_target_id)
                ->first();
        }

        $from = Carbon::parse($this->range_from.' 00:00:00', 'UTC');
        $to = Carbon::parse($this->range_to.' 23:59:59', 'UTC');

        return app(SchedulingOccupancyPreviewService::class)->previewForResource($tenant, $resource, $from, $to, $target);
    }
}
