<?php

namespace Tests\Feature\PageBuilder;

use App\Models\Page;
use App\Models\PageSection;
use App\Tenant\Expert\VideoEmbedUrlNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Связка data_json editorial_gallery → публичный include (img / video / iframe).
 */
class EditorialGalleryPublicRenderTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function getWithHost(string $host, string $path = '/'): \Illuminate\Testing\TestResponse
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->call('GET', 'http://'.$host.$path);
    }

    public function test_editorial_gallery_renders_image_video_and_embed_on_generic_page(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-pub', ['theme_key' => 'advocate_editorial']);
        $host = $this->tenancyHostForSlug('pb-eg-pub');

        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Галерея публичный тест',
            'slug' => 'gallery-pub',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        $imgUrl = 'https://eg-render.test/g-only.jpg';
        $vidUrl = 'https://eg-render.test/g-only.mp4';
        $ytShare = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $iframeSrc = VideoEmbedUrlNormalizer::toIframeSrc('youtube', $ytShare);
        $this->assertNotNull($iframeSrc);

        PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'editorial_gallery',
            'section_type' => 'editorial_gallery',
            'title' => 'Галерея',
            'data_json' => [
                'section_heading' => 'EG_PUB_HEAD_Z9',
                'section_lead' => '',
                'items' => [
                    'a' => [
                        'media_kind' => 'image',
                        'image_url' => $imgUrl,
                        'video_url' => '',
                        'poster_url' => '',
                        'caption' => '',
                        'source_url' => '',
                        'source_label' => '',
                        'source_new_tab' => true,
                    ],
                    'b' => [
                        'media_kind' => 'video',
                        'image_url' => '',
                        'video_url' => $vidUrl,
                        'poster_url' => '',
                        'caption' => '',
                        'source_url' => '',
                        'source_label' => '',
                        'source_new_tab' => true,
                    ],
                    'c' => [
                        'media_kind' => 'video_embed',
                        'embed_provider' => 'youtube',
                        'embed_share_url' => $ytShare,
                        'image_url' => '',
                        'video_url' => '',
                        'poster_url' => '',
                        'caption' => '',
                        'source_url' => '',
                        'source_label' => '',
                        'source_new_tab' => true,
                    ],
                ],
            ],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $res = $this->getWithHost($host, '/gallery-pub');
        $res->assertOk();
        $html = $res->getContent();
        $this->assertStringContainsString('EG_PUB_HEAD_Z9', $html);
        $this->assertStringContainsString($imgUrl, $html);
        $this->assertStringContainsString('<img ', $html);
        $this->assertStringContainsString('<video ', $html);
        $this->assertStringContainsString('<iframe ', $html);
        $this->assertStringContainsString('data-expert-dialog-src="'.htmlspecialchars($vidUrl, ENT_QUOTES, 'UTF-8').'"', $html);
        $this->assertStringContainsString('data-expert-dialog-embed-src="'.htmlspecialchars($iframeSrc, ENT_QUOTES, 'UTF-8').'"', $html);
    }
}
