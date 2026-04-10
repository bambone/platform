<?php

namespace Tests\Feature\CRM;

use App\Mail\PlatformMarketingContactMail;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PlatformContactMailRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function postWithHost(string $host, string $path, array $data): TestResponse
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->call('POST', 'http://'.$host.$path, $data);
    }

    public function test_contact_mail_goes_only_to_platform_recipients_not_spoofed_fields(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Mail::fake();

        PlatformSetting::set('email.contact_form_recipients', 'staff-one@example.test,staff-two@example.test', 'string');
        config(['mail.from.address' => 'system-from@example.test']);

        $this->postWithHost('apex.test', '/contact', [
            'name' => 'Spoof try',
            'phone' => '+79990001122',
            'email' => 'attacker-wants-copy@evil.test',
            'preferred_contact_channel' => 'phone',
            'message' => 'Long enough message body for validation.',
            'intent' => 'launch',
            'company_site' => '',
            'to' => 'hacker@evil.test',
            'bcc' => 'another@evil.test',
        ])->assertRedirect();

        Mail::assertQueued(PlatformMarketingContactMail::class, function (PlatformMarketingContactMail $mail): bool {
            return count($mail->to) === 1
                && ($mail->to[0]['address'] ?? null) === 'staff-one@example.test';
        });

        Mail::assertNotQueued(PlatformMarketingContactMail::class, function (PlatformMarketingContactMail $mail): bool {
            foreach ($mail->to as $recipient) {
                $addr = $recipient['address'] ?? '';
                if (str_contains($addr, 'evil.test')) {
                    return true;
                }
            }

            return false;
        });
    }
}
