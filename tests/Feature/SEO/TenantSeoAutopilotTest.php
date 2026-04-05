<?php

namespace Tests\Feature\SEO;

use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use App\Services\Seo\InitializeTenantSeoDefaults;
use App\Services\Seo\TenantSeoLintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantSeoAutopilotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    private function seedTrialTenant(string $host, string $slug): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'Autopilot Trial',
            'slug' => $slug,
            'status' => 'trial',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => $host,
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
            'verified_at' => now(),
            'activated_at' => now(),
        ]);

        TenantSetting::setForTenant($tenant->id, 'general.domain', 'https://'.$host, 'string');
        TenantSetting::setForTenant($tenant->id, 'general.site_name', 'ACME Rent', 'string');
        TenantSetting::setForTenant($tenant->id, 'seo.indexing_enabled', true, 'boolean');
        TenantSetting::setForTenant($tenant->id, 'seo.sitemap_enabled', true, 'boolean');

        return $tenant;
    }

    public function test_bootstrap_command_fills_llms_for_trial_tenant(): void
    {
        $tenant = $this->seedTrialTenant('aptrial.apex.test', 'aptrial');

        $this->artisan('tenant-seo:bootstrap', ['tenant' => 'aptrial'])
            ->assertSuccessful();

        $intro = trim((string) TenantSetting::getForTenant($tenant->id, 'seo.llms_intro', ''));
        $this->assertNotSame('', $intro);
        $this->assertStringContainsString('ACME Rent', $intro);
    }

    public function test_bootstrap_does_not_overwrite_manual_llms_without_force(): void
    {
        $tenant = $this->seedTrialTenant('apmanual.apex.test', 'apmanual');
        TenantSetting::setForTenant($tenant->id, 'seo.llms_intro', 'Ручной текст', 'string');

        app(InitializeTenantSeoDefaults::class)->execute($tenant, false, false);

        $this->assertSame('Ручной текст', trim((string) TenantSetting::getForTenant($tenant->id, 'seo.llms_intro', '')));
    }

    public function test_lint_runs_in_internal_mode_without_errors_for_minimal_tenant(): void
    {
        $tenant = $this->seedTrialTenant('aplint.apex.test', 'aplint');

        $result = app(TenantSeoLintService::class)->lint($tenant, false);

        $this->assertGreaterThanOrEqual(0, $result->score);
        $this->assertNotSame([], $result->checkedPages);
    }

    public function test_force_overwrites_home_seo_meta(): void
    {
        $tenant = $this->seedTrialTenant('apforce.apex.test', 'apforce');

        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Home',
            'slug' => 'home',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);

        SeoMeta::query()->create([
            'tenant_id' => $tenant->id,
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'meta_title' => 'OLD MANUAL TITLE',
            'meta_description' => 'old manual description',
            'og_title' => 'OLD MANUAL TITLE',
            'og_description' => 'old manual description',
        ]);

        app(InitializeTenantSeoDefaults::class)->execute($tenant, true, false);

        $page->refresh();
        $page->load('seoMeta');
        $this->assertNotNull($page->seoMeta);
        $this->assertStringContainsString('ACME Rent', (string) $page->seoMeta->meta_title);
        $this->assertStringNotContainsString('OLD MANUAL TITLE', (string) $page->seoMeta->meta_title);
    }
}
