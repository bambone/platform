<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarSubscription extends Model
{
    protected $table = 'calendar_subscriptions';

    public static function canonicalizeExternalCalendarId(?string $value): string
    {
        return trim((string) $value);
    }

    protected $fillable = [
        'calendar_connection_id',
        'external_calendar_id',
        'title',
        'timezone',
        'color',
        'use_for_busy',
        'use_for_write',
        'sync_token',
        'external_etag',
        'is_primary',
        'is_active',
        'last_successful_sync_at',
        'stale_after_seconds',
    ];

    protected function casts(): array
    {
        return [
            'use_for_busy' => 'boolean',
            'use_for_write' => 'boolean',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'last_successful_sync_at' => 'datetime',
        ];
    }

    /**
     * Вычисляемый дедлайн свежести busy по подписке (если заданы sync + stale_after_seconds).
     */
    public function getSyncFreshUntilAttribute(): ?Carbon
    {
        if ($this->last_successful_sync_at === null || $this->stale_after_seconds === null || $this->stale_after_seconds <= 0) {
            return null;
        }

        return $this->last_successful_sync_at->copy()->addSeconds((int) $this->stale_after_seconds);
    }

    public function calendarConnection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class, 'calendar_connection_id');
    }

    public function occupancyMappings(): HasMany
    {
        return $this->hasMany(CalendarOccupancyMapping::class, 'calendar_subscription_id');
    }

    protected static function booted(): void
    {
        static::saving(function (CalendarSubscription $model): void {
            $model->external_calendar_id = self::canonicalizeExternalCalendarId($model->external_calendar_id);
        });

        static::saved(function (CalendarSubscription $model): void {
            if (! $model->use_for_write) {
                return;
            }

            static::query()
                ->where('calendar_connection_id', $model->calendar_connection_id)
                ->whereKeyNot($model->getKey())
                ->update(['use_for_write' => false]);
        });
    }
}
