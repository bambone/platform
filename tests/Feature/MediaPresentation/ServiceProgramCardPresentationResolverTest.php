<?php

namespace Tests\Feature\MediaPresentation;

use App\MediaPresentation\PresentationData;
use App\MediaPresentation\ServiceProgramCardPresentationResolver;
use App\MediaPresentation\ViewportKey;
use App\Models\TenantServiceProgram;
use App\Tenant\Expert\ServiceProgramType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class ServiceProgramCardPresentationResolverTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_article_style_contains_mobile_and_desktop_focal_percentages(): void
    {
        $tenant = $this->createTenantWithActiveDomain('svc-pres-test', ['theme_key' => 'expert_auto']);
        $program = TenantServiceProgram::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'test-program',
            'title' => 'Test',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'sort_order' => 1,
            'cover_image_ref' => 'https://example.com/d.jpg',
            'cover_presentation_json' => (new PresentationData(1, [
                'mobile' => ['x' => 40.0, 'y' => 60.0],
                'desktop' => ['x' => 55.0, 'y' => 45.0],
            ]))->toArray(),
        ]);

        $resolver = app(ServiceProgramCardPresentationResolver::class);
        $style = $resolver->articleStyleAttribute($program);

        $this->assertStringContainsString('--svc-program-focal-x-mobile: 40%', $style);
        $this->assertStringContainsString('--svc-program-focal-y-mobile: 60%', $style);
        $this->assertStringContainsString('--svc-program-focal-x-desktop: 55%', $style);
        $this->assertStringContainsString('--svc-program-focal-y-desktop: 45%', $style);
        $this->assertStringContainsString('--svc-program-mask-fade-start-mobile: 52%', $style);
        $this->assertStringContainsString('--svc-program-mask-fade-mid-desktop: 72%', $style);
    }

    public function test_resolve_for_viewport_uses_legacy_when_presentation_empty(): void
    {
        $tenant = $this->createTenantWithActiveDomain('svc-legacy-test', ['theme_key' => 'expert_auto']);
        $program = TenantServiceProgram::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'legacy',
            'title' => 'Legacy',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'sort_order' => 1,
            'cover_object_position' => 'center 72%',
            'cover_image_ref' => 'https://example.com/x.jpg',
        ]);

        $resolver = app(ServiceProgramCardPresentationResolver::class);
        $r = $resolver->resolveForViewport($program, ViewportKey::Mobile, $tenant);

        $this->assertTrue($r->legacyFocalUsed);
        $this->assertSame(50.0, $r->resolvedFocal->x);
        $this->assertSame(72.0, $r->resolvedFocal->y);
    }
}
