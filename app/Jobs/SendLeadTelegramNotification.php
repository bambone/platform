<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\Tenant;
use App\Services\CurrentTenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SendLeadTelegramNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    public function handle(CurrentTenantManager $tenantManager): void
    {
        if ($this->lead->tenant_id) {
            $tenant = Tenant::find($this->lead->tenant_id);
            $tenantManager->setTenant($tenant);
        }

        $botToken = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
        $chatId = config('services.telegram.chat_id') ?? env('TELEGRAM_CHAT_ID');

        if (! $botToken || ! $chatId) {
            return;
        }

        $motorcycleName = $this->lead->motorcycle?->name ?? 'Не указан';
        $source = Lead::sources()[$this->lead->source] ?? $this->lead->source ?? 'booking_form';

        $message = "🏍 *Новая заявка*\n\n"
            ."Мотоцикл: {$motorcycleName}\n"
            ."Источник: {$source}\n\n"
            ."Клиент: {$this->lead->name}\n"
            ."Телефон: {$this->lead->phone}\n";

        if ($this->lead->email) {
            $message .= "Email: {$this->lead->email}\n";
        }
        if ($this->lead->rental_date_from) {
            $message .= "Даты: {$this->lead->rental_date_from->format('d.m.Y')}";
            if ($this->lead->rental_date_to) {
                $message .= " — {$this->lead->rental_date_to->format('d.m.Y')}";
            }
            $message .= "\n";
        }
        if ($this->lead->comment) {
            $message .= "Комментарий: {$this->lead->comment}\n";
        }

        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }
}
