<?php

namespace App\Models;

use App\Auth\AccessRoles;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Lead extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'crm_request_id',
        'name',
        'phone',
        'preferred_contact_channel',
        'preferred_contact_value',
        'visitor_contact_channels_json',
        'legal_acceptances_json',
        'email',
        'comment',
        'motorcycle_id',
        'customer_id',
        'rental_date_from',
        'rental_date_to',
        'source',
        'page_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'status',
        'assigned_user_id',
        'manager_notes',
    ];

    protected $casts = [
        'rental_date_from' => 'date',
        'rental_date_to' => 'date',
        'visitor_contact_channels_json' => 'array',
        'legal_acceptances_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Lead $lead): void {
            if ($lead->assigned_user_id === null) {
                return;
            }

            $tenantId = (int) $lead->tenant_id;
            if ($tenantId === 0) {
                return;
            }

            $allowed = User::query()
                ->whereKey($lead->assigned_user_id)
                ->whereHas('tenants', function ($query) use ($tenantId): void {
                    $query->where('tenants.id', $tenantId)
                        ->where('tenant_user.status', 'active')
                        ->whereIn('tenant_user.role', AccessRoles::tenantMembershipRolesForPanel());
                })
                ->exists();

            if (! $allowed) {
                throw ValidationException::withMessages([
                    'assigned_user_id' => 'Ответственный должен быть активным участником команды этого клиента.',
                ]);
            }
        });
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->orderByDesc('created_at');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(LeadActivityLog::class)->orderByDesc('created_at');
    }

    public function crmRequest(): BelongsTo
    {
        return $this->belongsTo(CrmRequest::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public static function statuses(): array
    {
        return [
            'new' => 'Новая',
            'in_progress' => 'В работе',
            'confirmed' => 'Подтверждена',
            'cancelled' => 'Отменена',
            'completed' => 'Завершена',
            'spam' => 'Спам',
        ];
    }

    public static function sources(): array
    {
        return [
            'booking_form' => 'Форма бронирования',
            'contact_form' => 'Форма обратной связи',
            'hero_cta_form' => 'Hero CTA',
            'phone' => 'Телефон',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'manual' => 'Вручную (кабинет)',
            'office' => 'Офис / стойка',
            'other' => 'Другое',
        ];
    }
}
