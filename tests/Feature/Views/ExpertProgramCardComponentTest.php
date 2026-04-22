<?php

namespace Tests\Feature\Views;

use App\MediaPresentation\PresentationData;
use App\Models\TenantServiceProgram;
use App\Tenant\Expert\ServiceProgramType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class ExpertProgramCardComponentTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_forced_picture_mode_auto_renders_picture_element_when_mobile_differs(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expert-card-pix', ['theme_key' => 'expert_auto']);
        $program = TenantServiceProgram::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'c1',
            'title' => 'C1',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'sort_order' => 1,
            'cover_image_ref' => 'https://example.com/wide.jpg',
            'cover_mobile_ref' => 'https://example.com/tall.jpg',
            'cover_presentation_json' => (new PresentationData(2, [
                'mobile' => ['x' => 50.0, 'y' => 50.0, 'scale' => 1.0],
                'desktop' => ['x' => 50.0, 'y' => 50.0, 'scale' => 1.0],
            ]))->toArray(),
        ]);

        $html = Blade::render(
            '<x-tenant.expert_auto.expert-program-card :program="$program" :tenant="$tenant" forced-picture-mode="auto" />',
            ['program' => $program, 'tenant' => $tenant]
        );

        $this->assertStringContainsString('<picture', $html);
        $this->assertStringContainsString('tall.jpg', $html);
        $this->assertStringContainsString('wide.jpg', $html);
    }

    public function test_forced_mobile_uses_single_img_with_mobile_url(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expert-card-m', ['theme_key' => 'expert_auto']);
        $program = TenantServiceProgram::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'c2',
            'title' => 'C2',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'sort_order' => 1,
            'cover_image_ref' => 'https://example.com/only-d.jpg',
            'cover_mobile_ref' => 'https://example.com/only-m.jpg',
            'cover_presentation_json' => (new PresentationData(2, []))->toArray(),
        ]);

        $html = Blade::render(
            '<x-tenant.expert_auto.expert-program-card :program="$program" :tenant="$tenant" forced-picture-mode="mobile" />',
            ['program' => $program, 'tenant' => $tenant]
        );

        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringContainsString('only-m.jpg', $html);
        $this->assertStringNotContainsString('only-d.jpg', $html);
    }

    public function test_forced_desktop_uses_desktop_url_only(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expert-card-d', ['theme_key' => 'expert_auto']);
        $program = TenantServiceProgram::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'c3',
            'title' => 'C3',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'sort_order' => 1,
            'cover_image_ref' => 'https://example.com/d-only.jpg',
            'cover_mobile_ref' => 'https://example.com/m-only.jpg',
            'cover_presentation_json' => (new PresentationData(2, []))->toArray(),
        ]);

        $html = Blade::render(
            '<x-tenant.expert_auto.expert-program-card :program="$program" :tenant="$tenant" forced-picture-mode="desktop" />',
            ['program' => $program, 'tenant' => $tenant]
        );

        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringContainsString('d-only.jpg', $html);
        $this->assertStringNotContainsString('m-only.jpg', $html);
    }

    public function test_forced_mobile_falls_back_to_desktop_when_mobile_ref_empty(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expert-card-fb', ['theme_key' => 'expert_auto']);
        $program = TenantServiceProgram::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'c4',
            'title' => 'C4',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'sort_order' => 1,
            'cover_image_ref' => 'https://example.com/solo.jpg',
            'cover_mobile_ref' => '',
            'cover_presentation_json' => (new PresentationData(2, []))->toArray(),
        ]);

        $html = Blade::render(
            '<x-tenant.expert_auto.expert-program-card :program="$program" :tenant="$tenant" forced-picture-mode="mobile" />',
            ['program' => $program, 'tenant' => $tenant]
        );

        $this->assertStringContainsString('solo.jpg', $html);
    }
}
