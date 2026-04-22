<?php

namespace Tests\Feature\MediaPresentation;

use App\Filament\Tenant\Support\TenantServiceProgramFormPreview;
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
            'cover_presentation_json' => (new PresentationData(2, [
                'mobile' => ['x' => 40.0, 'y' => 60.0, 'scale' => 1.25],
                'desktop' => ['x' => 55.0, 'y' => 45.0, 'scale' => 1.1],
            ]))->toArray(),
        ]);

        $resolver = app(ServiceProgramCardPresentationResolver::class);
        $style = $resolver->articleStyleAttribute($program);

        $this->assertStringContainsString('--svc-program-focal-x-mobile: 40%', $style);
        $this->assertStringContainsString('--svc-program-focal-y-mobile: 60%', $style);
        $this->assertStringContainsString('--svc-program-focal-x-desktop: 55%', $style);
        $this->assertStringContainsString('--svc-program-focal-y-desktop: 45%', $style);
        $this->assertStringContainsString('--svc-program-scale-mobile: 1.25', $style);
        $this->assertStringContainsString('--svc-program-scale-desktop: 1.1', $style);
        $this->assertStringContainsString('--svc-program-mask-fade-start-mobile: 78%', $style);
        $this->assertStringContainsString('--svc-program-mask-fade-mid-desktop: 91%', $style);
        $this->assertStringContainsString('--svc-program-media-aspect-w-mobile: 3', $style);
        $this->assertStringContainsString('--svc-program-media-aspect-h-mobile: 2.2', $style);
        $this->assertStringContainsString('--svc-program-media-aspect-w-desktop: 2.1', $style);
        $this->assertStringContainsString('--svc-program-media-aspect-h-desktop: 1.1', $style);
    }

    public function test_article_style_scales_media_height_by_height_factor(): void
    {
        $tenant = $this->createTenantWithActiveDomain('svc-hf-test', ['theme_key' => 'expert_auto']);
        $program = TenantServiceProgram::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'test-hf',
            'title' => 'Test',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'sort_order' => 1,
            'cover_image_ref' => 'https://example.com/d.jpg',
            'cover_presentation_json' => (new PresentationData(2, [
                'mobile' => ['x' => 50.0, 'y' => 50.0, 'scale' => 1.0, 'height_factor' => 2.0],
                'desktop' => ['x' => 50.0, 'y' => 50.0, 'scale' => 1.0, 'height_factor' => 1.5],
            ]))->toArray(),
        ]);

        $style = app(ServiceProgramCardPresentationResolver::class)->articleStyleAttribute($program);

        $this->assertStringContainsString('--svc-program-media-aspect-h-mobile: 4.4', $style);
        $this->assertStringContainsString('--svc-program-media-aspect-h-desktop: 1.65', $style);
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
        $this->assertSame(1.0, $r->resolvedUserScale);
    }

    public function test_article_style_matches_transient_form_preview_model_with_same_data(): void
    {
        $tenant = $this->createTenantWithActiveDomain('svc-form-bridge', ['theme_key' => 'expert_auto']);
        $presentation = (new PresentationData(2, [
            'mobile' => ['x' => 41.0, 'y' => 61.0, 'scale' => 1.2, 'height_factor' => 1.1],
            'desktop' => ['x' => 52.0, 'y' => 48.0, 'scale' => 1.05, 'height_factor' => 1.2],
        ]))->toArray();

        $saved = TenantServiceProgram::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'bridge-prog',
            'title' => 'Bridge',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => true,
            'sort_order' => 1,
            'cover_image_ref' => 'https://example.com/d.jpg',
            'cover_mobile_ref' => 'https://example.com/m.jpg',
            'audience_json' => ['a', 'b'],
            'outcomes_json' => ['o'],
            'teaser' => 'Teaser',
            'description' => 'Desc',
            'price_prefix' => 'от',
            'price_amount' => 5000_00,
            'format_label' => 'Онлайн',
            'duration_label' => '2 ч',
            'cover_presentation_json' => $presentation,
        ]);

        $formState = [
            'id' => $saved->id,
            'slug' => 'bridge-prog',
            'title' => 'Bridge',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => true,
            'sort_order' => 1,
            'cover_image_ref' => 'https://example.com/d.jpg',
            'cover_mobile_ref' => 'https://example.com/m.jpg',
            'audience_json' => [['text' => 'a'], ['text' => 'b']],
            'outcomes_json' => [['text' => 'o']],
            'teaser' => 'Teaser',
            'description' => 'Desc',
            'price_prefix' => 'от',
            'price_amount' => $saved->price_amount,
            'format_label' => 'Онлайн',
            'duration_label' => '2 ч',
            'cover_presentation' => $presentation,
        ];
        $transient = TenantServiceProgramFormPreview::makeFromFormStateArray($formState, $tenant, $saved->id);

        $r = app(ServiceProgramCardPresentationResolver::class);
        $this->assertSame(
            $r->articleStyleAttribute($saved),
            $r->articleStyleAttribute($transient),
        );
    }
}
