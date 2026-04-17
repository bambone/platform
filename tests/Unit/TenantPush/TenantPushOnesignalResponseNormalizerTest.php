<?php

namespace Tests\Unit\TenantPush;

use App\TenantPush\TenantPushDiagnosticCode;
use App\TenantPush\TenantPushOnesignalResponseNormalizer;
use PHPUnit\Framework\TestCase;

class TenantPushOnesignalResponseNormalizerTest extends TestCase
{
    public function test_verify_404_maps_to_app_not_found(): void
    {
        $code = TenantPushOnesignalResponseNormalizer::codeForVerify(404, ['errors' => 'nope']);

        $this->assertSame(TenantPushDiagnosticCode::AppNotFoundOrNotAccessible, $code);
    }

    public function test_verify_403_ip_hint(): void
    {
        $code = TenantPushOnesignalResponseNormalizer::codeForVerify(403, 'IP address not allowed on allowlist');

        $this->assertSame(TenantPushDiagnosticCode::IpNotAllowed, $code);
    }

    public function test_verify_401_org_key_hint(): void
    {
        $code = TenantPushOnesignalResponseNormalizer::codeForVerify(401, 'This organization key cannot access app');

        $this->assertSame(TenantPushDiagnosticCode::WrongKeyType, $code);
    }

    public function test_notification_no_recipients(): void
    {
        $code = TenantPushOnesignalResponseNormalizer::codeForNotificationSend([
            'ok' => false,
            'status' => 400,
            'body' => ['errors' => 'No subscribers with external_user_id'],
        ]);

        $this->assertSame(TenantPushDiagnosticCode::NoActiveSubscriptions, $code);
    }
}
