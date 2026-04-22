<?php

namespace App\Services\Notifications;

use App\NotificationCenter\Drivers\TelegramNotificationDriver;
use Illuminate\Support\Facades\Http;

/**
 * Plain-text Telegram Bot API sendMessage (no parse_mode).
 *
 * Shared by {@see TelegramNotificationDriver} and platform inbound jobs.
 */
final class TelegramTextSender
{
    private const int MAX_MESSAGE_LENGTH = 4096;

    private const int TIMEOUT_SECONDS = 15;

    /**
     * @return array{provider_message_id: ?string, response_json: array<string, mixed>}
     */
    public function sendPlainText(string $token, string $chatId, string $text): array
    {
        $text = mb_substr($text, 0, self::MAX_MESSAGE_LENGTH);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => trim($chatId),
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Telegram request failed: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Telegram API error: '.$response->body());
        }

        $decoded = $response->json();
        $messageId = $response->json('result.message_id');

        return [
            'provider_message_id' => $messageId !== null ? (string) $messageId : null,
            'response_json' => is_array($decoded) ? $decoded : ['raw' => $response->body()],
        ];
    }
}
