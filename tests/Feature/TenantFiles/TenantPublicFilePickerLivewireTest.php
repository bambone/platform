<?php

namespace Tests\Feature\TenantFiles;

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
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantPublicFilePickerLivewireTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    private function bindCurrentTenant(Tenant $tenant): void
    {
        $host = $this->tenancyHostForSlug((string) $tenant->slug);
        $domain = TenantDomain::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->app->instance(CurrentTenant::class, new CurrentTenant($tenant, $domain, false, $host));
    }

    public function test_pick_from_catalog_sets_nested_object_key_in_state(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('tfpick');
        $disk = TenantStorageDisks::publicDiskName();
        $key = TenantStorage::forTrusted($tenant->id)->publicPath('site/page-builder/x.jpg');
        Storage::disk($disk)->put($key, 'fake-binary');

        $this->bindCurrentTenant($tenant);

        Livewire::test(TenantPublicFilePickerTestHost::class)
            ->call('openTenantPublicFilePicker', 'sectionFormData.data_json.background_image', TenantFileCatalogService::FILTER_ALL)
            ->assertSet('tenantPublicFilePickerOpen', true)
            ->call('pickTenantPublicFile', $key)
            ->assertSet('tenantPublicFilePickerOpen', false)
            ->assertSet('sectionFormData.data_json.background_image', $key);
    }

    public function test_clear_clears_nested_field(): void
    {
        $tenant = $this->createTenantWithActiveDomain('tfclr');
        $this->bindCurrentTenant($tenant);
        $key = TenantStorage::forTrusted($tenant->id)->publicPath('site/page-builder/y.png');

        Livewire::test(TenantPublicFilePickerTestHost::class)
            ->set('sectionFormData.data_json.background_image', $key)
            ->call('clearTenantPublicImageField', 'sectionFormData.data_json.background_image')
            ->assertSet('sectionFormData.data_json.background_image', '');
    }

    public function test_upload_writes_file_and_sets_object_key(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('tfup');
        $this->bindCurrentTenant($tenant);

        $key = Livewire::test(TenantPublicFilePickerTestHost::class)
            ->call('prepareTenantPublicImageUpload', 'sectionFormData.data_json.background_image', 'page-builder')
            ->set('tenantPublicImageUploadBuffer', UploadedFile::fake()->image('new.jpg', 10, 10))
            ->get('sectionFormData.data_json.background_image');

        $this->assertIsString($key);
        $this->assertStringStartsWith('tenants/'.$tenant->id.'/public/site/page-builder/', $key);
        $this->assertTrue(Storage::disk(TenantStorageDisks::publicDiskName())->exists($key));
    }

    public function test_upload_video_writes_mp4_object_key(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('tfupvid');
        $this->bindCurrentTenant($tenant);

        $key = Livewire::test(TenantPublicFilePickerTestHost::class)
            ->call('prepareTenantPublicVideoUpload', 'sectionFormData.data_json.background_image', 'page-builder')
            ->set('tenantPublicVideoUploadBuffer', UploadedFile::fake()->create('clip.mp4', 50, 'video/mp4'))
            ->get('sectionFormData.data_json.background_image');

        $this->assertIsString($key);
        $this->assertStringEndsWith('.mp4', $key);
        $this->assertTrue(Storage::disk(TenantStorageDisks::publicDiskName())->exists($key));
    }
}
