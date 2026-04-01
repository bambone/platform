<?php

namespace Tests\Feature\Tenant;

use App\Models\Motorcycle;
use App\Models\User;
use App\Tenant\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantSpatieMediaStreamTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_spatie_media_stream(): void
    {
        $tenant = $this->createTenantWithActiveDomain('mediagu');
        $host = $this->tenancyHostForSlug('mediagu');

        $this->app->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Guest Bike',
            'slug' => 'guest-bike',
            'price_per_day' => 1000,
            'status' => 'available',
        ]);

        $media = $bike->addMediaFromString('test-bytes')
            ->usingFileName('cover.jpg')
            ->toMediaCollection('cover');

        $this->get('http://'.$host.'/admin/spatie-media/'.$media->id)
            ->assertRedirect();
    }

    public function test_authenticated_tenant_user_gets_cover_bytes(): void
    {
        $tenant = $this->createTenantWithActiveDomain('mediaok');
        $host = $this->tenancyHostForSlug('mediaok');

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $this->app->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner Bike',
            'slug' => 'owner-bike',
            'price_per_day' => 1000,
            'status' => 'available',
        ]);

        $payload = 'jpeg-bytes-simulated';
        $media = $bike->addMediaFromString($payload)
            ->usingFileName('cover.jpg')
            ->toMediaCollection('cover');

        $response = $this->actingAs($user)
            ->get('http://'.$host.'/admin/spatie-media/'.$media->id);

        $response->assertOk();
        $this->assertStringContainsString($payload, $response->streamedContent());
    }
}
