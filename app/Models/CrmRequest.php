<?php

namespace App\Models;

use App\Product\CRM\Actions\CreateCrmRequestFromPublicForm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Aggregate root inbound CRM: notes, activity/timeline, mail hooks, assignment и state machine статуса
 * относятся к этой сущности, а не к Lead / platform_marketing_leads / Booking как к параллельному SoT.
 *
 * @see CreateCrmRequestFromPublicForm
 */
class CrmRequest extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_CONTACT_ATTEMPTED = 'contact_attempted';

    public const STATUS_AWAITING_REPLY = 'awaiting_reply';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ARCHIVED = 'archived';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'message',
        'request_type',
        'source',
        'channel',
        'pipeline',
        'status',
        'priority',
        'assigned_user_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'referrer',
        'landing_page',
        'ip',
        'user_agent',
        'payload_json',
        'last_activity_at',
        'closed_at',
        'next_follow_up_at',
        'first_viewed_at',
        'processed_at',
        'last_commented_at',
        'internal_summary',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'last_activity_at' => 'datetime',
        'closed_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'first_viewed_at' => 'datetime',
        'processed_at' => 'datetime',
        'last_commented_at' => 'datetime',
    ];

    /**
     * @return list<string>
     */
    public static function terminalStatusValues(): array
    {
        return [
            self::STATUS_CONVERTED,
            self::STATUS_REJECTED,
            self::STATUS_ARCHIVED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function canonicalStatusValues(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_IN_REVIEW,
            self::STATUS_CONTACT_ATTEMPTED,
            self::STATUS_AWAITING_REPLY,
            self::STATUS_QUALIFIED,
            self::STATUS_CONVERTED,
            self::STATUS_REJECTED,
            self::STATUS_ARCHIVED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function priorityValues(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmRequestActivity::class)->orderByDesc('created_at');
    }

    /**
     * Chronological order for workspace (oldest first).
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CrmRequestNote::class)->orderBy('created_at');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopeTerminalStatuses(Builder $query): void
    {
        $query->whereIn('status', self::terminalStatusValues());
    }

    public function scopeOpenStatuses(Builder $query): void
    {
        $query->whereNotIn('status', self::terminalStatusValues());
    }

    public function scopeNeedsFollowUp(Builder $query): void
    {
        $query
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', now())
            ->whereNotIn('status', self::terminalStatusValues());
    }

    public function scopeStale(Builder $query, int $hours = 24): void
    {
        $threshold = Carbon::now()->subHours($hours);
        $query
            ->whereNotIn('status', self::terminalStatusValues())
            ->where(function (Builder $q) use ($threshold): void {
                $q->where(function (Builder $inner) use ($threshold): void {
                    $inner->whereNotNull('last_activity_at')
                        ->where('last_activity_at', '<', $threshold);
                })->orWhere(function (Builder $inner) use ($threshold): void {
                    $inner->whereNull('last_activity_at')
                        ->where('updated_at', '<', $threshold);
                });
            });
    }

    public function isTerminalStatus(): bool
    {
        return in_array($this->status, self::terminalStatusValues(), true);
    }

    /**
     * Follow-up datetime is overdue and should surface in operator UI (not for terminal leads).
     */
    public function isFollowUpOverdue(): bool
    {
        if ($this->isTerminalStatus()) {
            return false;
        }

        return $this->next_follow_up_at !== null
            && $this->next_follow_up_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_NEW => 'Новая',
            self::STATUS_IN_REVIEW => 'На рассмотрении',
            self::STATUS_CONTACT_ATTEMPTED => 'Попытка контакта',
            self::STATUS_AWAITING_REPLY => 'Ждём ответ',
            self::STATUS_QUALIFIED => 'Квалифицирована',
            self::STATUS_CONVERTED => 'Конверсия',
            self::STATUS_REJECTED => 'Отклонена',
            self::STATUS_ARCHIVED => 'Архив',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function priorityLabels(): array
    {
        return [
            self::PRIORITY_LOW => 'Низкий',
            self::PRIORITY_NORMAL => 'Обычный',
            self::PRIORITY_HIGH => 'Высокий',
            self::PRIORITY_URGENT => 'Срочно',
        ];
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_NEW => 'info',
            self::STATUS_IN_REVIEW => 'warning',
            self::STATUS_CONTACT_ATTEMPTED => 'warning',
            self::STATUS_AWAITING_REPLY => 'warning',
            self::STATUS_QUALIFIED => 'success',
            self::STATUS_CONVERTED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_ARCHIVED => 'gray',
            default => 'gray',
        };
    }

    public static function priorityColor(?string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_LOW => 'gray',
            self::PRIORITY_NORMAL => 'gray',
            self::PRIORITY_HIGH => 'warning',
            self::PRIORITY_URGENT => 'danger',
            default => 'gray',
        };
    }
}
