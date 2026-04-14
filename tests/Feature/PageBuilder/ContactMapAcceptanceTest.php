<?php

declare(strict_types=1);

namespace Tests\Feature\PageBuilder;

use App\Models\Page;
use App\Models\PageSection;
use App\PageBuilder\Contacts\ContactMapPreviewBuilder;
use App\PageBuilder\Contacts\ContactMapPublicResolver;
use App\PageBuilder\Contacts\MapEffectiveRenderMode;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Приёмка: карта контактов (DTO mapBlock), preview builder и публичная страница без ошибок вьюхи.
 */
final class ContactMapAcceptanceTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_preview_builder_empty_url_status_matches_public_resolver(): void
    {
        $data = [
            'map_enabled' => true,
            'map_provider' => 'yandex',
            'map_public_url' => '',
            'map_display_mode' => 'embed_and_button',
        ];

        $preview = ContactMapPreviewBuilder::fromDataJson($data);
        self::assertSame('empty', $preview['status']);

        $resolved = app(ContactMapPublicResolver::class)->resolve($data);
        self::assertSame(MapEffectiveRenderMode::None, $resolved->mapEffectiveRenderMode);
    }

    public function test_preview_builder_invalid_scheme_returns_error(): void
    {
        $preview = ContactMapPreviewBuilder::fromDataJson([
            'map_enabled' => true,
            'map_provider' => 'yandex',
            'map_public_url' => 'http://yandex.ru/maps/?ll=30.3,59.9&z=12',
            'map_display_mode' => 'embed_and_button',
        ]);

        self::assertSame('error', $preview['status']);
        self::assertNull($preview['resolved']);
    }

    public function test_preview_builder_yandex_ll_z_success_aligns_with_resolver(): void
    {
        $data = [
            'map_enabled' => true,
            'map_provider' => 'yandex',
            'map_public_url' => 'https://yandex.ru/maps/?ll=30.3%2C59.9&z=12',
            'map_display_mode' => 'embed_and_button',
        ];

        $preview = ContactMapPreviewBuilder::fromDataJson($data);
        self::assertSame('success', $preview['status']);
        self::assertNotNull($preview['resolved']);

        $direct = app(ContactMapPublicResolver::class)->resolve($data);
        self::assertEquals(
            $direct->mapEffectiveRenderMode,
            $preview['resolved']->mapEffectiveRenderMode,
            'Preview и публичный resolver должны давать один effective mode.'
        );
        self::assertSame(
            $direct->mapPublicUrl,
            $preview['resolved']->mapPublicUrl
        );
    }

    public function test_preview_builder_yandex_text_only_warns_when_embed_wanted(): void
    {
        $data = [
            'map_enabled' => true,
            'map_provider' => 'yandex',
            'map_public_url' => 'https://yandex.ru/maps/?text='.rawurlencode('Москва'),
            'map_display_mode' => 'embed_and_button',
        ];

        $preview = ContactMapPreviewBuilder::fromDataJson($data);
        self::assertSame('warning', $preview['status']);
        self::assertNotNull($preview['resolved']);
        self::assertSame(MapEffectiveRenderMode::ButtonOnly, $preview['resolved']->mapEffectiveRenderMode);
    }

    public function test_preview_builder_two_gis_consumer_link_shows_link_first_policy_success(): void
    {
        $data = [
            'map_enabled' => true,
            'map_provider' => '2gis',
            'map_input_mode' => 'auto',
            'map_combined_input' => 'https://2gis.ru/moscow',
            'map_display_mode' => 'embed_and_button',
        ];

        $preview = ContactMapPreviewBuilder::fromDataJson($data);
        self::assertSame('success', $preview['status']);
        self::assertNotNull($preview['resolved']);
        self::assertStringContainsString('2ГИС', $preview['message']);
        self::assertStringContainsString('не поддерживается', $preview['message']);
        self::assertStringContainsString('Открыть в 2ГИС', $preview['message']);
    }

    public function test_preview_builder_button_only_with_embeddable_widget_warns(): void
    {
        $data = [
            'map_enabled' => true,
            'map_provider' => 'yandex',
            'map_input_mode' => 'auto',
            'map_combined_input' => '<iframe src="https://yandex.ru/map-widget/v1/?ll=61.4%2C55.17&z=16"></iframe>',
            'map_display_mode' => 'button_only',
        ];

        $preview = ContactMapPreviewBuilder::fromDataJson($data);
        self::assertSame('warning', $preview['status']);
        self::assertNotNull($preview['resolved']);
        self::assertTrue($preview['resolved']->mapCanEmbed);
        self::assertStringContainsString('Только ссылка', $preview['message']);
        self::assertStringContainsString('Только карта', $preview['message']);
    }

    public function test_advocate_editorial_contacts_custom_page_renders_ok_with_visible_map(): void
    {
        $tenant = $this->createTenantWithActiveDomain('advcontacts', ['theme_key' => 'advocate_editorial']);
        $host = $this->tenancyHostForSlug('advcontacts');

        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Контакты',
            'slug' => 'contacts',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => true,
            'main_menu_sort_order' => 10,
        ]);

        PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'main',
            'section_type' => 'rich_text',
            'title' => 'Вводный текст',
            'data_json' => [
                'content' => '<p>Вступление для теста контактов.</p>',
            ],
            'sort_order' => 0,
            'is_visible' => true,
            'status' => 'published',
        ]);

        PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'contacts_block',
            'section_type' => 'contacts_info',
            'title' => 'Контакты',
            'data_json' => [
                'title' => 'Связь',
                'address' => 'г. Челябинск',
                'map_enabled' => true,
                'map_provider' => 'yandex',
                'map_public_url' => 'https://yandex.ru/maps/?ll=61.4%2C55.17&z=16',
                'map_display_mode' => 'embed_and_button',
                'map_title' => '',
                'channels' => [],
            ],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $this->call('GET', 'http://'.$host.'/contacts')
            ->assertOk()
            ->assertSee('Связь', false);
    }
}
