<?php

namespace Tests\Unit\TenantPush;

use App\TenantPush\TenantPushIosReadinessResolver;
use App\TenantPush\TenantPushIosReadinessState;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class TenantPushIosReadinessResolverTest extends TestCase
{
    public function test_desktop_is_not_applicable(): void
    {
        $r = new TenantPushIosReadinessResolver();
        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)']);

        $this->assertSame(TenantPushIosReadinessState::NotApplicable, $r->stateForRequest($req));
    }

    public function test_ios_old_version_not_supported(): void
    {
        $r = new TenantPushIosReadinessResolver();
        $req = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15',
        ]);

        $this->assertSame(TenantPushIosReadinessState::IosNotSupported, $r->stateForRequest($req));
    }

    public function test_ios_17_needs_home_screen_install_when_not_standalone(): void
    {
        $r = new TenantPushIosReadinessResolver();
        $req = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
        ]);

        $this->assertSame(TenantPushIosReadinessState::IosReadyButNotInstalled, $r->stateForRequest($req));
    }

    public function test_standalone_cookie(): void
    {
        $r = new TenantPushIosReadinessResolver();
        $req = Request::create('/', 'GET', [], ['rb_ios_standalone' => '1'], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
        ]);

        $this->assertSame(TenantPushIosReadinessState::IosInstalledReadyForPrompt, $r->stateForRequest($req));
    }
}
