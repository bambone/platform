<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\Tenant;
use App\Services\CurrentTenantManager;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SendLeadTelegramNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    public function handle(CurrentTenantManager $tenantManager): void
    {
        $tenant = $this->lead->tenant_id ? Tenant::find($this->lead->tenant_id) : null;
        if ($tenant !== null) {
            $tenantManager->setTenant($tenant);
        }

        $botToken = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
        $chatId = config('services.telegram.chat_id') ?? env('TELEGRAM_CHAT_ID');

        if (! $botToken || ! $chatId) {
            return;
        }
        $terminology = $tenant !== null ? app(TenantTerminologyService::class) : null;
        $leadLabel = $terminology?->label($tenant, DomainTermKeys::LEAD) ?? 'Заявка';
        $resourceLabel = $terminology?->label($tenant, DomainTermKeys::RESOURCE) ?? 'Объект';

        $motorcycleName = $this->lead->motorcycle?->name ?? 'Не указан';
        $source = Lead::sources()[$this->lead->source] ?? $this->lead->source ?? 'booking_form';

        $message = "📋 *Новая {$leadLabel}*\n\n"
            ."{$resourceLabel}: {$motorcycleName}\n"
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
