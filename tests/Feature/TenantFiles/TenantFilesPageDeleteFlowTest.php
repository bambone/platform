<?php

declare(strict_types=1);

namespace Tests\Feature\TenantFiles;

use App\Filament\Tenant\Pages\TenantFilesPage;
use App\Jobs\RecalculateTenantStorageUsageJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CurrentTenantManager;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class TenantFilesPageDeleteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function actingOwnerOnTenant(Tenant $tenant): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(CurrentTenantManager::class)->setTenant($tenant);

        return $user;
    }

    public function test_delete_is_rejected_for_themes_path_and_file_remains(): void
    {
        $this->withoutVite();
        Storage::fake(TenantStorageDisks::publicDiskName());

        $tenant = Tenant::query()->create([
            'name' => 'Th',
            'slug' => 'tft-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        $this->actingOwnerOnTenant($tenant);

        $themesKey = TenantStorage::forTrusted($tid)->publicPath('themes/expert_auto/section-bg.png');
        Storage::disk(TenantStorageDisks::publicDiskName())->put($themesKey, 'z');

        Livewire::test(TenantFilesPage::class)
            ->call('deleteFile', $themesKey);

        Storage::disk(TenantStorageDisks::publicDiskName())->assertExists($themesKey);
    }

    public function test_delete_succeeds_for_site_path_without_references_and_dispatches_quota_job(): void
    {
        $this->withoutVite();
        Storage::fake(TenantStorageDisks::publicDiskName());
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Site',
            'slug' => 'tfs-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        $this->actingOwnerOnTenant($tenant);

        $key = TenantStorage::forTrusted($tid)->publicPath('site/brand/deletable-'.substr(uniqid(), -8).'.jpg');
        Storage::disk(TenantStorageDisks::publicDiskName())->put($key, 'bytes');

        $component = Livewire::test(TenantFilesPage::class)
            ->call('deleteFile', $key);

        Storage::disk(TenantStorageDisks::publicDiskName())->assertMissing($key);

        $this->assertSame(0, $component->instance()->fileCatalogTotal);
        Queue::assertPushed(RecalculateTenantStorageUsageJob::class, static fn (RecalculateTenantStorageUsageJob $job): bool => $job->tenantId === $tid);
    }

    public function test_after_delete_file_page_clamps_to_last_page(): void
    {
        $this->withoutVite();
        Storage::fake(TenantStorageDisks::publicDiskName());
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Pag',
            'slug' => 'tfp-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        $this->actingOwnerOnTenant($tenant);

        $disk = TenantStorageDisks::publicDiskName();
        $k1 = TenantStorage::forTrusted($tid)->publicPath('site/brand/p1-'.substr(uniqid(), -6).'.txt');
        $k2 = TenantStorage::forTrusted($tid)->publicPath('site/brand/p2-'.substr(uniqid(), -6).'.txt');
        $k3 = TenantStorage::forTrusted($tid)->publicPath('site/brand/p3-'.substr(uniqid(), -6).'.txt');
        Storage::disk($disk)->put($k1, '1');
        Storage::disk($disk)->put($k2, '2');
        Storage::disk($disk)->put($k3, '3');

        Livewire::test(TenantFilesPage::class)
            ->set('filesPerPage', 1)
            ->set('filePage', 3)
            ->call('deleteFile', $k3)
            ->assertSet('filePage', 2)
            ->assertSet('filesPerPage', 1);
    }

    public function test_page_still_renders_after_successful_delete(): void
    {
        $this->withoutVite();
        Storage::fake(TenantStorageDisks::publicDiskName());
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Ok',
            'slug' => 'tfo-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        $this->actingOwnerOnTenant($tenant);

        $key = TenantStorage::forTrusted($tid)->publicPath('site/brand/ok-'.substr(uniqid(), -8).'.gif');
        Storage::disk(TenantStorageDisks::publicDiskName())->put($key, 'g');

        Livewire::test(TenantFilesPage::class)
            ->call('deleteFile', $key)
            ->assertOk();
    }
}
