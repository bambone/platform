<?php

declare(strict_types=1);

namespace Tests\Feature\TenantFiles;

use App\Filament\Tenant\Pages\TenantFilesPage;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CurrentTenantManager;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Сценарии уровня страницы: доступ, UI «Только просмотр» для themes, блокировка delete при ссылках.
 */
final class TenantFilesPageTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_http_get_admin_tenant_files_is_forbidden_without_manage_tenant_files(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $tenant = $this->createTenantWithActiveDomain('tfiles403', [
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
        ]);
        $host = $this->tenancyHostForSlug('tfiles403');
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'content_manager', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($user)
            ->call('GET', 'http://'.$host.'/admin/tenant-files')
            ->assertForbidden();
    }

    public function test_http_get_admin_tenant_files_is_ok_for_tenant_owner(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->withoutVite();

        $tenant = $this->createTenantWithActiveDomain('tfiles200', [
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
        ]);
        $host = $this->tenancyHostForSlug('tfiles200');
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake(TenantStorageDisks::publicDiskName());

        $this->actingAs($user)
            ->call('GET', 'http://'.$host.'/admin/tenant-files')
            ->assertOk();
    }

    public function test_livewire_mount_tenant_files_page_is_forbidden_without_manage_tenant_files(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $tenant = Tenant::query()->create([
            'name' => 'Lw403',
            'slug' => 'tfw-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'content_manager', 'status' => 'active']);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(TenantFilesPage::class)
            ->assertForbidden();
    }

    public function test_themes_file_row_shows_read_only_label_not_delete(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->withoutVite();
        Storage::fake(TenantStorageDisks::publicDiskName());

        $tenant = Tenant::query()->create([
            'name' => 'Ro',
            'slug' => 'tfr-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        $user = User::factory()->create();
        $user->tenants()->attach($tid, ['role' => 'tenant_owner', 'status' => 'active']);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(CurrentTenantManager::class)->setTenant($tenant);

        $path = TenantStorage::forTrusted($tid)->publicPath('themes/expert_auto/bg.png');
        Storage::disk(TenantStorageDisks::publicDiskName())->put($path, 'b');

        Livewire::test(TenantFilesPage::class)
            ->assertSee(__('Только просмотр'), false, false)
            ->assertDontSee('wire:click="deleteFile', false, false);
    }

    public function test_site_file_row_includes_delete_action(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->withoutVite();
        Storage::fake(TenantStorageDisks::publicDiskName());

        $tenant = Tenant::query()->create([
            'name' => 'Del',
            'slug' => 'tfd-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        $user = User::factory()->create();
        $user->tenants()->attach($tid, ['role' => 'tenant_owner', 'status' => 'active']);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(CurrentTenantManager::class)->setTenant($tenant);

        $path = TenantStorage::forTrusted($tid)->publicPath('site/brand/del-'.substr(uniqid(), -6).'.png');
        Storage::disk(TenantStorageDisks::publicDiskName())->put($path, 'x');

        Livewire::test(TenantFilesPage::class)
            ->assertSee(__('Удалить'), false, false);
    }

    public function test_delete_rejected_in_ui_when_references_found_file_remains(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->withoutVite();
        Storage::fake(TenantStorageDisks::publicDiskName());

        $tenant = Tenant::query()->create([
            'name' => 'Ref',
            'slug' => 'tfr2-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;

        $key = TenantStorage::forTrusted($tid)->publicPath('site/brand/blocked-'.substr(uniqid(), -6).'.jpg');
        Storage::disk(TenantStorageDisks::publicDiskName())->put($key, 'x');

        $page = Page::query()->create([
            'tenant_id' => $tid,
            'name' => 'H',
            'slug' => 'h',
            'template' => 'default',
            'status' => 'published',
        ]);
        PageSection::query()->create([
            'tenant_id' => $tid,
            'page_id' => $page->id,
            'section_key' => 'c',
            'section_type' => 'rich_text',
            'data_json' => [
                'content' => 'Ref '.$key,
            ],
            'sort_order' => 1,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $user = User::factory()->create();
        $user->tenants()->attach($tid, ['role' => 'tenant_owner', 'status' => 'active']);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(TenantFilesPage::class)
            ->call('deleteFile', $key);

        Storage::disk(TenantStorageDisks::publicDiskName())->assertExists($key);
    }
}
