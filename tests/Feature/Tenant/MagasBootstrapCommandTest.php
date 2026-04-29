<?php

namespace Tests\Feature\Tenant;

use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Tenant\MagasExpertBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MagasBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_default_run_creates_draft_pages_and_seo_not_indexable(): void
    {
        Artisan::call('tenant:magas:bootstrap', []);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');
        $this->assertGreaterThan(0, $tid);

        $privacy = DB::table('pages')->where('tenant_id', $tid)->where('slug', 'privacy')->first();
        $this->assertNotNull($privacy);
        $this->assertSame('draft', $privacy->status);

        $homeId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'home')->value('id');
        $this->assertGreaterThan(0, $homeId);

        $seo = DB::table('seo_meta')
            ->where('tenant_id', $tid)
            ->where('seoable_type', \App\Models\Page::class)
            ->where('seoable_id', $homeId)
            ->first();
        $this->assertNotNull($seo);
        $this->assertSame(0, (int) $seo->is_indexable);
    }

    public function test_publish_sets_substantive_pages_indexable_not_placeholder_without_flag(): void
    {
        Artisan::call('tenant:magas:bootstrap', [
            '--publish' => true,
        ]);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');
        $homeId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'home')->value('id');
        $seoHome = DB::table('seo_meta')
            ->where('tenant_id', $tid)
            ->where('seoable_id', $homeId)
            ->where('seoable_type', \App\Models\Page::class)
            ->first();
        $this->assertNotNull($seoHome);
        $this->assertSame(1, (int) $seoHome->is_indexable);

        $privacyRow = DB::table('pages')->where('tenant_id', $tid)->where('slug', 'privacy')->first();
        $this->assertNotNull($privacyRow);
        $this->assertSame('published', $privacyRow->status);

        $privacyId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'privacy')->value('id');
        $seoPrivacy = DB::table('seo_meta')
            ->where('tenant_id', $tid)
            ->where('seoable_id', $privacyId)
            ->where('seoable_type', \App\Models\Page::class)
            ->first();
        $this->assertNotNull($seoPrivacy);
        $this->assertSame(0, (int) $seoPrivacy->is_indexable);
    }

    public function test_sergeymagas_com_is_only_primary_domain(): void
    {
        Artisan::call('tenant:magas:bootstrap', []);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');
        $primaries = DB::table('tenant_domains')
            ->where('tenant_id', $tid)
            ->where('is_primary', true)
            ->pluck('host')
            ->all();
        $this->assertSame(['sergeymagas.com'], array_values($primaries));
    }

    public function test_host_conflict_with_another_tenant_throws(): void
    {
        $otherTid = (int) DB::table('tenants')->insertGetId([
            'name' => 'Other',
            'slug' => 'other-magas-block',
            'brand_name' => 'Other',
            'theme_key' => 'expert_pr',
            'status' => 'active',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tenant_domains')->insert([
            'tenant_id' => $otherTid,
            'host' => 'sergeymagas.com',
            'type' => \App\Models\TenantDomain::TYPE_CUSTOM,
            'is_primary' => true,
            'status' => 'active',
            'ssl_status' => \App\Models\TenantDomain::SSL_PENDING,
            'verified_at' => now(),
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        MagasExpertBootstrap::run(false, null, false, false);
    }

    public function test_second_run_with_publish_and_placeholder_promotes_existing_faq_rows(): void
    {
        Artisan::call('tenant:magas:bootstrap', []);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');
        $this->assertGreaterThan(0, DB::table('faqs')->where('tenant_id', $tid)->where('status', 'draft')->count());

        Artisan::call('tenant:magas:bootstrap', [
            '--publish' => true,
            '--allow-placeholder-content' => true,
        ]);

        $this->assertSame(
            DB::table('faqs')->where('tenant_id', $tid)->count(),
            DB::table('faqs')->where('tenant_id', $tid)->where('status', 'published')->count(),
        );
    }

    public function test_canonical_id_rejects_invalid_value(): void
    {
        $exit = Artisan::call('tenant:magas:bootstrap', [
            '--canonical-id' => 'abc',
        ]);
        $this->assertSame(1, $exit);
    }

    public function test_publish_conflicts_with_force_draft(): void
    {
        $exit = Artisan::call('tenant:magas:bootstrap', [
            '--publish' => true,
            '--force-draft' => true,
        ]);
        $this->assertSame(1, $exit);
    }

    /**
     * Без параметра командная строка не обязана «избегать» id=5: до Magas уже могли создаться сиды других строк.
     * Явный --canonical-id гарантированно вставляет в свободный слот выбранный PK.
     */
    public function test_canonical_id_inserts_requested_row_id_when_slot_free(): void
    {
        Artisan::call('tenant:magas:bootstrap', [
            '--canonical-id' => 88,
        ]);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');
        $this->assertSame(88, $tid);
    }
}
