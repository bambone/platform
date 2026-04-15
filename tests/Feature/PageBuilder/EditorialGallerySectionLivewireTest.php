<?php

namespace Tests\Feature\PageBuilder;

use App\Livewire\Tenant\PageSectionsBuilder;
use App\PageBuilder\Blueprints\Expert\EditorialGalleryBlueprint;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenantFiles\TenantFileCatalogService;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use App\Tenant\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Редактор секции editorial_gallery: дефолты repeater, валидация по media_kind, подсказки embed.
 */
class EditorialGallerySectionLivewireTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    private const EG_UPLOAD_IMAGES = 'page-builder/editorial-gallery/images';

    private const EG_UPLOAD_VIDEOS = 'page-builder/editorial-gallery/videos';

    private const EG_UPLOAD_POSTERS = 'page-builder/editorial-gallery/posters';

    private function bindTenantContext(Tenant $tenant): void
    {
        $host = $this->tenancyHostForSlug((string) $tenant->slug);
        $domain = TenantDomain::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->app->instance(CurrentTenant::class, new CurrentTenant($tenant, $domain, false, $host));
    }

    private function homePage(Tenant $tenant): Page
    {
        return Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Главная',
            'slug' => 'home',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);
    }

    public function test_repeater_add_seeds_media_kind_image_and_source_new_tab_true(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-add', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery');

        $this->assertSame([], $lw->get('sectionFormData.data_json.items') ?? []);

        $lw->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $this->assertCount(1, $items);
        $row = $items[array_key_first($items)] ?? [];
        $this->assertSame('image', $row['media_kind'] ?? null);
        $this->assertTrue((bool) ($row['source_new_tab'] ?? false));
    }

    public function test_save_fails_when_image_row_has_empty_image_url(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-val-img', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'image');
        $lw->set('sectionFormData.data_json.items.'.$key.'.image_url', '');

        $lw->call('save')
            ->assertHasErrors();
    }

    public function test_save_fails_when_video_row_has_empty_video_url(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-val-vid', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video');
        $lw->set('sectionFormData.data_json.items.'.$key.'.video_url', '');

        $lw->call('save')
            ->assertHasErrors();
    }

    public function test_save_fails_when_video_embed_without_provider(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-val-emb', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', null);
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_share_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $lw->call('save')
            ->assertHasErrors();
    }

    public function test_save_fails_when_video_embed_share_url_invalid_for_provider(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-val-url', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', 'youtube');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_share_url', 'https://example.com/not-youtube');

        $lw->call('save')
            ->assertHasErrors();

        $errKey = 'sectionFormData.data_json.items.'.$key.'.embed_share_url';
        $msgs = $lw->errors()->get($errKey);
        $this->assertIsArray($msgs);
        $this->assertNotEmpty($msgs);
        $this->assertStringContainsString(
            EditorialGalleryBlueprint::embedShareUrlFailureMessage('youtube'),
            (string) ($msgs[0] ?? ''),
        );
    }

    public function test_editor_html_hides_embed_fields_for_image_kind(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-vis-img', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $html = $lw->html();
        $this->assertStringContainsString('Выберите файл из хранилища сайта или загрузите новый', $html);
        $this->assertStringNotContainsString('Где размещено видео', $html);
        $this->assertStringNotContainsString('Выберите видеофайл MP4 или WebM', $html);
    }

    public function test_editor_html_hides_image_and_embed_for_video_file_kind(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-vis-vid', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video');

        $html = $lw->html();
        $this->assertStringContainsString('Выберите видеофайл MP4 или WebM', $html);
        $this->assertStringContainsString('По желанию. Делает превью в сетке ровнее', $html);
        $this->assertStringNotContainsString('Выберите файл из хранилища сайта или загрузите новый', $html);
        $this->assertStringNotContainsString('Где размещено видео', $html);
    }

    public function test_editor_html_shows_embed_fields_for_video_embed_kind(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-vis-emb', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', 'vk');

        $html = $lw->html();
        $this->assertStringContainsString('Где размещено видео', $html);
        $this->assertStringContainsString('Ссылка на видео', $html);
        $this->assertStringContainsString('Для стабильного встраивания используйте', $html);
        $this->assertStringContainsString('video_ext.php', $html);
        $this->assertStringNotContainsString('Выберите файл из хранилища сайта или загрузите новый', $html);
        $this->assertStringNotContainsString('Выберите видеофайл MP4 или WebM', $html);
    }

    public function test_pick_video_sets_video_url_in_repeater_item(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('pb-eg-pick-vid', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);
        $diskKey = TenantStorage::forTrusted($tenant->id)->publicPath('site/'.self::EG_UPLOAD_VIDEOS.'/clip.mp4');
        Storage::disk(TenantStorageDisks::publicDiskName())->put($diskKey, 'fake-video');

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video');
        $abs = 'sectionFormData.data_json.items.'.$key.'.video_url';
        $lw->call('openTenantPublicFilePicker', $abs, TenantFileCatalogService::FILTER_VIDEOS)
            ->call('pickTenantPublicFile', $diskKey);

        $row = ($lw->get('sectionFormData.data_json.items') ?? [])[$key] ?? [];
        $this->assertSame($diskKey, $row['video_url'] ?? null);
    }

    public function test_upload_video_sets_video_url_in_repeater_item(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('pb-eg-up-vid', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video');
        $abs = 'sectionFormData.data_json.items.'.$key.'.video_url';
        $keyOut = $lw->call('prepareTenantPublicVideoUpload', $abs, self::EG_UPLOAD_VIDEOS)
            ->set('tenantPublicVideoUploadBuffer', UploadedFile::fake()->create('x.mp4', 40, 'video/mp4'))
            ->get('sectionFormData.data_json.items.'.$key.'.video_url');

        $this->assertIsString($keyOut);
        $this->assertStringEndsWith('.mp4', $keyOut);
        $this->assertTrue(Storage::disk(TenantStorageDisks::publicDiskName())->exists($keyOut));
        $this->assertStringContainsString(self::EG_UPLOAD_VIDEOS, $keyOut);
    }

    public function test_repeater_delete_sibling_preserves_video_url_on_other_item(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('pb-eg-del-sib', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);
        $diskKey = TenantStorage::forTrusted($tenant->id)->publicPath('site/'.self::EG_UPLOAD_VIDEOS.'/keep.mp4');
        Storage::disk(TenantStorageDisks::publicDiskName())->put($diskKey, 'fake');

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items'])
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $keys = array_keys($lw->get('sectionFormData.data_json.items') ?? []);
        $this->assertCount(2, $keys);
        sort($keys);
        $kKeep = $keys[0];
        $kDelete = $keys[1];

        $lw->set('sectionFormData.data_json.items.'.$kKeep.'.media_kind', 'video');
        $lw->call('openTenantPublicFilePicker', 'sectionFormData.data_json.items.'.$kKeep.'.video_url', TenantFileCatalogService::FILTER_VIDEOS)
            ->call('pickTenantPublicFile', $diskKey);
        $expected = $lw->get('sectionFormData.data_json.items.'.$kKeep.'.video_url');
        $this->assertSame($diskKey, $expected);

        $lw->call('mountAction', 'delete', ['item' => $kDelete], ['schemaComponent' => 'sectionEditor.data_json.items'])
            ->call('callMountedAction');

        $after = $lw->get('sectionFormData.data_json.items') ?? [];
        $this->assertCount(1, $after);
        $this->assertSame($diskKey, $after[(string) array_key_first($after)]['video_url'] ?? null);
    }

    public function test_pick_image_sets_image_url_in_repeater_item(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('pb-eg-pick-img', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);
        $diskKey = TenantStorage::forTrusted($tenant->id)->publicPath('site/'.self::EG_UPLOAD_IMAGES.'/hero.jpg');
        Storage::disk(TenantStorageDisks::publicDiskName())->put($diskKey, 'fake-img');

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $abs = 'sectionFormData.data_json.items.'.$key.'.image_url';
        $lw->call('openTenantPublicFilePicker', $abs, TenantFileCatalogService::FILTER_IMAGES)
            ->call('pickTenantPublicFile', $diskKey);

        $row = ($lw->get('sectionFormData.data_json.items') ?? [])[$key] ?? [];
        $this->assertSame($diskKey, $row['image_url'] ?? null);
    }

    public function test_upload_image_sets_image_url_in_repeater_item(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('pb-eg-up-img', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $abs = 'sectionFormData.data_json.items.'.$key.'.image_url';
        $keyOut = $lw->call('prepareTenantPublicImageUpload', $abs, self::EG_UPLOAD_IMAGES)
            ->set('tenantPublicImageUploadBuffer', UploadedFile::fake()->image('slide.jpg', 10, 10))
            ->get('sectionFormData.data_json.items.'.$key.'.image_url');

        $this->assertIsString($keyOut);
        $this->assertStringContainsString(self::EG_UPLOAD_IMAGES, $keyOut);
        $this->assertTrue(Storage::disk(TenantStorageDisks::publicDiskName())->exists($keyOut));
    }

    public function test_pick_poster_sets_poster_url_for_video_row(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('pb-eg-pick-poster', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);
        $diskKey = TenantStorage::forTrusted($tenant->id)->publicPath('site/'.self::EG_UPLOAD_POSTERS.'/cover.png');
        Storage::disk(TenantStorageDisks::publicDiskName())->put($diskKey, 'fake');

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video');
        $abs = 'sectionFormData.data_json.items.'.$key.'.poster_url';
        $lw->call('openTenantPublicFilePicker', $abs, TenantFileCatalogService::FILTER_IMAGES)
            ->call('pickTenantPublicFile', $diskKey);

        $row = ($lw->get('sectionFormData.data_json.items') ?? [])[$key] ?? [];
        $this->assertSame($diskKey, $row['poster_url'] ?? null);
    }

    public function test_upload_poster_sets_poster_url_for_video_row(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('pb-eg-up-poster', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video');
        $abs = 'sectionFormData.data_json.items.'.$key.'.poster_url';
        $keyOut = $lw->call('prepareTenantPublicImageUpload', $abs, self::EG_UPLOAD_POSTERS)
            ->set('tenantPublicImageUploadBuffer', UploadedFile::fake()->image('poster.png', 8, 8))
            ->get('sectionFormData.data_json.items.'.$key.'.poster_url');

        $this->assertIsString($keyOut);
        $this->assertStringContainsString(self::EG_UPLOAD_POSTERS, $keyOut);
        $this->assertTrue(Storage::disk(TenantStorageDisks::publicDiskName())->exists($keyOut));
    }

    public function test_switching_media_kind_does_not_clear_hidden_video_url(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-kind-persist', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $path = 'site/'.self::EG_UPLOAD_VIDEOS.'/kept.webm';
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video');
        $lw->set('sectionFormData.data_json.items.'.$key.'.video_url', $path);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'image');
        $this->assertSame($path, $lw->get('sectionFormData.data_json.items.'.$key.'.video_url'));
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video');
        $this->assertSame($path, $lw->get('sectionFormData.data_json.items.'.$key.'.video_url'));
    }

    public function test_switching_media_kind_preserves_manual_image_url(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-img-persist', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $path = 'site/'.self::EG_UPLOAD_IMAGES.'/manual.jpg';
        $lw->set('sectionFormData.data_json.items.'.$key.'.image_url', $path);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video');
        $this->assertSame($path, $lw->get('sectionFormData.data_json.items.'.$key.'.image_url'));
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'image');
        $this->assertSame($path, $lw->get('sectionFormData.data_json.items.'.$key.'.image_url'));
    }

    public function test_source_fields_hidden_until_source_url_filled(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-src-vis', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $base = $lw->html();
        $this->assertStringNotContainsString('Текст ссылки на источник', $base);
        $this->assertStringNotContainsString('Открывать источник в новой вкладке', $base);

        $lw->set('sectionFormData.data_json.items.'.$key.'.source_url', 'https://example.com/article');
        $after = $lw->html();
        $this->assertStringContainsString('Текст ссылки на источник', $after);
        $this->assertStringContainsString('Открывать источник в новой вкладке', $after);
    }

    public function test_source_new_tab_stays_true_when_source_toggle_becomes_visible(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-src-tab', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $this->assertTrue((bool) $lw->get('sectionFormData.data_json.items.'.$key.'.source_new_tab'));

        $lw->set('sectionFormData.data_json.items.'.$key.'.source_url', 'https://example.com/article');
        $this->assertTrue((bool) $lw->get('sectionFormData.data_json.items.'.$key.'.source_new_tab'));
        $this->assertStringContainsString('Открывать источник в новой вкладке', $lw->html());
    }

    public function test_media_picker_shows_manual_mode_control(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-manual', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $html = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items'])
            ->html();

        $this->assertStringContainsString('Указать вручную', $html);
        $this->assertStringContainsString('Выбрать из файлов', $html);
    }

    public function test_embed_share_helper_switches_with_youtube_provider(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-hlp-yt', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', 'youtube');

        $html = $lw->html();
        $this->assertStringContainsString('Вставьте обычную ссылку на видео.', $html);
        $this->assertStringNotContainsString('Для стабильного встраивания используйте', $html);
    }

    public function test_video_embed_editor_html_has_no_manual_poster_phrase(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-emb-no-man', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', 'youtube');

        $html = $lw->html();
        $this->assertStringNotContainsString('Указать вручную', $html);
        $this->assertStringContainsString('Добавить обложку', $html);
    }

    public function test_video_embed_poster_filled_shows_replace_and_delete(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-emb-repl', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', 'youtube');
        $lw->set('sectionFormData.data_json.items.'.$key.'.poster_url', 'https://example.com/poster.jpg');

        $html = $lw->html();
        $this->assertStringContainsString('Заменить', $html);
        $this->assertStringContainsString('Удалить', $html);
    }

    public function test_pick_poster_sets_poster_url_for_video_embed_row(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('pb-eg-pick-emb-poster', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);
        $diskKey = TenantStorage::forTrusted($tenant->id)->publicPath('site/'.self::EG_UPLOAD_POSTERS.'/embed-cover.png');
        Storage::disk(TenantStorageDisks::publicDiskName())->put($diskKey, 'fake');

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', 'youtube');
        $abs = 'sectionFormData.data_json.items.'.$key.'.poster_url';
        $lw->call('openTenantPublicFilePicker', $abs, TenantFileCatalogService::FILTER_IMAGES)
            ->call('pickTenantPublicFile', $diskKey);

        $row = ($lw->get('sectionFormData.data_json.items') ?? [])[$key] ?? [];
        $this->assertSame($diskKey, $row['poster_url'] ?? null);
    }

    public function test_upload_poster_sets_poster_url_for_video_embed_row(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('pb-eg-up-emb-poster', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', 'youtube');
        $abs = 'sectionFormData.data_json.items.'.$key.'.poster_url';
        $keyOut = $lw->call('prepareTenantPublicImageUpload', $abs, self::EG_UPLOAD_POSTERS)
            ->set('tenantPublicImageUploadBuffer', UploadedFile::fake()->image('emb-poster.png', 8, 8))
            ->get('sectionFormData.data_json.items.'.$key.'.poster_url');

        $this->assertIsString($keyOut);
        $this->assertStringContainsString(self::EG_UPLOAD_POSTERS, $keyOut);
        $this->assertTrue(Storage::disk(TenantStorageDisks::publicDiskName())->exists($keyOut));
    }

    public function test_switching_media_kind_preserves_poster_between_embed_and_image(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-poster-persist', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $poster = 'https://example.com/keep-poster.jpg';
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', 'youtube');
        $lw->set('sectionFormData.data_json.items.'.$key.'.poster_url', $poster);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'image');
        $this->assertSame($poster, $lw->get('sectionFormData.data_json.items.'.$key.'.poster_url'));
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $this->assertSame($poster, $lw->get('sectionFormData.data_json.items.'.$key.'.poster_url'));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function vkEmbedUrlsMissingHashProvider(): iterable
    {
        yield 'vk_com_watch' => ['https://vk.com/video-231646483_456239036'];
        yield 'vkvideo_watch' => ['https://vkvideo.ru/video-231646483_456239036'];
        yield 'video_ext_no_hash' => ['https://vk.com/video_ext.php?oid=-231646483&id=456239036&hd=2'];
    }

    #[DataProvider('vkEmbedUrlsMissingHashProvider')]
    public function test_save_fails_when_vk_embed_url_missing_hash(string $badUrl): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-vk-no-hash-'.md5($badUrl), ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', 'vk');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_share_url', $badUrl);

        $lw->call('save')
            ->assertHasErrors();

        $errKey = 'sectionFormData.data_json.items.'.$key.'.embed_share_url';
        $msgs = $lw->errors()->get($errKey);
        $this->assertIsArray($msgs);
        $this->assertNotEmpty($msgs);
        $this->assertStringContainsString(
            EditorialGalleryBlueprint::embedShareUrlVkMissingHashMessage(),
            (string) ($msgs[0] ?? ''),
        );
    }

    public function test_save_persists_vkvideo_ru_video_ext_with_hash(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-vkvid-hash', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $embedUrl = 'https://vkvideo.ru/video_ext.php?oid=-231646483&id=456239036&hash=0b8e847a6291fad3';

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', 'vk');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_share_url', $embedUrl);

        $lw->call('save')
            ->assertHasNoErrors();

        $section = PageSection::query()
            ->where('page_id', $page->id)
            ->where('section_type', 'editorial_gallery')
            ->orderByDesc('id')
            ->firstOrFail();

        $stored = is_array($section->data_json) ? ($section->data_json['items'] ?? []) : [];
        $row = $stored[array_key_first($stored)] ?? [];
        $this->assertSame($embedUrl, $row['embed_share_url'] ?? null);
    }

    public function test_save_keeps_vk_com_video_ext_url_with_hash_unchanged(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-eg-vk-ext-hash', ['theme_key' => 'advocate_editorial']);
        $this->bindTenantContext($tenant);
        $page = $this->homePage($tenant);

        $embedUrl = 'https://vk.com/video_ext.php?oid=-231646483&id=456239036&hash=deadbeef&hd=2';

        $lw = Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'editorial_gallery')
            ->call('mountAction', 'add', [], ['schemaComponent' => 'sectionEditor.data_json.items']);

        $items = $lw->get('sectionFormData.data_json.items') ?? [];
        $key = (string) array_key_first($items);
        $lw->set('sectionFormData.data_json.items.'.$key.'.media_kind', 'video_embed');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_provider', 'vk');
        $lw->set('sectionFormData.data_json.items.'.$key.'.embed_share_url', $embedUrl);

        $lw->call('save')
            ->assertHasNoErrors();

        $section = PageSection::query()
            ->where('page_id', $page->id)
            ->where('section_type', 'editorial_gallery')
            ->orderByDesc('id')
            ->firstOrFail();

        $stored = is_array($section->data_json) ? ($section->data_json['items'] ?? []) : [];
        $row = $stored[array_key_first($stored)] ?? [];
        $this->assertSame($embedUrl, $row['embed_share_url'] ?? null);
    }
}
