<?php

namespace App\NotificationCenter\Drivers;

use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationEvent;
use App\NotificationCenter\ChannelSendResult;
use App\NotificationCenter\Contracts\NotificationChannelDriver;
use App\NotificationCenter\NotificationDeliveryStatus;
use App\Services\Notifications\TelegramTextSender;
use App\Services\Platform\PlatformNotificationSettings;
use Illuminate\Support\Carbon;

/**
 * Telegram Bot API sendMessage. Sends plain text only (no parse_mode) so payload is never interpreted as Markdown/HTML.
 */
final class TelegramNotificationDriver implements NotificationChannelDriver
{
    public function __construct(
        private readonly PlatformNotificationSettings $platform,
        private readonly TelegramTextSender $telegramText,
    ) {}

    public function send(
        NotificationDelivery $delivery,
        NotificationEvent $event,
        NotificationDestination $destination,
    ): ChannelSendResult {
        $token = $this->platform->telegramBotTokenDecrypted();
        if ($token === null) {
            throw new \RuntimeException('Telegram bot token is not configured on platform.');
        }

        $chatId = $destination->config_json['chat_id'] ?? null;
        if (! is_string($chatId) || trim($chatId) === '') {
            throw new \InvalidArgumentException('Telegram destination requires chat_id in config.');
        }

        $payload = $event->payloadDto();
        $parts = array_filter([
            trim((string) $payload->title),
            trim((string) $payload->body),
            $payload->actionUrl ? trim((string) $payload->actionUrl) : null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        $text = implode("\n\n", $parts);

        $result = $this->telegramText->sendPlainText($token, $chatId, $text);
        $now = Carbon::now();

        return new ChannelSendResult(
            status: NotificationDeliveryStatus::Sent,
            sentAt: $now,
            deliveredAt: null,
            providerMessageId: $result['provider_message_id'],
            responseJson: $result['response_json'],
        );
    }
}
