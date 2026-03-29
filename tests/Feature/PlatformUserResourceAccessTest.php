<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformUserResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_platform_owner_can_open_edit_page_for_another_platform_user(): void
    {
        $editor = User::factory()->create(['status' => 'active']);
        $editor->assignRole('platform_owner');

        $peer = User::factory()->create(['status' => 'active']);
        $peer->assignRole('platform_admin');

        $this->actingAs($editor)
            ->get('http://platform.apex.test/platform/platform-users/'.$peer->id.'/edit')
            ->assertOk();
    }
}
