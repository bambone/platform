<?php

namespace Tests\Feature;

use App\Mail\PlatformMarketingContactMail;
use App\Models\CrmRequest;
use App\Product\Settings\MarketingContentResolver;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PlatformMarketingContactTest extends TestCase
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

    public function test_contact_get_renders_form_on_central_marketing_host(): void
    {
        $pm = app(MarketingContentResolver::class)->resolved();
        $contactTitle = (string) (($pm['contact_page']['default_title'] ?? null) ?: 'Напишите нам');

        $this->call('GET', 'http://apex.test/contact')
            ->assertOk()
            ->assertSee($contactTitle, false)
            ->assertSee('Тема обращения', false);
    }

    public function test_contact_post_sends_mail_and_redirects(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Mail::fake();
        config(['mail.from.address' => 'ops@example.test']);

        $response = $this->postWithHost('apex.test', '/contact', [
            'name' => 'Тест Тестов',
            'phone' => '+79990001122',
            'email' => 'test-contact@example.test',
            'preferred_contact_channel' => 'phone',
            'message' => 'Нужен прокат мото, онлайн-запись и учёт парка.',
            'intent' => 'launch',
            'company_site' => '',
        ]);

        $response->assertRedirect();
        Mail::assertQueued(PlatformMarketingContactMail::class);
        $this->assertDatabaseHas('crm_requests', [
            'tenant_id' => null,
            'request_type' => 'platform_contact',
            'source' => 'platform_marketing_contact',
        ]);
        $this->assertSame(1, CrmRequest::query()->whereNull('tenant_id')->count());
    }

    public function test_contact_post_requires_email(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Mail::fake();

        $this->postWithHost('apex.test', '/contact', [
            'name' => 'Тест',
            'phone' => '',
            'email' => '',
            'preferred_contact_channel' => 'email',
            'message' => 'Достаточно длинный текст для валидации.',
            'intent' => 'demo',
            'company_site' => '',
        ])->assertSessionHasErrors(['email']);
    }
}
