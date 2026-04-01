<?php

namespace App\Product\CRM\BookingWorkspace;

use App\Enums\BookingStatus;
use App\Filament\Tenant\Resources\BookingResource;
use App\Filament\Tenant\Resources\MotorcycleResource;
use App\Models\AvailabilityCalendar;
use App\Models\Booking;
use App\Models\CrmRequest;
use App\Models\Lead;
use App\Models\Motorcycle;
use App\Models\RentalUnit;
use App\Services\BookingService;
use App\Services\TenantPublicBookingAvailabilityService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;

final class CrmRequestBookingWorkspaceAssembler
{
    private const TIMELINE_DAYS_BEFORE = 7;

    private const TIMELINE_DAYS_AFTER = 21;

    public function __construct(
        private readonly BookingService $bookingService,
        private readonly TenantPublicBookingAvailabilityService $tenantPublicBookingAvailabilityService,
    ) {}

    public function assemble(CrmRequest $crm): CrmRequestBookingWorkspaceData
    {
        if ($crm->tenant_id === null) {
            return $this->emptyForPlatformScope();
        }

        $warnings = [];
        $tenant = $crm->relationLoaded('tenant') ? $crm->tenant : $crm->tenant()->first();
        $tz = $this->resolveTimezone($tenant?->timezone);

        $leads = $crm->relationLoaded('leads')
            ? $crm->leads->sortByDesc(fn (Lead $l) => $l->id)->values()
            : $crm->leads()->orderByDesc('id')->get();

        $canonicalLead = $leads->first();
        if ($leads->count() > 1) {
            $warnings[] = sprintf(
                'Связано несколько записей Lead (%d). Для контекста бронирования используется запись с максимальным id (#%d).',
                $leads->count(),
                $canonicalLead?->id ?? 0
            );
        }

        $payload = is_array($crm->payload_json) ? $crm->payload_json : [];
        $payloadMotorcycleId = isset($payload['motorcycle_id']) ? (int) $payload['motorcycle_id'] : null;
        $payloadDateFrom = $this->parsePayloadDate($payload['rental_date_from'] ?? null);
        $payloadDateTo = $this->parsePayloadDate($payload['rental_date_to'] ?? null);

        $source = 'none';
        $motorcycleId = null;
        $startDate = null;
        $endDate = null;

        if ($canonicalLead !== null) {
            $source = 'lead';
            $motorcycleId = $canonicalLead->motorcycle_id;
            $startDate = $canonicalLead->rental_date_from;
            $endDate = $canonicalLead->rental_date_to;
            $this->appendPayloadDivergenceWarnings($warnings, $canonicalLead, $payloadMotorcycleId, $payloadDateFrom, $payloadDateTo);
        } else {
            if ($payloadMotorcycleId !== null || $payloadDateFrom !== null || $payloadDateTo !== null) {
                $source = 'payload';
                $motorcycleId = $payloadMotorcycleId;
                $startDate = $payloadDateFrom;
                $endDate = $payloadDateTo;
            }
        }

        $motorcycle = null;
        if ($motorcycleId !== null) {
            $motorcycle = Motorcycle::query()
                ->where('tenant_id', $crm->tenant_id)
                ->whereKey($motorcycleId)
                ->with('category')
                ->first();
            if ($motorcycle === null) {
                $warnings[] = sprintf('В данных указан motorcycle_id=%d, объект не найден в каталоге тенанта.', $motorcycleId);
                $motorcycleId = null;
            }
        }

        $adminMotorcycleUrl = $this->safeAdminUrl(fn () => $motorcycle !== null
            ? MotorcycleResource::getUrl('edit', ['record' => $motorcycle])
            : null);
        $adminBookingsIndexUrl = $this->safeAdminUrl(fn () => BookingResource::getUrl('index'));

        $requestedHumanRange = '';
        $requestedDurationLabel = '';
        if ($startDate !== null && $endDate !== null) {
            [$requestedHumanRange, $requestedDurationLabel] = $this->formatRequestedRange($startDate, $endDate, $tz);
        } elseif ($startDate !== null || $endDate !== null) {
            $requestedHumanRange = 'Указана только одна граница периода';
            $requestedDurationLabel = '—';
        }

        $hasBookingContext = $motorcycleId !== null || ($startDate !== null && $endDate !== null);
        $motorcycleTitle = $motorcycle?->name ?? '';
        $motorcycleImageUrl = $motorcycle?->cover_url;
        $motorcycleDescriptor = $this->buildMotorcycleDescriptor($motorcycle);
        $motorcycleStatusLabel = $motorcycle !== null ? (Motorcycle::statuses()[$motorcycle->status] ?? $motorcycle->status) : null;
        $priceLabel = $motorcycle !== null && $motorcycle->price_per_day
            ? number_format((int) $motorcycle->price_per_day, 0, ',', ' ').' ₽ / сутки'
            : null;

        $availabilityState = BookingWorkspaceAvailabilityState::Unknown;
        $timelineSegments = [];
        $conflictingCompact = [];
        $conflictsCount = 0;
        $nearestWindow = null;
        $timelineStartStr = null;
        $timelineEndStr = null;
        $timelineWindowHuman = '';
        $timelineAxisTicks = [];
        $insightLines = [];

        $showTimelinePanel = $motorcycleId !== null && $startDate !== null && $endDate !== null;
        $showInsightsPanel = $hasBookingContext;

        if ($motorcycleId === null) {
            $availabilityState = ($startDate !== null && $endDate !== null)
                ? BookingWorkspaceAvailabilityState::NoItem
                : ($startDate === null && $endDate === null
                    ? BookingWorkspaceAvailabilityState::NoDates
                    : BookingWorkspaceAvailabilityState::NoItem);
        } elseif ($startDate === null || $endDate === null) {
            $availabilityState = BookingWorkspaceAvailabilityState::NoDates;
        } else {
            $startStr = $startDate->toDateString();
            $endStr = $endDate->toDateString();
            $overlapRequested = $this->overlappingOccupyingBookings($crm->tenant_id, $motorcycleId, $startStr, $endStr);
            $conflictsCount = $overlapRequested->count();
            $available = $this->bookingService->isAvailableForMotorcycle($motorcycleId, $startStr, $endStr);

            if ($conflictsCount > 0) {
                $availabilityState = BookingWorkspaceAvailabilityState::Conflict;
            } elseif ($available) {
                $availabilityState = BookingWorkspaceAvailabilityState::Available;
            } else {
                $availabilityState = $this->detectBlockedVsUnknown($crm->tenant_id, $motorcycleId, $startStr, $endStr, $overlapRequested);
            }

            $winStart = $startDate->copy()->timezone($tz)->startOfDay()->subDays(self::TIMELINE_DAYS_BEFORE);
            $winEnd = $endDate->copy()->timezone($tz)->startOfDay()->addDays(self::TIMELINE_DAYS_AFTER);
            $timelineStartStr = $winStart->toDateString();
            $timelineEndStr = $winEnd->toDateString();
            $timelineWindowHuman = $this->formatTimelineWindowHuman($timelineStartStr, $timelineEndStr, $tz);

            $hints = $this->tenantPublicBookingAvailabilityService->motorcycleCalendarHints(
                (int) $crm->tenant_id,
                $motorcycleId,
                $timelineStartStr,
                $timelineEndStr,
                $startStr,
                $endStr,
                $crm->phone,
            );

            $nearestWindow = $this->nearestWindowFromHints($hints, $tz);
            $timelineSegments = $this->buildTimelineSegments(
                $crm->tenant_id,
                $motorcycleId,
                $winStart,
                $winEnd,
                $startDate,
                $endDate,
                $overlapRequested,
                $tz,
                $startStr,
                $endStr
            );

            $timelineAxisTicks = $this->buildTimelineAxisTicks($winStart, $winEnd, $tz);

            $conflictingCompact = $this->buildConflictingCompact($overlapRequested, $tz);

            $insightLines = $this->buildInsightLines($availabilityState, $conflictsCount, $nearestWindow, $hints, $endStr, $tz);
        }

        return new CrmRequestBookingWorkspaceData(
            hasBookingContext: $hasBookingContext,
            source: $source,
            motorcycleId: $motorcycleId,
            motorcycleTitle: $motorcycleTitle,
            motorcycleImageUrl: $motorcycleImageUrl,
            motorcycleDescriptor: $motorcycleDescriptor,
            motorcycleStatusLabel: $motorcycleStatusLabel,
            priceLabel: $priceLabel,
            requestedStartDate: $startDate?->toDateString(),
            requestedEndDate: $endDate?->toDateString(),
            requestedHumanRange: $requestedHumanRange,
            requestedDurationLabel: $requestedDurationLabel,
            availabilityState: $availabilityState,
            availabilityStateLabel: $availabilityState->label(),
            availabilitySummaryText: $availabilityState->summaryText(),
            availabilityBadgeTone: $availabilityState->badgeTone(),
            conflictsCount: $conflictsCount,
            nearestAvailableWindow: $nearestWindow,
            timelineWindowStart: $timelineStartStr,
            timelineWindowEnd: $timelineEndStr,
            timelineWindowHuman: $timelineWindowHuman,
            timelineSegments: $timelineSegments,
            timelineAxisTicks: $timelineAxisTicks,
            warnings: $warnings,
            conflictingBookingsCompact: $conflictingCompact,
            adminMotorcycleUrl: $adminMotorcycleUrl,
            adminBookingsIndexUrl: $adminBookingsIndexUrl,
            showTimelinePanel: $showTimelinePanel,
            showInsightsPanel: $showInsightsPanel,
            insightLines: $insightLines,
        );
    }

