<?php

namespace App\Services;

use App\Models\AvailabilityCalendar;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\Motorcycle;
use App\Models\RentalUnit;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

final class TenantPublicBookingAvailabilityService
{
    public const MAX_CATALOG_MOTORCYCLE_IDS = 50;

    public const MAX_HINTS_WINDOW_DAYS = 90;

    public const MAX_SUGGESTED_RANGES = 5;

    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly BookingService $bookingService,
    ) {}

    public function catalogAvailabilityForMotorcycles(int $tenantId, array $motorcycleIds, string $startDate, string $endDate): array
    {
        $motorcycleIds = array_values(array_unique(array_map('intval', $motorcycleIds)));
        $motorcycleIds = array_slice($motorcycleIds, 0, self::MAX_CATALOG_MOTORCYCLE_IDS);

        $validIds = Motorcycle::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $motorcycleIds)
            ->pluck('id')
            ->all();

        if ($validIds === []) {
            return [];
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $result = [];
        foreach ($validIds as $id) {
            $result[$id] = true;
        }

        $unitsByMoto = RentalUnit::query()
            ->whereIn('motorcycle_id', $validIds)
            ->where('status', 'active')
            ->get()
            ->groupBy('motorcycle_id');

        $withoutUnits = [];
        $withUnits = [];
        foreach ($validIds as $mid) {
            $group = $unitsByMoto->get($mid, collect());
            if ($group->isEmpty()) {
                $withoutUnits[] = $mid;
            } else {
                $withUnits[$mid] = $group;
            }
        }

        if ($withoutUnits !== []) {
            $busyIds = Booking::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('motorcycle_id', $withoutUnits)
                ->whereIn('status', Booking::occupyingStatusValues())
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->pluck('motorcycle_id')
                ->unique()
                ->all();

            foreach ($busyIds as $busyId) {
                $result[(int) $busyId] = false;
            }
        }

        $allUnitIds = collect($withUnits)
            ->flatten()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($allUnitIds === []) {
            return $result;
        }

        $blocksByUnit = AvailabilityCalendar::query()
            ->whereIn('rental_unit_id', $allUnitIds)
            ->whereIn('status', ['blocked', 'booked'])
            ->where('ends_at', '>=', $start)
            ->where('starts_at', '<=', $end)
            ->get()
            ->groupBy('rental_unit_id');

        foreach ($withUnits as $mid => $units) {
            $anyFree = false;
            foreach ($units as $unit) {
                if (! $this->unitHasCalendarConflict((int) $unit->id, $start, $end, $blocksByUnit)) {
                    $anyFree = true;
                    break;
                }
            }
            $result[(int) $mid] = $anyFree;
        }

        return $result;
    }

    public function motorcycleCalendarHints(
        int $tenantId,
        int $motorcycleId,
        string $rangeFrom,
        string $rangeTo,
        ?string $selectedStart,
        ?string $selectedEnd,
        ?string $phone,
    ): array {
        $exists = Motorcycle::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($motorcycleId)
            ->exists();

        if (! $exists) {
            return [
                'disabled_dates' => [],
                'is_range_available' => null,
                'available_ranges' => [],
                'already_booked_by_phone' => false,
                'pending_request_dates' => [],
                'pending_requests_on_selected_range' => false,
            ];
        }

        $fromDay = Carbon::parse($rangeFrom)->startOfDay();
        $toDay = Carbon::parse($rangeTo)->startOfDay();

        $units = RentalUnit::query()
            ->where('motorcycle_id', $motorcycleId)
            ->where('status', 'active')
            ->get();

        $bookingsWindow = collect();
        $blocksByUnit = collect();

        if ($units->isEmpty()) {
            $bookingsWindow = Booking::query()
                ->where('tenant_id', $tenantId)
                ->where('motorcycle_id', $motorcycleId)
                ->whereIn('status', Booking::occupyingStatusValues())
                ->where('start_date', '<=', $toDay->toDateString())
                ->where('end_date', '>=', $fromDay->toDateString())
                ->get();
        } else {
            $unitIds = $units->pluck('id')->map(fn ($id) => (int) $id)->all();
            $blocksByUnit = AvailabilityCalendar::query()
                ->whereIn('rental_unit_id', $unitIds)
                ->whereIn('status', ['blocked', 'booked'])
                ->where('ends_at', '>=', $fromDay->copy()->startOfDay())
                ->where('starts_at', '<=', $toDay->copy()->endOfDay())
                ->get()
                ->groupBy('rental_unit_id');
        }

        $disabledDates = [];
        $period = CarbonPeriod::create($fromDay->toDateString(), $toDay->toDateString());
        foreach ($period as $date) {
            $dStart = $date->copy()->startOfDay();
            $dEnd = $date->copy()->endOfDay();
            $free = $units->isEmpty()
                ? $this->motorcycleFreeBookingsOnly($bookingsWindow, $dStart, $dEnd)
                : $this->motorcycleFreeWithUnits($units, $blocksByUnit, $dStart, $dEnd);

            if (! $free) {
                $disabledDates[] = $date->toDateString();
            }
        }

        $isRangeAvailable = null;
        if ($selectedStart !== null && $selectedStart !== '' && $selectedEnd !== null && $selectedEnd !== '') {
            $selStart = Carbon::parse($selectedStart);
            $selEnd = Carbon::parse($selectedEnd);
            if ($selEnd->gte($selStart)) {
                $isRangeAvailable = $this->bookingService->isAvailableForMotorcycle(
                    $motorcycleId,
                    $selStart->toDateString(),
                    $selEnd->toDateString(),
                );
            }
        }

        $minDays = 1;
        if ($selectedStart !== null && $selectedStart !== '' && $selectedEnd !== null && $selectedEnd !== '') {
            $s = Carbon::parse($selectedStart)->startOfDay();
            $e = Carbon::parse($selectedEnd)->startOfDay();
            if ($e->gte($s)) {
                $minDays = (int) $s->diffInDays($e) + 1;
            }
        }

        $availableRanges = $units->isEmpty()
            ? $this->availableRangesFromBookingsOnly($bookingsWindow, $fromDay, $toDay, $minDays)
            : $this->availableRangesFromFirstRentalUnit($units->first(), $fromDay, $toDay, $minDays);

        $availableRanges = $this->limitSuggestedRanges($availableRanges, $fromDay);

        $alreadyBooked = $this->alreadyBookedByPhone(
            $tenantId,
            $motorcycleId,
            $phone,
            $selectedStart,
            $selectedEnd,
        );

        $pendingRequestDates = $this->pendingLeadDateStringsInRange($tenantId, $motorcycleId, $fromDay, $toDay);
        sort($pendingRequestDates);

        $pendingOnSelected = $this->pendingLeadsOverlapSelectedRange(
            $tenantId,
            $motorcycleId,
            $selectedStart,
            $selectedEnd,
        );

        return [
            'disabled_dates' => $disabledDates,
            'is_range_available' => $isRangeAvailable,
            'available_ranges' => $availableRanges,
            'already_booked_by_phone' => $alreadyBooked,
            'pending_request_dates' => $pendingRequestDates,
            'pending_requests_on_selected_range' => $pendingOnSelected,
        ];
    }

    /**
     * Дни в окне, где по этой карточке парка уже есть заявки (Lead) в статусе «ожидают обработки».
     * Не блокируем выбор в календаре — только подсветка и текст в UI.
     *
     * @return list<string> Y-m-d
     */
    private function pendingLeadDateStringsInRange(int $tenantId, int $motorcycleId, Carbon $fromDay, Carbon $toDay): array
    {
        $leads = Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('motorcycle_id', $motorcycleId)
            ->whereIn('status', ['new', 'in_progress'])
            ->whereNotNull('rental_date_from')
            ->whereNotNull('rental_date_to')
            ->where('rental_date_from', '<=', $toDay->toDateString())
            ->where('rental_date_to', '>=', $fromDay->toDateString())
            ->get(['rental_date_from', 'rental_date_to']);

        $out = [];
        foreach ($leads as $lead) {
            $lf = Carbon::parse($lead->rental_date_from)->startOfDay();
            $lt = Carbon::parse($lead->rental_date_to)->startOfDay();
            $clipStart = $lf->greaterThan($fromDay) ? $lf : $fromDay->copy();
            $clipEnd = $lt->lessThan($toDay) ? $lt : $toDay->copy();
            if ($clipEnd->lt($clipStart)) {
                continue;
            }
            foreach (CarbonPeriod::create($clipStart->toDateString(), $clipEnd->toDateString()) as $d) {
                $out[] = $d->toDateString();
            }
        }

        return array_values(array_unique($out));
    }

    private function pendingLeadsOverlapSelectedRange(
        int $tenantId,
        int $motorcycleId,
        ?string $selectedStart,
        ?string $selectedEnd,
    ): bool {
        if ($selectedStart === null || $selectedStart === '' || $selectedEnd === null || $selectedEnd === '') {
            return false;
        }

        $selStart = Carbon::parse($selectedStart)->toDateString();
        $selEnd = Carbon::parse($selectedEnd)->toDateString();
        if (Carbon::parse($selectedEnd)->lt(Carbon::parse($selectedStart))) {
            return false;
        }

        return Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('motorcycle_id', $motorcycleId)
            ->whereIn('status', ['new', 'in_progress'])
            ->whereNotNull('rental_date_from')
            ->whereNotNull('rental_date_to')
            ->where('rental_date_from', '<=', $selEnd)
            ->where('rental_date_to', '>=', $selStart)
            ->exists();
    }

    private function unitHasCalendarConflict(int $rentalUnitId, Carbon $start, Carbon $end, Collection $blocksByUnit): bool
    {
        $unitBlocks = $blocksByUnit->get($rentalUnitId, collect());
        foreach ($unitBlocks as $b) {
            if ($start->lte($b->ends_at) && $end->gte($b->starts_at)) {
                return true;
            }
        }

        return false;
    }

    private function motorcycleFreeBookingsOnly(Collection $bookings, Carbon $start, Carbon $end): bool
    {
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        foreach ($bookings as $b) {
            if ($b->start_date->toDateString() <= $endStr && $b->end_date->toDateString() >= $startStr) {
                return false;
            }
        }

        return true;
    }

    private function motorcycleFreeWithUnits(Collection $units, Collection $blocksByUnit, Carbon $start, Carbon $end): bool
    {
        foreach ($units as $unit) {
            if (! $this->unitHasCalendarConflict((int) $unit->id, $start, $end, $blocksByUnit)) {
                return true;
            }
        }

        return false;
    }

    private function availableRangesFromBookingsOnly(Collection $bookings, Carbon $from, Carbon $to, int $minDays): array
    {
        $period = CarbonPeriod::create($from->toDateString(), $to->toDateString());
        $available = [];
        $currentStart = null;

        foreach ($period as $date) {
            $d = $date->toDateString();
            $blocked = false;
            foreach ($bookings as $b) {
                if ($b->start_date->toDateString() <= $d && $b->end_date->toDateString() >= $d) {
                    $blocked = true;
                    break;
                }
            }

            if (! $blocked) {
                if ($currentStart === null) {
                    $currentStart = $date->copy();
                }
            } else {
                if ($currentStart !== null) {
                    $rangeEnd = $date->copy()->subDay();
                    if ($currentStart->diffInDays($rangeEnd) + 1 >= $minDays) {
                        $available[] = [
                            'start' => $currentStart->toDateString(),
                            'end' => $rangeEnd->toDateString(),
                        ];
                    }
                    $currentStart = null;
                }
            }
        }

        if ($currentStart !== null && $currentStart->diffInDays($to) + 1 >= $minDays) {
            $available[] = [
                'start' => $currentStart->toDateString(),
                'end' => $to->toDateString(),
            ];
        }

        return $available;
    }

    private function availableRangesFromFirstRentalUnit(RentalUnit $unit, Carbon $from, Carbon $to, int $minDays): array
    {
        $ranges = $this->availabilityService->getAvailableRanges($unit, $from->copy(), $to->copy(), $minDays);
        $out = [];
        foreach ($ranges as $r) {
            $out[] = [
                'start' => $r['start']->toDateString(),
                'end' => $r['end']->toDateString(),
            ];
        }

        return $out;
    }

    private function limitSuggestedRanges(array $ranges, Carbon $rangeFrom): array
    {
        usort($ranges, function (array $a, array $b) use ($rangeFrom): int {
            $anchor = $rangeFrom->toDateString();
            $da = strcmp($a['start'], $anchor) >= 0 ? 0 : 1;
            $db = strcmp($b['start'], $anchor) >= 0 ? 0 : 1;
            if ($da !== $db) {
                return $da <=> $db;
            }

            return strcmp($a['start'], $b['start']);
        });

        return array_slice($ranges, 0, self::MAX_SUGGESTED_RANGES);
    }

    private function alreadyBookedByPhone(
        int $tenantId,
        int $motorcycleId,
        ?string $phone,
        ?string $selectedStart,
        ?string $selectedEnd,
    ): bool {
        if ($selectedStart === null || $selectedStart === '' || $selectedEnd === null || $selectedEnd === '') {
            return false;
        }

        $normalized = PhoneNormalizer::normalizeOrEmpty($phone);
        $digits = preg_replace('/\D/', '', $normalized) ?? '';
        if ($digits === '' || strlen($digits) < 10 || strlen($digits) > 15) {
            return false;
        }

        $variants = PhoneNormalizer::comparisonVariants($normalized);

        return Booking::query()
            ->where('tenant_id', $tenantId)
            ->where('motorcycle_id', $motorcycleId)
            ->whereIn('status', Booking::occupyingStatusValues())
            ->whereIn('phone_normalized', $variants)
            ->where('start_date', '<=', $selectedEnd)
            ->where('end_date', '>=', $selectedStart)
            ->exists();
    }
}
