<?php

namespace App\Filament\Tenant\Pages;

use App\Bookings\Calendar\BookingCalendarEventsService;
use App\Bookings\Calendar\BookingCalendarFiltersData;
use App\Bookings\Calendar\BookingCalendarRangeNormalizer;
use App\Bookings\Calendar\BookingStatusPresentation;
use App\Enums\BookingStatus;
use App\Filament\Tenant\Concerns\ResolvesDomainTermLabels;
use App\Filament\Tenant\Forms\ManualOperatorBookingForm;
use App\Filament\Tenant\Resources\BookableServiceResource;
use App\Filament\Tenant\Resources\CalendarConnectionResource;
use App\Filament\Tenant\Resources\SchedulingResourceResource;
use App\Filament\Tenant\Resources\TenantServiceProgramResource;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Motorcycle;
use App\Models\RentalUnit;
use App\Terminology\DomainTermKeys;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

class BookingCalendarPage extends Page
{
    use ResolvesDomainTermLabels;

    protected static ?string $slug = 'bookings/calendar';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 25;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected string $view = 'filament.tenant.pages.booking-calendar';

    #[Url(as: 'view', history: true)]
    public string $calView = 'month';

    #[Url(as: 'date', history: true, nullable: true)]
    public ?string $calDate = null;

    #[Url(as: 'rental_unit_id', history: true, nullable: true)]
    public ?int $rental_unit_id = null;

    #[Url(as: 'motorcycle_id', history: true, nullable: true)]
    public ?int $motorcycle_id = null;

    #[Url(as: 'booking_id', history: true, nullable: true)]
    public ?int $booking_id = null;

    #[Url(as: 'crm_request_id', history: true, nullable: true)]
    public ?int $crm_request_id = null;

    /** @var list<string> */
    public array $filterStatuses = [];

    public ?int $filterCategoryId = null;

    /** @var array<string, mixed>|null */
    public ?array $eventDetail = null;

    public static function getNavigationLabel(): string
    {
        return 'Календарь: '.static::domainTermLabel(DomainTermKeys::BOOKING_PLURAL, 'Бронирования');
    }

    public function getTitle(): string
    {
        return static::domainTermLabel(DomainTermKeys::BOOKING_PLURAL, 'Бронирования').' — календарь';
    }

