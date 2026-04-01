<?php

namespace Tests\Feature\Tenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantPublicStorageFileTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_serves_file_when_tenant_id_matches_host_tenant(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rb-pub-'.uniqid('', true);
        mkdir($root, 0777, true);
        config(['filesystems.disks.public.root' => $root]);

        $tenant = $this->createTenantWithActiveDomain('storfile');
        $relative = 'tenants/'.$tenant->id.'/public/site/marketing/hero.txt';
        $fullPath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        mkdir(dirname($fullPath), 0777, true);
        file_put_contents($fullPath, 'hello');

        $host = $this->tenancyHostForSlug('storfile');
        $url = '/storage/tenants/'.$tenant->id.'/public/site/marketing/hero.txt';

        $response = $this->get('http://'.$host.$url);
        $response->assertOk();
        $this->assertSame('hello', $response->streamedContent());
    }

    public function test_rejects_wrong_tenant_id_in_path(): void
    {
        Storage::fake('public');

        $tenantA = $this->createTenantWithActiveDomain('stora');
        $tenantB = $this->createTenantWithActiveDomain('storb');

        $relative = 'tenants/'.$tenantB->id.'/public/x.txt';
        Storage::disk('public')->put($relative, 'secret');

        $host = $this->tenancyHostForSlug('stora');
        $url = '/storage/tenants/'.$tenantB->id.'/public/x.txt';

        $this->get('http://'.$host.$url)->assertForbidden();
    }

    public function test_path_traversal_returns_not_found(): void
    {
        Storage::fake('public');

        $tenant = $this->createTenantWithActiveDomain('storpt');
        Storage::disk('public')->put('tenants/'.$tenant->id.'/public/a.txt', 'a');

        $host = $this->tenancyHostForSlug('storpt');

        $this->get('http://'.$host.'/storage/tenants/'.$tenant->id.'/public/../'.$tenant->id.'/public/a.txt')
            ->assertNotFound();
    }
}