    private function emptyForPlatformScope(): CrmRequestBookingWorkspaceData
    {
        $s = BookingWorkspaceAvailabilityState::NoData;

        return new CrmRequestBookingWorkspaceData(
            hasBookingContext: false,
            source: 'none',
            motorcycleId: null,
            motorcycleTitle: '',
            motorcycleImageUrl: null,
            motorcycleDescriptor: '',
            motorcycleStatusLabel: null,
            priceLabel: null,
            requestedStartDate: null,
            requestedEndDate: null,
            requestedHumanRange: '',
            requestedDurationLabel: '',
            availabilityState: $s,
            availabilityStateLabel: $s->label(),
            availabilitySummaryText: $s->summaryText(),
            availabilityBadgeTone: $s->badgeTone(),
            conflictsCount: 0,
            nearestAvailableWindow: null,
            timelineWindowStart: null,
            timelineWindowEnd: null,
            timelineWindowHuman: '',
            timelineSegments: [],
            timelineAxisTicks: [],
            warnings: [],
            conflictingBookingsCompact: [],
            adminMotorcycleUrl: null,
            adminBookingsIndexUrl: null,
            showTimelinePanel: false,
            showInsightsPanel: false,
            insightLines: [],
        );
    }

    private function resolveTimezone(?string $tenantTz): string
    {
        if (is_string($tenantTz) && $tenantTz !== '') {
            return $tenantTz;
        }

        return (string) config('app.timezone', 'UTC');
    }

