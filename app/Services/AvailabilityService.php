<?php

namespace App\Services;

use App\Models\AvailabilityCalendar;
use App\Models\Booking;
use App\Models\RentalUnit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Check if a rental unit is available for the given date range.
     */
    public function isAvailable(RentalUnit $rentalUnit, Carbon $start, Carbon $end, ?int $excludeBookingId = null): bool
    {
        $conflicts = $this->getConflicts($rentalUnit, $start, $end, $excludeBookingId);

        return $conflicts->isEmpty();
    }

    /**
     * Get conflicting availability entries for the given range.
     */
    public function getConflicts(RentalUnit $rentalUnit, Carbon $start, Carbon $end, ?int $excludeBookingId = null): Collection
    {
        return AvailabilityCalendar::query()
            ->where('rental_unit_id', $rentalUnit->id)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('starts_at', [$start, $end])
                    ->orWhereBetween('ends_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('starts_at', '<=', $start)
                            ->where('ends_at', '>=', $end);
                    });
            })
            ->whereIn('status', ['blocked', 'booked'])
            ->when($excludeBookingId, fn ($q) => $q->where('booking_id', '!=', $excludeBookingId)->orWhereNull('booking_id'))
            ->get();
    }

    /**
     * Block slots for a booking.
     */
    public function blockForBooking(Booking $booking): void
    {
        if (! $booking->rental_unit_id || ! $booking->start_at || ! $booking->end_at) {
            return;
        }

        AvailabilityCalendar::create([
            'rental_unit_id' => $booking->rental_unit_id,
            'starts_at' => $booking->start_at,
            'ends_at' => $booking->end_at,
            'status' => 'booked',
            'source' => 'booking',
            'booking_id' => $booking->id,
        ]);
    }

    /**
     * Remove blocks when a booking is cancelled.
     */
    public function unblockForBooking(Booking $booking): void
    {
        AvailabilityCalendar::query()
            ->where('booking_id', $booking->id)
            ->delete();
    }

    /**
     * Create manual block.
     */
    public function createBlock(RentalUnit $rentalUnit, Carbon $start, Carbon $end, string $reason = '', ?int $userId = null): AvailabilityCalendar
    {
        return AvailabilityCalendar::create([
            'rental_unit_id' => $rentalUnit->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => 'blocked',
            'source' => 'manual',
            'reason' => $reason,
            'created_by' => $userId,
        ]);
    }

    /**
     * Get available date ranges for a rental unit within a period.
     */
    public function getAvailableRanges(RentalUnit $rentalUnit, Carbon $from, Carbon $to, int $minDays = 1): array
    {
        $blocks = AvailabilityCalendar::query()
            ->where('rental_unit_id', $rentalUnit->id)
            ->whereIn('status', ['blocked', 'booked'])
            ->where('ends_at', '>=', $from)
            ->where('starts_at', '<=', $to)
            ->orderBy('starts_at')
            ->get();

        $period = CarbonPeriod::create($from, $to);
        $available = [];
        $currentStart = null;

        foreach ($period as $date) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            $isBlocked = $blocks->contains(fn ($b) => $dayStart->lte($b->ends_at) && $dayEnd->gte($b->starts_at));

            if (! $isBlocked) {
                if ($currentStart === null) {
                    $currentStart = $date->copy();
                }
            } else {
                if ($currentStart !== null) {
                    $rangeEnd = $date->copy()->subDay();
                    if ($currentStart->diffInDays($rangeEnd) + 1 >= $minDays) {
                        $available[] = ['start' => $currentStart, 'end' => $rangeEnd];
                    }
                    $currentStart = null;
                }
            }
        }

        if ($currentStart !== null && $currentStart->diffInDays($to) + 1 >= $minDays) {
            $available[] = ['start' => $currentStart, 'end' => $to->copy()];
        }

        return $available;
    }
}
