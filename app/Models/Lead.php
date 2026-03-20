<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'messenger',
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
    ];

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
            'other' => 'Другое',
        ];
    }
}