    private function parsePayloadDate(mixed $v): ?Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $v)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function appendPayloadDivergenceWarnings(
        array &$warnings,
        Lead $lead,
        ?int $payloadMotorcycleId,
        ?Carbon $payloadFrom,
        ?Carbon $payloadTo,
    ): void {
        if ($payloadMotorcycleId !== null && (int) $lead->motorcycle_id !== $payloadMotorcycleId) {
            $warnings[] = 'В payload указан другой motorcycle_id, чем в Lead; в интерфейсе используются данные Lead.';
        }
        $lf = $lead->rental_date_from?->toDateString();
        $lt = $lead->rental_date_to?->toDateString();
        $pf = $payloadFrom?->toDateString();
        $pt = $payloadTo?->toDateString();
        if (($pf !== null && $lf !== null && $pf !== $lf) || ($pt !== null && $lt !== null && $pt !== $lt)) {
            $warnings[] = 'Даты в payload отличаются от Lead; в интерфейсе используются даты Lead.';
        }
    }

    private function formatRequestedRange(Carbon $start, Carbon $end, string $tz): array
    {
        $s = $start->copy()->timezone($tz)->locale(app()->getLocale());
        $e = $end->copy()->timezone($tz)->locale(app()->getLocale());
        $human = $s->translatedFormat('j F Y').' → '.$e->translatedFormat('j F Y');
        $nights = (int) $s->diffInDays($e);
        $duration = $nights === 0 ? '1 сутки' : sprintf('%d %s', $nights, $this->pluralRu($nights, 'ночь', 'ночи', 'ночей'));

        return [$human, $duration];
    }

    private function pluralRu(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return $many;
        }
        if ($n1 > 1 && $n1 < 5) {
            return $few;
        }
        if ($n1 === 1) {
            return $one;
        }

        return $many;
    }

    private function buildMotorcycleDescriptor(?Motorcycle $m): string
    {
        if ($m === null) {
            return '';
        }
        $parts = array_filter([
            $m->category?->name,
            filled($m->slug) ? 'slug: '.$m->slug : null,
            filled($m->brand) || filled($m->model) ? trim($m->brand.' '.$m->model) : null,
        ]);

        return implode(' · ', $parts);
    }

    private function safeAdminUrl(callable $resolver): ?string
    {
        try {
            if (Filament::getCurrentPanel()?->getId() !== 'admin') {
                return null;
            }

            return $resolver();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, Booking>
     */
    private function overlappingOccupyingBookings(int $tenantId, int $motorcycleId, string $startDate, string $endDate): Collection
    {
        return Booking::query()
            ->where('tenant_id', $tenantId)
            ->where('motorcycle_id', $motorcycleId)
            ->whereIn('status', Booking::occupyingStatusValues())
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->orderBy('start_date')
            ->get();
    }

    /**
     * @param  Collection<int, Booking>  $overlapRequested
     */
    private function detectBlockedVsUnknown(int $tenantId, int $motorcycleId, string $startDate, string $endDate, Collection $overlapRequested): BookingWorkspaceAvailabilityState
    {
        if ($overlapRequested->isNotEmpty()) {
            return BookingWorkspaceAvailabilityState::Conflict;
        }

        $units = RentalUnit::query()
            ->where('motorcycle_id', $motorcycleId)
            ->where('status', 'active')
            ->get();

        if ($units->isEmpty()) {
            return BookingWorkspaceAvailabilityState::Unknown;
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        foreach ($units as $unit) {
            $manualBlocks = AvailabilityCalendar::query()
                ->where('rental_unit_id', $unit->id)
                ->where('status', 'blocked')
                ->where('ends_at', '>=', $start)
                ->where('starts_at', '<=', $end)
                ->exists();
            if ($manualBlocks) {
                return BookingWorkspaceAvailabilityState::Blocked;
            }
        }

        return BookingWorkspaceAvailabilityState::Unknown;
    }

    /**
     * @param  Collection<int, Booking>  $overlapRequested
     * @return list<BookingTimelineSegmentData>
     */
    private function buildTimelineSegments(
        int $tenantId,
        int $motorcycleId,
        Carbon $winStart,
        Carbon $winEnd,
        Carbon $reqStart,
        Carbon $reqEnd,
        Collection $overlapRequested,
        string $tz,
        string $reqStartStr,
        string $reqEndStr,
    ): array {
        $segments = [];
        $totalDays = max(1, (int) $winStart->diffInDays($winEnd->copy()->startOfDay()) + 1);

        $layout = function (Carbon $segStart, Carbon $segEnd) use ($winStart, $winEnd, $totalDays): array {
            $ws = $winStart->copy()->startOfDay();
            $we = $winEnd->copy()->startOfDay();
            $ss = $segStart->copy()->startOfDay()->max($ws);
            $se = $segEnd->copy()->startOfDay()->min($we);
            if ($se->lt($ss)) {
                return [0.0, 0.0];
            }
            $left = (int) $ws->diffInDays($ss);
            $span = (int) $ss->diffInDays($se) + 1;

            return [
                round($left / $totalDays * 100, 2),
                round($span / $totalDays * 100, 2),
            ];
        };

        [$l, $w] = $layout($reqStart, $reqEnd);
        $segments[] = new BookingTimelineSegmentData(
            type: BookingTimelineSegmentData::TYPE_REQUESTED,
            startDate: $reqStartStr,
            endDate: $reqEndStr,
            label: 'Запрошенный период',
            shortLabel: 'Запрос',
            relatedBookingId: null,
            statusLabel: 'Заявка',
            isConflicting: $overlapRequested->isNotEmpty(),
            tooltipLines: ['Период из CRM / Lead', $reqStartStr.' — '.$reqEndStr],
            leftPercent: $l,
            widthPercent: max(0.5, $w),
        );

        $bookingsInWindow = Booking::query()
            ->where('tenant_id', $tenantId)
            ->where('motorcycle_id', $motorcycleId)
            ->whereIn('status', Booking::occupyingStatusValues())
            ->where('start_date', '<=', $winEnd->toDateString())
            ->where('end_date', '>=', $winStart->toDateString())
            ->orderBy('start_date')
            ->get();

        foreach ($bookingsInWindow as $b) {
            $bStart = Carbon::parse($b->start_date->toDateString())->timezone($tz)->startOfDay();
            $bEnd = Carbon::parse($b->end_date->toDateString())->timezone($tz)->startOfDay();
            [$pl, $pw] = $layout($bStart, $bEnd);
            $status = $b->status instanceof BookingStatus ? $b->status : BookingStatus::from((string) $b->status);
            $type = $status === BookingStatus::CONFIRMED
                ? BookingTimelineSegmentData::TYPE_CONFIRMED
                : BookingTimelineSegmentData::TYPE_PENDING;
            $overlapsReq = $b->start_date->toDateString() <= $reqEndStr && $b->end_date->toDateString() >= $reqStartStr;
            $segments[] = new BookingTimelineSegmentData(
                type: $type,
                startDate: $b->start_date->toDateString(),
                endDate: $b->end_date->toDateString(),
                label: $b->booking_number.' · '.$this->bookingStatusLabel($status),
                shortLabel: $b->booking_number,
                relatedBookingId: $b->id,
                statusLabel: $this->bookingStatusLabel($status),
                isConflicting: $overlapsReq,
                tooltipLines: array_values(array_filter([
                    $b->booking_number,
                    $b->customer_name,
                    $b->start_date->toDateString().' — '.$b->end_date->toDateString(),
                    $this->bookingStatusLabel($status),
                ])),
                leftPercent: $pl,
                widthPercent: max(0.5, $pw),
            );
        }

        $unitIds = RentalUnit::query()
            ->where('motorcycle_id', $motorcycleId)
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        if ($unitIds !== []) {
            $blocks = AvailabilityCalendar::query()
                ->whereIn('rental_unit_id', $unitIds)
                ->where('status', 'blocked')
                ->where('ends_at', '>=', $winStart->copy()->startOfDay())
                ->where('starts_at', '<=', $winEnd->copy()->endOfDay())
                ->get();

            foreach ($blocks as $block) {
                $bs = $block->starts_at->copy()->timezone($tz)->startOfDay();
                $be = $block->ends_at->copy()->timezone($tz)->startOfDay();
                [$bl, $bw] = $layout($bs, $be);
                $segments[] = new BookingTimelineSegmentData(
                    type: BookingTimelineSegmentData::TYPE_BLOCKED,
                    startDate: $bs->toDateString(),
                    endDate: $be->toDateString(),
                    label: 'Блокировка календаря',
                    shortLabel: 'Блок',
                    relatedBookingId: null,
                    statusLabel: 'blocked',
                    isConflicting: $bs->toDateString() <= $reqEndStr && $be->toDateString() >= $reqStartStr,
                    tooltipLines: array_values(array_filter([
                        'Ручная блокировка',
                        $block->reason,
                        $bs->toDateString().' — '.$be->toDateString(),
                    ])),
                    leftPercent: $bl,
                    widthPercent: max(0.3, $bw),
                );
            }
        }

        $today = Carbon::now($tz)->startOfDay();
        if ($today->gte($winStart->copy()->startOfDay()) && $today->lte($winEnd->copy()->startOfDay())) {
            [$tl] = $layout($today, $today);
            $segments[] = new BookingTimelineSegmentData(
                type: BookingTimelineSegmentData::TYPE_TODAY_MARKER,
                startDate: $today->toDateString(),
                endDate: $today->toDateString(),
                label: 'Сегодня',
                shortLabel: '|',
                relatedBookingId: null,
                statusLabel: '',
                isConflicting: false,
                tooltipLines: ['Сегодня: '.$today->toDateString()],
                leftPercent: max(0, $tl),
                widthPercent: 0.35,
            );
        }

        return $segments;
    }

    private function bookingStatusLabel(BookingStatus $state): string
    {
        return match ($state) {
            BookingStatus::DRAFT => 'Черновик',
            BookingStatus::PENDING => 'Ожидает',
            BookingStatus::AWAITING_PAYMENT => 'Ожидает оплаты',
            BookingStatus::CONFIRMED => 'Подтверждено',
            BookingStatus::CANCELLED => 'Отменено',
            BookingStatus::COMPLETED => 'Завершено',
            BookingStatus::NO_SHOW => 'Неявка',
        };
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     * @return list<ConflictingBookingCompactData>
     */
    private function buildConflictingCompact(Collection $bookings, string $tz): array
    {
        $out = [];
        foreach ($bookings as $b) {
            $viewUrl = $this->safeAdminUrl(fn () => BookingResource::getUrl('view', ['record' => $b]));
            $range = $b->start_date->toDateString().' — '.$b->end_date->toDateString();
            $status = $b->status instanceof BookingStatus ? $b->status : BookingStatus::from((string) $b->status);
            $out[] = new ConflictingBookingCompactData(
                id: $b->id,
                bookingNumber: $b->booking_number,
                customerLabel: filled($b->customer_name) ? (string) $b->customer_name : '—',
                dateRangeLabel: $range,
                statusLabel: $this->bookingStatusLabel($status),
                viewUrl: $viewUrl,
            );
        }

        return $out;
    }

    /**
     * @param  array{disabled_dates: array, is_range_available: ?bool, available_ranges: array, already_booked_by_phone: bool}  $hints
     */
    private function nearestWindowFromHints(array $hints, string $tz): ?NearestAvailableWindowData
    {
        $ranges = $hints['available_ranges'] ?? [];
        if (! is_array($ranges) || $ranges === []) {
            return null;
        }
        $first = $ranges[0] ?? null;
        if (! is_array($first)) {
            return null;
        }
        $from = $first['from'] ?? $first['start'] ?? null;
        $to = $first['to'] ?? $first['end'] ?? null;
        if ($from === null || $to === null) {
            return null;
        }
        try {
            $fs = Carbon::parse((string) $from)->timezone($tz)->startOfDay();
            $fe = Carbon::parse((string) $to)->timezone($tz)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
        $label = $fs->locale(app()->getLocale())->translatedFormat('j M').' — '.$fe->locale(app()->getLocale())->translatedFormat('j M Y');

        return new NearestAvailableWindowData(
            startDate: $fs->toDateString(),
            endDate: $fe->toDateString(),
            label: $label,
        );
    }

    /**
     * @param  array{available_ranges: array}  $hints
     * @return list<string>
     */
    private function buildInsightLines(
        BookingWorkspaceAvailabilityState $state,
        int $conflictsCount,
        ?NearestAvailableWindowData $nearest,
        array $hints,
        string $requestedEndStr,
        string $tz,
    ): array {
        $lines = [];
        if ($conflictsCount > 0) {
            $lines[] = 'Пересечений с действующими бронями: '.$conflictsCount;
        }
        if ($nearest !== null) {
            $lines[] = 'Ближайшее свободное окно (подсказка): '.$nearest->label;
        }
        $after = $this->firstAvailableRangeAfter($hints, $requestedEndStr, $tz);
        if ($after !== null) {
            $lines[] = 'Следующая доступность после запрошенного периода: '.$after;
        }
        if ($lines === [] && $state === BookingWorkspaceAvailabilityState::Available) {
            $lines[] = 'Объект свободен на выбранные даты — можно подтверждать сценарий бронирования вне этого экрана.';
        }

        return $lines;
    }

    private function formatTimelineWindowHuman(string $startStr, string $endStr, string $tz): string
    {
        try {
            $a = Carbon::parse($startStr)->timezone($tz)->startOfDay()->locale(app()->getLocale());
            $b = Carbon::parse($endStr)->timezone($tz)->startOfDay()->locale(app()->getLocale());
            $n = max(1, (int) $a->diffInDays($b) + 1);

            return $a->translatedFormat('j M Y').' — '.$b->translatedFormat('j M Y').' · '.$n.' '.$this->pluralRu($n, 'день', 'дня', 'дней');
        } catch (\Throwable) {
            return $startStr.' — '.$endStr;
        }
    }

    /**
     * Подписи дней по оси таймлайна (проценты согласованы с buildTimelineSegments).
     *
     * @return list<BookingTimelineAxisTickData>
     */
    private function buildTimelineAxisTicks(Carbon $winStart, Carbon $winEnd, string $tz): array
    {
        $ws = $winStart->copy()->timezone($tz)->startOfDay();
        $we = $winEnd->copy()->timezone($tz)->startOfDay();
        $totalDays = max(1, (int) $ws->diffInDays($we) + 1);
        $today = Carbon::now($tz)->startOfDay();
        $locale = app()->getLocale();
        $ticks = [];
        $d = $ws->copy();

        while ($d->lte($we)) {
            $dayIndex = (int) $ws->diffInDays($d);
            $leftPercent = round($dayIndex / $totalDays * 100, 2);
            $isFirst = $d->equalTo($ws);
            $isLast = $d->equalTo($we);
            $isMonday = (int) $d->format('N') === 1;
            $isToday = $d->equalTo($today);
            $show = $totalDays <= 14 || $isFirst || $isLast || $isMonday || $isToday;
            if ($show) {
                $isMajor = $isFirst || $isLast || $isMonday || $isToday || $totalDays <= 7;
                $label = ($isMajor || $totalDays <= 10)
                    ? $d->copy()->locale($locale)->translatedFormat('D j')
                    : $d->copy()->locale($locale)->translatedFormat('j');
                $ticks[] = new BookingTimelineAxisTickData(
                    leftPercent: $leftPercent,
                    label: $label,
                    isoDate: $d->toDateString(),
                    isMajor: $isMajor,
                );
            }
            $d->addDay();
        }

        return $ticks;
    }

    private function firstAvailableRangeAfter(array $hints, string $requestedEndStr, string $tz): ?string
    {
        $ranges = $hints['available_ranges'] ?? [];
        if (! is_array($ranges)) {
            return null;
        }
        try {
            $endReq = Carbon::parse($requestedEndStr)->timezone($tz)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
        foreach ($ranges as $r) {
            if (! is_array($r)) {
                continue;
            }
            $from = $r['from'] ?? $r['start'] ?? null;
            if ($from === null) {
                continue;
            }
            try {
                $f = Carbon::parse((string) $from)->timezone($tz)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
            if ($f->gt($endReq)) {
                $to = $r['to'] ?? $r['end'] ?? $from;
                try {
                    $t = Carbon::parse((string) $to)->timezone($tz)->startOfDay();
                } catch (\Throwable) {
                    $t = $f;
                }

                return $f->locale(app()->getLocale())->translatedFormat('j M').' — '.$t->locale(app()->getLocale())->translatedFormat('j M Y');
            }
        }

        return null;
    }
}
