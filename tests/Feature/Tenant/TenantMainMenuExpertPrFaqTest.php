<?php

namespace Tests\Feature\Tenant;

use App\Models\Page;
use App\Services\Tenancy\TenantMainMenuPages;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class TenantMainMenuExpertPrFaqTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_expert_pr_menu_adds_faq_only_when_at_least_one_faq_is_published(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expertprmenufaq', [
            'theme_key' => 'expert_pr',
            'locale' => 'en',
        ]);

        Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Contacts',
            'slug' => 'contacts',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => true,
            'main_menu_sort_order' => 10,
        ]);

        DB::table('faqs')->insert([
            'tenant_id' => $tenant->id,
            'question' => 'Draft only?',
            'answer' => 'Yes',
            'category' => null,
            'sort_order' => 10,
            'status' => 'draft',
            'show_on_home' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemsDraft = app(TenantMainMenuPages::class)->menuItems($tenant);
        $this->assertSame(['Contacts'], $itemsDraft->pluck('label')->all());

        DB::table('faqs')->where('tenant_id', $tenant->id)->update([
            'status' => 'published',
            'updated_at' => now(),
        ]);

        $itemsPublished = app(TenantMainMenuPages::class)->menuItems($tenant->fresh());
        $labels = $itemsPublished->pluck('label')->all();
        $this->assertContains('Contacts', $labels);
        $this->assertContains('FAQ', $labels);
    }
}
