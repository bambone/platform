<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Concerns;

use Carbon\Carbon;
use Filament\Notifications\Notification;

/**
 * Валидация пары дат (UTC) для отладочных страниц scheduling: уведомления только из updated*,
 * без сайд-эффектов в #[Computed].
 */
trait ValidatesUtcDateRangeForDebugTools
{
    private ?string $rangeNoticeFingerprint = null;

    public function updatedRangeFrom(): void
    {
        $this->validateUtcDateRangeAndNotify();
    }

    public function updatedRangeTo(): void
    {
        $this->validateUtcDateRangeAndNotify();
    }

    abstract protected function utcDateRangeInvalidNotificationTitle(): string;

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    protected function parseUtcDateRangeOrNull(): ?array
    {
        $fromYmd = trim($this->range_from);
        $toYmd = trim($this->range_to);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromYmd) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $toYmd)) {
            return null;
        }
        try {
            $from = Carbon::parse($fromYmd.' 00:00:00', 'UTC');
            $to = Carbon::parse($toYmd.' 23:59:59', 'UTC');
        } catch (\Throwable) {
            return null;
        }
        if ($to->lt($from)) {
            return null;
        }

        return [$from, $to];
    }

    private function validateUtcDateRangeAndNotify(): void
    {
        $fromYmd = trim($this->range_from);
        $toYmd = trim($this->range_to);
        if ($fromYmd === '' || $toYmd === '') {
            $this->rangeNoticeFingerprint = null;

            return;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromYmd) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $toYmd)) {
            $this->rangeNoticeFingerprint = null;

            return;
        }
        try {
            $from = Carbon::parse($fromYmd.' 00:00:00', 'UTC');
            $to = Carbon::parse($toYmd.' 23:59:59', 'UTC');
        } catch (\Throwable) {
            $this->sendRangeNoticeOnce(
                'parse:'.$fromYmd.'|'.$toYmd,
                'Некорректный диапазон дат. Укажите даты в формате ГГГГ-ММ-ДД.',
            );

            return;
        }
        if ($to->lt($from)) {
            $this->sendRangeNoticeOnce(
                'order:'.$fromYmd.'|'.$toYmd,
                'Дата «по» не может быть раньше «с» (UTC).',
            );

            return;
        }
        $this->rangeNoticeFingerprint = null;
    }

    private function sendRangeNoticeOnce(string $fingerprint, string $body): void
    {
        if ($this->rangeNoticeFingerprint === $fingerprint) {
            return;
        }
        $this->rangeNoticeFingerprint = $fingerprint;
        Notification::make()
            ->title($this->utcDateRangeInvalidNotificationTitle())
            ->body($body)
            ->danger()
            ->send();
    }
}
