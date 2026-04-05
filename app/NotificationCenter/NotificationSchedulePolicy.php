<?php

namespace App\NotificationCenter;

use App\Models\NotificationEvent;
use App\Models\NotificationSubscription;
use DateTimeZone;
use Illuminate\Support\Carbon;

/**
 * Quiet / working hours via schedule_json on the subscription.
 *
 * Expected shape (all keys optional; combine `days` and/or `from`–`to` as needed):
 * - timezone: string (IANA); invalid values fall back to safer resolution order below
 * - user_timezone_override: string — personal TZ; invalid → next candidate
 * - days: int[] ISO weekday 1–7 (Mon–Sun); omit or empty array = all days
 * - from, to: "HH:MM"
 *   - same-day window when from < to (inclusive by minute)
 *   - overnight when from > to (e.g. 22:00 → 06:00)
 *   - from === to: degenerate window treated as “whole day” (time check always passes)
 * - critical_bypass: bool — only {@see NotificationSeverity::Critical} bypasses the window (not High)
 *
 * Timezone resolution (first valid IANA wins):
 * 1) schedule.user_timezone_override
 * 2) schedule.timezone
 * 3) tenant.timezone (from {@see NotificationEvent::$tenant} when loaded, else relation)
 * 4) UTC
 *
 * Day filter and time window are independent: a non-empty `days` list always applies.
 * `from` / `to` restrict by clock only when both are present and parseable; otherwise there is no
 * time-of-day restriction (still subject to `days` when set).
 */
final class NotificationSchedulePolicy
{
    public function allowsImmediateDelivery(
        NotificationSubscription $subscription,
        NotificationEvent $event,
    ): bool {
        $schedule = $subscription->schedule_json;
        if (! is_array($schedule) || $schedule === []) {
            return true;
        }

        $timezone = $this->resolveTimezone($schedule, $event);
        $now = Carbon::now($timezone);

        $days = $schedule['days'] ?? null;
        if (is_array($days) && $days !== []) {
            $allowed = array_values(array_unique(array_map(intval(...), $days)));
            $dow = $now->dayOfWeekIso;
            if (! in_array($dow, $allowed, true)) {
                return $this->criticalBypassAllows($schedule, $event);
            }
        }

        $from = $schedule['from'] ?? null;
        $to = $schedule['to'] ?? null;
        if (! is_string($from) || ! is_string($to) || trim($from) === '' || trim($to) === '') {
            return true;
        }

        $fromM = $this->timeToMinutes($from);
        $toM = $this->timeToMinutes($to);
        if ($fromM === null || $toM === null) {
            return true;
        }

        $cur = $now->hour * 60 + $now->minute;
        if ($this->isWithinTimeWindow($cur, $fromM, $toM)) {
            return true;
        }

        return $this->criticalBypassAllows($schedule, $event);
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    private function criticalBypassAllows(array $schedule, NotificationEvent $event): bool
    {
        if (! ($schedule['critical_bypass'] ?? false)) {
            return false;
        }

        $sev = NotificationSeverity::tryFromString($event->severity);

        return $sev === NotificationSeverity::Critical;
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    private function resolveTimezone(array $schedule, NotificationEvent $event): string
    {
        $candidates = [];

        if (isset($schedule['user_timezone_override']) && is_string($schedule['user_timezone_override']) && trim($schedule['user_timezone_override']) !== '') {
            $candidates[] = trim($schedule['user_timezone_override']);
        }

        if (isset($schedule['timezone']) && is_string($schedule['timezone']) && trim($schedule['timezone']) !== '') {
            $candidates[] = trim($schedule['timezone']);
        }

        foreach ($candidates as $raw) {
            $safe = $this->safeTimezoneIdentifier($raw);
            if ($safe !== null) {
                return $safe;
            }
        }

        $tenant = $event->tenant;
        $tz = $tenant?->timezone;
        if (is_string($tz) && trim($tz) !== '') {
            $safe = $this->safeTimezoneIdentifier(trim($tz));
            if ($safe !== null) {
                return $safe;
            }
        }

        return 'UTC';
    }

    private function safeTimezoneIdentifier(string $identifier): ?string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        try {
            new DateTimeZone($identifier);

            return $identifier;
        } catch (\Exception) {
            return null;
        }
    }

    private function isWithinTimeWindow(int $cur, int $fromM, int $toM): bool
    {
        if ($fromM === $toM) {
            return true;
        }

        if ($fromM < $toM) {
            return $cur >= $fromM && $cur <= $toM;
        }

        return $cur >= $fromM || $cur <= $toM;
    }

    private function timeToMinutes(string $time): ?int
    {
        $time = trim($time);
        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return null;
        }

        $h = (int) $parts[0];
        $m = (int) $parts[1];
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;
        }

        return $h * 60 + $m;
    }
}
