<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CrmRequestActivity extends Model
{
    public const TYPE_INBOUND_RECEIVED = 'inbound_received';

    public const TYPE_STATUS_CHANGED = 'status_changed';

    /** Operator comment / internal note added (stored in crm_request_notes). */
    public const TYPE_NOTE_ADDED = 'note_added';

    public const TYPE_MAIL_QUEUED = 'mail_queued';

    public const TYPE_PRIORITY_CHANGED = 'priority_changed';

    public const TYPE_FOLLOW_UP_SET = 'follow_up_set';

    public const TYPE_SUMMARY_UPDATED = 'summary_updated';

    public const TYPE_ASSIGNED = 'assigned';

    public $timestamps = false;

    protected $fillable = [
        'crm_request_id',
        'type',
        'meta',
        'actor_user_id',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->created_at = $model->created_at ?? now();
        });
    }

    public function crmRequest(): BelongsTo
    {
        return $this->belongsTo(CrmRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_INBOUND_RECEIVED => 'Входящая заявка',
            self::TYPE_STATUS_CHANGED => 'Смена статуса',
            self::TYPE_NOTE_ADDED => 'Комментарий оператора',
            self::TYPE_MAIL_QUEUED => 'Письмо в очереди',
            self::TYPE_PRIORITY_CHANGED => 'Приоритет',
            self::TYPE_FOLLOW_UP_SET => 'Напоминание / follow-up',
            self::TYPE_SUMMARY_UPDATED => 'Внутреннее резюме',
            self::TYPE_ASSIGNED => 'Ответственный',
            default => $type,
        };
    }

    /**
     * Short human-readable line for timeline (avoid raw JSON).
     */
    public function summaryLine(): string
    {
        $meta = $this->meta ?? [];

        return match ($this->type) {
            self::TYPE_INBOUND_RECEIVED => isset($meta['request_type'])
                ? 'Тип: '.$meta['request_type']
                : 'Входящая заявка',
            self::TYPE_STATUS_CHANGED => sprintf(
                '%s → %s',
                self::formatStatusLabel($meta['old'] ?? null),
                self::formatStatusLabel($meta['new'] ?? null),
            ),
            self::TYPE_NOTE_ADDED => isset($meta['preview']) && is_string($meta['preview'])
                ? $meta['preview']
                : 'Добавлен комментарий',
            self::TYPE_MAIL_QUEUED => isset($meta['recipients_count'])
                ? 'Получателей: '.$meta['recipients_count']
                : 'Письмо поставлено в очередь',
            self::TYPE_PRIORITY_CHANGED => sprintf(
                '%s → %s',
                self::formatPriorityLabel($meta['old'] ?? null),
                self::formatPriorityLabel($meta['new'] ?? null),
            ),
            self::TYPE_FOLLOW_UP_SET => self::formatFollowUpSummaryLine($meta),
            self::TYPE_SUMMARY_UPDATED => self::formatSummaryUpdatedLine($meta),
            self::TYPE_ASSIGNED => self::formatAssignedSummaryLine($meta),
            default => '',
        };
    }

    private static function formatStatusLabel(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '—';
        }

        $labels = CrmRequest::statusLabels();

        return $labels[$value] ?? $value;
    }

    private static function formatPriorityLabel(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '—';
        }

        $labels = CrmRequest::priorityLabels();

        return $labels[$value] ?? $value;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function formatFollowUpSummaryLine(array $meta): string
    {
        $at = $meta['at'] ?? null;
        if (! is_string($at) || $at === '') {
            $oldLabel = self::formatIsoDateTimeForSummary($meta['old'] ?? null);

            return $oldLabel !== null
                ? 'Напоминание снято (было: '.$oldLabel.')'
                : 'Напоминание снято';
        }

        try {
            return 'Следующий контакт: '.Carbon::parse($at)->format('d.m.Y H:i');
        } catch (\Throwable) {
            return 'Дата: '.$at;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function formatSummaryUpdatedLine(array $meta): string
    {
        if (! empty($meta['cleared'])) {
            return 'Резюме очищено';
        }

        $preview = isset($meta['preview']) && is_string($meta['preview']) && $meta['preview'] !== ''
            ? $meta['preview']
            : null;

        if ($preview !== null) {
            $verb = ! empty($meta['first']) ? 'Резюме добавлено' : 'Резюме обновлено';

            return $verb.' — '.$preview;
        }

        return 'Резюме обновлено';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function formatAssignedSummaryLine(array $meta): string
    {
        if (isset($meta['old_name'], $meta['new_name']) && is_string($meta['old_name']) && is_string($meta['new_name'])) {
            $old = $meta['old_name'] !== '' ? $meta['old_name'] : '—';
            $new = $meta['new_name'] !== '' ? $meta['new_name'] : '—';

            return $old.' → '.$new;
        }

        $oldId = $meta['old_user_id'] ?? null;
        $newId = $meta['new_user_id'] ?? null;
        if (($oldId === null || $oldId === '') && ($newId === null || $newId === '') && isset($meta['user_id'])) {
            $newId = $meta['user_id'];
        }

        $oldLabel = self::resolveAssigneeDisplayLabel($oldId);
        $newLabel = self::resolveAssigneeDisplayLabel($newId);

        if ($oldLabel === null && $newLabel === null) {
            return 'Ответственный изменён';
        }

        return ($oldLabel ?? '—').' → '.($newLabel ?? '—');
    }

    private static function resolveAssigneeDisplayLabel(mixed $userId): ?string
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        if (is_string($userId) && ! ctype_digit($userId)) {
            return null;
        }

        if (! is_int($userId) && ! (is_string($userId) && ctype_digit($userId))) {
            return null;
        }

        $id = (int) $userId;
        $name = User::query()->whereKey($id)->value('name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return '#'.$id;
    }

    private static function formatIsoDateTimeForSummary(mixed $iso): ?string
    {
        if (! is_string($iso) || $iso === '') {
            return null;
        }

        try {
            return Carbon::parse($iso)->format('d.m.Y H:i');
        } catch (\Throwable) {
            return Str::limit($iso, 32);
        }
    }
}
