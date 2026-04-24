<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use App\Tenant\BlackDuck\BlackDuckCaseStudyCardsFiller;
use App\Tenant\BlackDuck\BlackDuckMediaCatalog;
use App\Tenant\CurrentTenant;
use App\Tenant\Expert\ExpertBrandMediaUrl;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Tenant\BlackDuckBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BlackDuckFillCaseStudyCardsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['tenancy.central_domains' => ['localhost', '127.0.0.1']]);
        Cache::flush();
        $this->seed(RolePermissionSeeder::class);
        (new BlackDuckBootstrap)->run();
    }

    public function test_fill_case_study_cards_dry_run_and_apply(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $host = (string) DB::table('tenant_domains')->where('tenant_id', $tid)->value('host');
        $this->assertNotSame('', $host);

        $src = storage_path('framework/testing/bd-case-study-src-'.uniqid());
        if (! is_dir($src)) {
            mkdir($src, 0777, true);
        }
        $this->assertDirectoryExists($src);

        $jpg = self::minimalJpegBytes();
        $ts = TenantStorage::forTrusted($tid);
        foreach (BlackDuckCaseStudyCardsFiller::PRIMARY_BASENAMES as $base) {
            $this->assertTrue(file_put_contents($src.DIRECTORY_SEPARATOR.$base, $jpg) > 0);
            $logical = 'site/uploads/page-builder/case-study/'.$base;
            $this->assertTrue($ts->putPublic($logical, $jpg, ['ContentType' => 'image/webp', 'visibility' => 'public']));
        }

        $exitDry = Artisan::call('tenant:black-duck:fill-case-study-cards', [
            'tenant' => BlackDuckBootstrap::SLUG,
            '--source-dir' => $src,
        ]);
        $this->assertSame(0, $exitDry, Artisan::output());

        $rabotyPageId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'raboty')->value('id');
        $jsonBefore = (string) DB::table('page_sections')
            ->where('tenant_id', $tid)
            ->where('page_id', $rabotyPageId)
            ->where('section_key', 'case_list')
            ->value('data_json');

        $exitApply = Artisan::call('tenant:black-duck:fill-case-study-cards', [
            'tenant' => BlackDuckBootstrap::SLUG,
            '--source-dir' => $src,
            '--apply' => true,
        ]);
        $this->assertSame(0, $exitApply, Artisan::output());

        $data = json_decode(
            (string) DB::table('page_sections')
                ->where('tenant_id', $tid)
                ->where('page_id', $rabotyPageId)
                ->where('section_key', 'case_list')
                ->value('data_json'),
            true,
        );
        $this->assertIsArray($data);
        $this->assertCount(10, $data['items'] ?? []);
        $this->assertSame('Тёмный кузов', (string) ($data['items'][0]['vehicle'] ?? ''));
        $this->assertStringContainsString('yandex_maps_172.webp', (string) ($data['items'][0]['image_url'] ?? ''));

        $this->assertNotSame($jsonBefore, json_encode($data, JSON_UNESCAPED_UNICODE));

        $this->app->instance(CurrentTenant::class, new CurrentTenant(Tenant::query()->findOrFail($tid)));
        $this->assertNotSame(
            '',
            ExpertBrandMediaUrl::resolve((string) ($data['items'][0]['image_url'] ?? '')),
            'image_url must resolve in tenant context',
        );

        $html = (string) $this->call('GET', 'http://'.$host.'/raboty')->getContent();
        $this->assertStringContainsString('Полировка кузова и визуальное восстановление блеска', $html);
        $this->assertStringContainsString('bd-section', $html);
    }

    public function test_refresh_content_force_does_not_wipe_case_list_filled_outside_media_catalog(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $catalogKey = TenantStorage::forTrusted($tid)->publicPath(BlackDuckMediaCatalog::CATALOG_LOGICAL);
        $diskName = TenantStorageDisks::publicDiskName();
        if (Storage::disk($diskName)->exists($catalogKey)) {
            Storage::disk($diskName)->delete($catalogKey);
        }
        Artisan::call('tenant:black-duck:refresh-content', [
            'tenant' => BlackDuckBootstrap::SLUG,
            '--force' => true,
        ]);

        $src = storage_path('framework/testing/bd-case-study-src-'.uniqid());
        if (! is_dir($src)) {
            mkdir($src, 0777, true);
        }
        $jpg = self::minimalJpegBytes();
        $ts = TenantStorage::forTrusted($tid);
        foreach (BlackDuckCaseStudyCardsFiller::PRIMARY_BASENAMES as $base) {
            $this->assertTrue(file_put_contents($src.DIRECTORY_SEPARATOR.$base, $jpg) > 0);
            $logical = 'site/uploads/page-builder/case-study/'.$base;
            $this->assertTrue($ts->putPublic($logical, $jpg, ['ContentType' => 'image/webp', 'visibility' => 'public']));
        }

        $exitApply = Artisan::call('tenant:black-duck:fill-case-study-cards', [
            'tenant' => BlackDuckBootstrap::SLUG,
            '--source-dir' => $src,
            '--apply' => true,
        ]);
        $this->assertSame(0, $exitApply, Artisan::output());

        $exitRefresh = Artisan::call('tenant:black-duck:refresh-content', [
            'tenant' => BlackDuckBootstrap::SLUG,
            '--force' => true,
        ]);
        $this->assertSame(0, $exitRefresh, Artisan::output());

        $rabotyPageId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'raboty')->value('id');
        $data = json_decode(
            (string) DB::table('page_sections')
                ->where('tenant_id', $tid)
                ->where('page_id', $rabotyPageId)
                ->where('section_key', 'case_list')
                ->value('data_json'),
            true,
        );
        $this->assertIsArray($data);
        $this->assertCount(10, $data['items'] ?? []);
        $this->assertTrue((bool) DB::table('page_sections')
            ->where('tenant_id', $tid)
            ->where('page_id', $rabotyPageId)
            ->where('section_key', 'case_list')
            ->value('is_visible'));
    }

    /**
     * @return non-empty-string
     */
    private static function minimalJpegBytes(): string
    {
        if (extension_loaded('gd')) {
            $img = imagecreatetruecolor(1, 1);
            if ($img !== false) {
                imagecolorallocate($img, 10, 20, 30);
                ob_start();
                imagejpeg($img);
                imagedestroy($img);
                $b = (string) ob_get_clean();
                if ($b !== '') {
                    return $b;
                }
            }
        }

        return (string) base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAoHBwgHBgoICAgLCgoLDhgQDg0NDh0VFhEYIx8lJCIfIiEmKzcvJik0KSEiMEExNDk7Pj4+JS5ESUM8SDc9Pjv/2wBDAQoLCw4NDhwQEBw7KCIoOzs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozv/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=',
            true,
        );
    }
}
