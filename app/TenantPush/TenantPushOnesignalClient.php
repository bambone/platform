<?php

declare(strict_types=1);

namespace App\TenantPush;

use Illuminate\Support\Facades\Http;

final class TenantPushOnesignalClient
{
    public function verifyAppCredentials(string $appId, string $restApiKey): array
    {
        $response = Http::timeout(15)
            ->withHeaders([
                'Authorization' => 'Key '.$restApiKey,
            ])
            ->acceptJson()
            ->get('https://api.onesignal.com/apps/'.$appId);

        $body = $response->json() ?? $response->body();

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $body,
            'code' => TenantPushOnesignalResponseNormalizer::codeForVerify($response->status(), $body),
        ];
    }

    /**
     * @param  list<string>  $externalUserIds
     * @return array{ok: bool, status: int, body: mixed, code: TenantPushDiagnosticCode}
     */
    public function sendTestToExternalUserIds(
        string $appId,
        string $restApiKey,
        array $externalUserIds,
        string $title,
        string $body,
    ): array {
        $payload = [
            'app_id' => $appId,
            'include_external_user_ids' => array_values($externalUserIds),
            'headings' => ['en' => $title],
            'contents' => ['en' => $body],
        ];

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => 'Key '.$restApiKey,
                'Content-Type' => 'application/json',
            ])
            ->acceptJson()
            ->post('https://api.onesignal.com/notifications', $payload);

        $body = $response->json() ?? $response->body();

        $wrapped = [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $body,
        ];

        return [
            ...$wrapped,
            'code' => TenantPushOnesignalResponseNormalizer::codeForNotificationSend($wrapped),
        ];
    }
}
