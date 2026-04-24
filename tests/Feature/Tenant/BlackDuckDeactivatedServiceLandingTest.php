<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use App\Tenant\BlackDuck\BlackDuckServicePageSync;
use App\Tenant\Expert\ServiceProgramType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Витрина скрыла услугу, но лендинг существует: {@see BlackDuckContentRefresher::concealPublicSectionsForDeactivatedServiceLandings} + {@see \App\Tenant\BlackDuck\BlackDuckServicePageSync}.
 *
 * Регрессия «реактивации»: {@see regression_deactivate_sync_refresh_then_reactivate_sync_refresh_restores_structural_sections} — сценарий
 * disable → {@see BlackDuckServicePageSync::syncForTenant} + {@see BlackDuckContentRefresher::refreshContent} → enable → sync + refresh → снова {@code is_visible} у структурных секций и {@code pages.status = published}.
 */
final class BlackDuckDeactivatedServiceLandingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function refresh_hides_spoofing_sections_when_service_turned_off_after_landing_existed(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD Deact',
            'slug' => 'bd-deact-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        $slug = 'svc-deact-'.substr(uniqid(), -5);
        $p = TenantServiceProgram::query()->create([
            'tenant_id' => $tid,
            'slug' => $slug,
            'title' => 'Temp landing',
            'teaser' => 't',
            'description' => 'body',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'catalog_meta_json' => [
                'has_landing' => true,
                'booking_mode' => 'confirm',
            ],
        ]);
        $page = Page::query()->create([
            'tenant_id' => $tid,
            'name' => 'Temp',
            'slug' => $slug,
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $pid = (int) $page->id;
        $mk = static function (string $key, string $type) use ($tid, $pid, $slug): void {
            PageSection::query()->create([
                'tenant_id' => $tid,
                'page_id' => $pid,
                'section_key' => $key,
                'section_type' => $type,
                'title' => $key,
                'data_json' => [],
                'is_visible' => true,
                'status' => 'published',
                'sort_order' => 1,
            ]);
        };
        $mk('hero', 'hero');
        $mk('service_proof', 'case_study_cards');
        $mk('service_review_feed', 'review_feed');
        $mk('service_faq', 'faq');

        $p->is_visible = false;
        $p->save();
        $tenant = $tenant->fresh();
        $this->assertNotNull($tenant);
        (new BlackDuckServicePageSync)->syncForTenant($tid);
        app(BlackDuckContentRefresher::class)->refreshContent($tenant, [
            'force' => false,
            'if_placeholder' => true,
            'only_seo' => false,
            'force_section' => null,
            'dry_run' => false,
        ]);

        $this->assertSame('hidden', (string) DB::table('pages')->where('id', $pid)->value('status'));
        foreach (['service_proof', 'service_review_feed', 'service_faq', 'hero'] as $key) {
            $v = (bool) DB::table('page_sections')
                ->where('tenant_id', $tid)
                ->where('page_id', $pid)
                ->where('section_key', $key)
                ->value('is_visible');
            $this->assertFalse($v, 'section '.$key.' must be hidden for deactivated service');
        }
    }

    /**
     * Регрессия reactivate-landing (план pre-merge): услуга выключена → sync+refresh скрывает страницу;
     * услуга снова включена → sync+refresh восстанавливает {@code published} и {@code is_visible} для
     * hero, body_intro, service_included, service_final_cta.
     */
    #[Test]
    public function regression_deactivate_sync_refresh_then_reactivate_sync_refresh_restores_structural_sections(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD React',
            'slug' => 'bd-react-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        $slug = 'svc-react-'.substr(uniqid(), -5);
        $p = TenantServiceProgram::query()->create([
            'tenant_id' => $tid,
            'slug' => $slug,
            'title' => 'Reactivable',
            'teaser' => 'lead',
            'description' => 'intro body',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'catalog_meta_json' => [
                'has_landing' => true,
                'booking_mode' => 'confirm',
            ],
        ]);
        $page = Page::query()->create([
            'tenant_id' => $tid,
            'name' => 'Reactivable',
            'slug' => $slug,
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $pid = (int) $page->id;
        $mk = static function (string $key, string $type) use ($tid, $pid): void {
            PageSection::query()->create([
                'tenant_id' => $tid,
                'page_id' => $pid,
                'section_key' => $key,
                'section_type' => $type,
                'title' => $key,
                'data_json' => [],
                'is_visible' => true,
                'status' => 'published',
                'sort_order' => 1,
            ]);
        };
        foreach (
            [
                ['hero', 'hero'],
                ['body_intro', 'rich_text'],
                ['service_included', 'list_block'],
                ['service_final_cta', 'rich_text'],
                ['service_proof', 'case_study_cards'],
            ] as [$k, $t]
        ) {
            $mk($k, $t);
        }

        $p->is_visible = false;
        $p->save();
        $tenant = $tenant->fresh();
        $this->assertNotNull($tenant);
        (new BlackDuckServicePageSync)->syncForTenant($tid);
        app(BlackDuckContentRefresher::class)->refreshContent($tenant, [
            'force' => false,
            'if_placeholder' => true,
            'only_seo' => false,
            'force_section' => null,
            'dry_run' => false,
        ]);
        $this->assertSame('hidden', (string) DB::table('pages')->where('id', $pid)->value('status'));

        $p->is_visible = true;
        $p->save();
        $tenant = $tenant->fresh();
        $this->assertNotNull($tenant);
        (new BlackDuckServicePageSync)->syncForTenant($tid);
        app(BlackDuckContentRefresher::class)->refreshContent($tenant, [
            'force' => false,
            'if_placeholder' => true,
            'only_seo' => false,
            'force_section' => null,
            'dry_run' => false,
        ]);

        $this->assertSame('published', (string) DB::table('pages')->where('id', $pid)->value('status'));
        foreach (['hero', 'body_intro', 'service_included', 'service_final_cta'] as $key) {
            $v = (bool) DB::table('page_sections')
                ->where('tenant_id', $tid)
                ->where('page_id', $pid)
                ->where('section_key', $key)
                ->value('is_visible');
            $this->assertTrue($v, 'structural section '.$key.' must be visible after reactivate+refresh');
        }
    }

    #[Test]
    public function narrow_force_section_conceal_only_hides_matching_section_on_deactivated_landings(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD Narrow',
            'slug' => 'bd-narrow-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        $slugs = ['svc-n-a-'.substr(uniqid(), -4), 'svc-n-b-'.substr(uniqid(), -4)];
        foreach ($slugs as $i => $slug) {
            TenantServiceProgram::query()->create([
                'tenant_id' => $tid,
                'slug' => $slug,
                'title' => 'Off '.$i,
                'teaser' => 't',
                'description' => 'd',
                'program_type' => ServiceProgramType::Program->value,
                'is_visible' => false,
                'is_featured' => false,
                'sort_order' => $i,
                'catalog_meta_json' => [
                    'has_landing' => true,
                    'booking_mode' => 'confirm',
                ],
            ]);
            $pid = (int) Page::query()->create([
                'tenant_id' => $tid,
                'name' => 'P'.$i,
                'slug' => $slug,
                'template' => 'default',
                'status' => 'published',
                'published_at' => now(),
            ])->id;
            foreach (
                [
                    ['hero', 'hero'],
                    ['service_proof', 'case_study_cards'],
                ] as [$key, $type]
            ) {
                PageSection::query()->create([
                    'tenant_id' => $tid,
                    'page_id' => $pid,
                    'section_key' => $key,
                    'section_type' => $type,
                    'title' => $key,
                    'data_json' => [],
                    'is_visible' => true,
                    'status' => 'published',
                    'sort_order' => 1,
                ]);
            }
        }

        $tenant = $tenant->fresh();
        $this->assertNotNull($tenant);
        app(BlackDuckContentRefresher::class)->refreshContent($tenant, [
            'force' => false,
            'if_placeholder' => true,
            'only_seo' => false,
            'force_section' => 'hero',
            'dry_run' => false,
        ]);

        foreach ($slugs as $slug) {
            $pid = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', $slug)->value('id');
            $this->assertFalse((bool) DB::table('page_sections')
                ->where('tenant_id', $tid)
                ->where('page_id', $pid)
                ->where('section_key', 'hero')
                ->value('is_visible'));
            $this->assertTrue((bool) DB::table('page_sections')
                ->where('tenant_id', $tid)
                ->where('page_id', $pid)
                ->where('section_key', 'service_proof')
                ->value('is_visible'),
                'force_section=hero must not set service_proof hidden on '.$slug
            );
        }
    }
}