    public static function canAccess(): bool
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return false;
        }

        return Gate::allows('viewAny', Booking::class);
    }

    public function mount(): void
    {
        $tenant = currentTenant();
        abort_if($tenant === null, 403);
        Gate::authorize('viewAny', Booking::class);

        $normalizer = app(BookingCalendarRangeNormalizer::class);
        $tz = $normalizer->tenantTimezone($tenant->timezone);
        $this->calDate = $normalizer->normalizeAnchorDate($this->calDate, $tz);

        if (! in_array($this->calView, ['month', 'week'], true)) {
            $this->calView = 'month';
        }

        // Тема expert_auto — записи занятий без «аренды единицы парка» в фильтрах календаря.
        if (($tenant->theme_key ?? '') === 'expert_auto') {
            $this->rental_unit_id = null;
        }
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (TenantServiceProgramResource::canAccess()) {
            $actions[] = Action::make('openTenantServicePrograms')
                ->label('Программы')
                ->icon('heroicon-o-academic-cap')
                ->color('gray')
                ->url(TenantServiceProgramResource::getUrl());
        }

        $actions[] = ManualOperatorBookingForm::standaloneBookingCreateAction(
            fillForm: fn (): array => $this->prefillManualBookingFromCalendar(),
            afterSubmit: function (): void {
                $this->refreshCalendarBrowserEvent();
            },
        );

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    private function prefillManualBookingFromCalendar(): array
    {
        $out = [];
        if ($this->motorcycle_id !== null) {
            $out['motorcycle_id'] = $this->motorcycle_id;
        }
        if ($this->rental_unit_id !== null) {
            $out['rental_unit_id'] = $this->rental_unit_id;
        }
        if (filled($this->calDate)) {
            $out['start_date'] = $this->calDate;
            $out['end_date'] = $this->calDate;
        }

        return $out;
    }

    /**
     * Called from FullCalendar when the visible range or view changes.
     */
    public function syncCalendarNav(string $view, string $dateYmd): void
    {
        $this->calView = $view === 'week' ? 'week' : 'month';
        $tenant = currentTenant();
        if ($tenant === null) {
            return;
        }
        $normalizer = app(BookingCalendarRangeNormalizer::class);
        $tz = $normalizer->tenantTimezone($tenant->timezone);
        $this->calDate = $normalizer->normalizeAnchorDate($dateYmd, $tz);
    }

    public function updatedRentalUnitId(): void
    {
        $this->refreshCalendarBrowserEvent();
    }

    public function updatedMotorcycleId(): void
    {
        $this->refreshCalendarBrowserEvent();
    }

    public function updatedFilterCategoryId(): void
    {
        $this->refreshCalendarBrowserEvent();
    }

    public function updatedFilterStatuses(): void
    {
        $this->refreshCalendarBrowserEvent();
    }

    public function updatedCalDate(): void
    {
        $this->pushCalendarGotoJs();
    }

    public function updatedCalView(): void
    {
        $this->pushCalendarGotoJs();
    }

    private function refreshCalendarBrowserEvent(): void
    {
        $this->js('window.__bookingCalRefetch?.()');
    }

    private function pushCalendarGotoJs(): void
    {
        $date = json_encode($this->calDate, JSON_THROW_ON_ERROR);
        $view = json_encode($this->calView, JSON_THROW_ON_ERROR);
        $this->js("window.__bookingCalGoto?.({$date}, {$view})");
    }

    /**
     * Быстрые ссылки с календаря: программы (expert_auto), затем модуль расписания при наличии прав.
     *
     * @return list<array{label: string, url: string}>
     */
    public function calendarContextNavLinks(): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }

        $links = [];

        if (TenantServiceProgramResource::canAccess()) {
            $links[] = ['label' => 'Программы', 'url' => TenantServiceProgramResource::getUrl()];
        }

        if ($tenant->scheduling_module_enabled && Gate::allows('manage_scheduling')) {
            $links[] = ['label' => 'Ресурсы расписания', 'url' => SchedulingResourceResource::getUrl()];
            $links[] = ['label' => 'Услуги (запись)', 'url' => BookableServiceResource::getUrl()];
            if ($tenant->calendar_integrations_enabled) {
                $links[] = ['label' => 'Календари (подключения)', 'url' => CalendarConnectionResource::getUrl()];
            }
        }

        return $links;
    }

    #[Computed]
    public function legendRentalOverlapDescription(): string
    {
        $unit = mb_strtolower(static::domainTermLabel(DomainTermKeys::FLEET_UNIT, 'единице парка'));

        return 'Пересечение по '.$unit;
    }

    private function shouldShowRentalUnitCalendarFilter(): bool
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return false;
        }

        if (($tenant->theme_key ?? '') === 'expert_auto') {
            return false;
        }

        return count($this->rentalUnitsForFilter) > 0;
    }

    /**
     * Filament Select / CheckboxList вместо нативных select: выпадающие списки в стиле панели (в т.ч. тёмная тема).
     */
    public function calendarFiltersForm(Schema $schema): Schema
    {
        $filterCells = [];

        if ($this->shouldShowRentalUnitCalendarFilter()) {
            $filterCells[] = Select::make('rental_unit_id')
                ->label(static::domainTermLabel(DomainTermKeys::FLEET_UNIT, 'Единица парка'))
                ->placeholder('Все')
                ->options(fn (): array => collect($this->rentalUnitsForFilter)
                    ->mapWithKeys(fn (array $row): array => [$row['id'] => $row['label']])
                    ->all())
                ->native(false)
                ->nullable()
                ->searchable(false)
                ->live(onBlur: false);
        }

        $filterCells[] = Select::make('motorcycle_id')
            ->label(static::domainTermLabel(DomainTermKeys::RESOURCE_PLURAL, 'Каталог').' (модель)')
            ->placeholder('Все')
            ->options(fn (): array => collect($this->motorcyclesForFilter)
                ->mapWithKeys(fn (array $row): array => [$row['id'] => $row['label']])
                ->all())
            ->native(false)
            ->nullable()
            ->searchable(false)
            ->live(onBlur: false);

        $filterCells[] = Select::make('filterCategoryId')
            ->label(static::domainTermLabel(DomainTermKeys::CATEGORY, 'Категория'))
            ->placeholder('Все')
            ->options(fn (): array => collect($this->categoriesForFilter)
                ->mapWithKeys(fn (array $row): array => [$row['id'] => $row['label']])
                ->all())
            ->native(false)
            ->nullable()
            ->searchable(false)
            ->live(onBlur: false);

        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->schema($filterCells),
                CheckboxList::make('filterStatuses')
                    ->label('Статусы (пусто = все занимающие)')
                    ->options(fn (): array => collect($this->occupyingStatusFilterOptions)
                        ->mapWithKeys(fn (array $o): array => [$o['value'] => $o['label']])
                        ->all())
                    ->columns(3)
                    ->columnSpanFull()
                    ->bulkToggleable(false)
                    ->live(onBlur: false),
            ]);
    }

    public function filtersDataObject(): BookingCalendarFiltersData
    {
        $tenant = currentTenant();
        abort_if($tenant === null, 403);

        return BookingCalendarFiltersData::make(
            tenant: $tenant,
            viewType: $this->calView,
            anchorDateYmd: (string) $this->calDate,
            rentalUnitId: $this->rental_unit_id,
            motorcycleId: $this->motorcycle_id,
            categoryId: $this->filterCategoryId,
            statusValues: $this->filterStatuses,
            highlightBookingId: $this->booking_id,
            crmRequestPrefilterId: $this->crm_request_id,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchEvents(string $start, string $end): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }
        Gate::authorize('viewAny', Booking::class);

        try {
            return app(BookingCalendarEventsService::class)->fetchEvents(
                $tenant,
                $this->filtersDataObject(),
                $start,
                $end,
            );
        } catch (ValidationException $e) {
            $message = $e->errors()['range'][0] ?? $e->getMessage() ?? 'Некорректный диапазон календаря.';

            Notification::make()
                ->title('Календарь бронирований')
                ->body($message)
                ->danger()
                ->send();

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    public function openEventDetail(array $detail): void
    {
        $this->eventDetail = $detail;
    }

    public function closeEventDetail(): void
    {
        $this->eventDetail = null;
    }

    public function tenantTimezoneForJs(): string
    {
        $tenant = currentTenant();

        return app(BookingCalendarRangeNormalizer::class)->tenantTimezone($tenant?->timezone);
    }

    public function initialFcView(): string
    {
        return $this->calView === 'week' ? 'timeGridWeek' : 'dayGridMonth';
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    #[Computed]
    public function rentalUnitsForFilter(): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }

        return RentalUnit::query()
            ->where('tenant_id', $tenant->id)
            ->with('motorcycle:id,name')
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(function (RentalUnit $u): array {
                $label = $u->motorcycle
                    ? sprintf('#%d — %s', $u->id, $u->motorcycle->name)
                    : sprintf('Единица #%d', $u->id);

                return ['id' => $u->id, 'label' => $label];
            })
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    #[Computed]
    public function motorcyclesForFilter(): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }

        return Motorcycle::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name'])
            ->map(fn (Motorcycle $m): array => ['id' => $m->id, 'label' => $m->name])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    #[Computed]
    public function categoriesForFilter(): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }

        return Category::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name'])
            ->map(fn (Category $c): array => ['id' => $c->id, 'label' => $c->name])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function occupyingStatusFilterOptions(): array
    {
        return array_map(
            static fn (BookingStatus $s): array => [
                'value' => $s->value,
                'label' => BookingStatusPresentation::label($s),
            ],
            Booking::occupyingStatuses(),
        );
    }
}
