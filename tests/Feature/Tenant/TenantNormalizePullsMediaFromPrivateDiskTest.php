<?php

namespace Tests\Feature\Tenant;

use App\Models\Motorcycle;
use App\Support\MediaLibrary\TenantMediaStoragePaths;
use App\Support\Storage\TenantStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantNormalizePullsMediaFromPrivateDiskTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_normalize_copies_spatie_files_from_local_private_tree_to_public_disk(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['media-library.disk_name' => 'public']);

        $tenant = $this->createTenantWithActiveDomain('mediapull');
        $moto = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pull Test Bike',
            'slug' => 'pull-test-bike-'.Str::random(8),
            'status' => 'available',
        ]);

        $media = Media::query()->create([
            'model_type' => Motorcycle::class,
            'model_id' => $moto->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'cover',
            'name' => 'cover',
            'file_name' => 'honda.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 4,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $legacyKey = TenantMediaStoragePaths::legacyFlatBasePath($media).'/honda.jpg';
        Storage::disk('local')->put($legacyKey, 'fake');

        $target = TenantMediaStoragePaths::canonicalPublicMediaBase($media).'/honda.jpg';

        $this->assertFalse(Storage::disk('public')->exists($target));

        Artisan::call('tenant:normalize-storage');

        $this->assertTrue(Storage::disk('public')->exists($target));
        $this->assertFalse(Storage::disk('local')->exists(TenantMediaStoragePaths::legacyFlatBasePath($media)));
    }

    public function test_normalize_updates_disk_when_media_was_local(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['media-library.disk_name' => 'public']);

        $tenant = $this->createTenantWithActiveDomain('medialocal');
        $moto = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Local Disk Bike',
            'slug' => 'local-disk-bike-'.Str::random(8),
            'status' => 'available',
        ]);

        $media = Media::query()->create([
            'model_type' => Motorcycle::class,
            'model_id' => $moto->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'cover',
            'name' => 'cover',
            'file_name' => 'x.png',
            'mime_type' => 'image/png',
            'disk' => 'local',
            'conversions_disk' => 'local',
            'size' => 3,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $legacyKey = TenantMediaStoragePaths::legacyFlatBasePath($media).'/x.png';
        Storage::disk('local')->put($legacyKey, 'png');

        Artisan::call('tenant:normalize-storage');

        $media->refresh();
        $this->assertSame('public', $media->disk);
        $this->assertSame('public', $media->conversions_disk);

        $expected = TenantStorage::forTrusted((int) $tenant->id)->publicPath('media/'.$media->id.'/x.png');
        $this->assertTrue(Storage::disk('public')->exists($expected));
    }

    public function test_repair_moves_public_media_site_tree_into_public_site(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['media-library.disk_name' => 'public']);

        $tenant = $this->createTenantWithActiveDomain('nestfix');
        $wrong = 'tenants/'.$tenant->id.'/public/media/site/marketing/hero-bg.png';
        Storage::disk('public')->put($wrong, 'png');

        Artisan::call('tenant:normalize-storage', [
            '--skip-branding' => true,
            '--skip-seo' => true,
        ]);

        $right = 'tenants/'.$tenant->id.'/public/site/marketing/hero-bg.png';
        $this->assertTrue(Storage::disk('public')->exists($right));
        $this->assertFalse(Storage::disk('public')->exists($wrong));
    }

    public function test_align_copies_main_file_from_wrong_media_id_folder(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['media-library.disk_name' => 'public']);

        $tenant = $this->createTenantWithActiveDomain('alignfix');
        $moto = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Align Bike',
            'slug' => 'align-bike-'.Str::random(8),
            'status' => 'available',
        ]);

        $media = Media::query()->create([
            'model_type' => Motorcycle::class,
            'model_id' => $moto->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'cover',
            'name' => 'cover',
            'file_name' => 'cover.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 3,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        Storage::disk('public')->put('tenants/'.$tenant->id.'/public/media/888/cover.jpg', 'jpg');

        Artisan::call('tenant:normalize-storage', [
            '--skip-branding' => true,
            '--skip-seo' => true,
        ]);

        $canonical = TenantMediaStoragePaths::canonicalPublicMediaBase($media).'/cover.jpg';
        $this->assertTrue(Storage::disk('public')->exists($canonical));
    }
}
