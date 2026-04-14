<?php

namespace Tests\Feature\PageBuilder;

use App\Livewire\Tenant\PageSectionsBuilder;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenant\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Ensures Filament repeater "add" updates Livewire state for contacts_info channels (regression: DOM morph / ignore.self).
 */
class ContactsRepeaterAddLivewireTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    private function bindTenantContext(Tenant $tenant): void
    {
        $host = $this->tenancyHostForSlug((string) $tenant->slug);
        $domain = TenantDomain::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->app->instance(CurrentTenant::class, new CurrentTenant($tenant, $domain, false, $host));
    }

    public function test_mount_add_action_increases_contacts_channels_in_state(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-contacts-add');
        $this->bindTenantContext($tenant);

        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Контакты',
            'slug' => 'contacts-pb-add',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        $section = PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'contacts_1',
            'section_type' => 'contacts_info',
            'title' => 'Контакты блок',
            'data_json' => [
                'title' => 'Пишите',
                'description' => null,
                'additional_note' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+79990001122',
                        'is_enabled' => true,
                        'is_primary' => true,
                        'label' => null,
                        'cta_label' => null,
                        'note' => null,
                        'open_in_new_tab' => 'inherit',
                        'is_override_url' => false,
                        'url' => null,
                    ],
                ],
                'address' => null,
                'working_hours' => null,
                'map_enabled' => false,
                'map_provider' => 'none',
                'map_public_url' => '',
                'map_display_mode' => 'embed_and_button',
                'map_title' => '',
            ],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page->fresh()])
            ->call('startEdit', $section->id);

        $channels = $lw->get('sectionFormData.data_json.channels') ?? [];
        $this->assertCount(1, $channels, 'startEdit should hydrate one channel row');

        $lw->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.channels']);

        $channelsAfter = $lw->get('sectionFormData.data_json.channels') ?? [];
        $this->assertCount(2, $channelsAfter, 'Repeater add should append a second channel row in Livewire state');
    }
}
