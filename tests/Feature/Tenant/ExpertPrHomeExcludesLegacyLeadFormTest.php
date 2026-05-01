<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Page;
use App\Models\PageSection;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * На главной expert_pr блок expert_lead_form не показываем (форма брифа на /contacts).
 * Старые строки в page_sections остаются до bootstrap — контроллер их отфильтровывает.
 */
final class ExpertPrHomeExcludesLegacyLeadFormTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_home_html_does_not_render_expert_lead_form_block_when_section_row_exists(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expertprhomelead', [
            'theme_key' => 'expert_pr',
            'locale' => 'en',
        ]);
        $host = $this->tenancyHostForSlug('expertprhomelead');

        $home = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Home',
            'slug' => 'home',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);

        PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $home->id,
            'section_key' => 'expert_lead_form',
            'section_type' => 'expert_lead_form',
            'title' => null,
            'data_json' => ['headline' => 'Legacy'],
            'sort_order' => 50,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $response = $this->get('http://'.$host.'/');
        $response->assertOk();
        $response->assertDontSee('id="expert-inquiry-block"', false);
        $response->assertDontSee('expert-lead-mega__shell', false);
    }
}
