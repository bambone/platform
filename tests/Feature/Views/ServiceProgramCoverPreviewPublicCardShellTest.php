<?php

namespace Tests\Feature\Views;

use App\Filament\Tenant\Support\ServiceProgramCoverPreviewViewDataFactory;
use App\MediaPresentation\PresentationData;
use App\Services\CurrentTenantManager;
use App\Tenant\Expert\ServiceProgramType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Контракт: shell превью в режиме public_card — та же разметка карточки, CTA-заглушка, scoping-класс.
 */
final class ServiceProgramCoverPreviewPublicCardShellTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_factory_public_card_mode_and_resolves_preview_data(): void
    {
        $tenant = $this->createTenantWithActiveDomain('svc-prev-shell', ['theme_key' => 'expert_auto']);
        app(CurrentTenantManager::class)->setTenant($tenant);

        $presentation = (new PresentationData(2, [
            'mobile' => ['x' => 50.0, 'y' => 50.0, 'scale' => 1.0, 'height_factor' => 1.0],
            'desktop' => ['x' => 50.0, 'y' => 50.0, 'scale' => 1.0, 'height_factor' => 1.0],
            'tablet' => ['x' => 50.0, 'y' => 50.0, 'scale' => 1.0],
        ]))->toArray();

        $data = ServiceProgramCoverPreviewViewDataFactory::fromGetter(
            function (string $key) use ($presentation): mixed {
                return match ($key) {
                    'id' => 42,
                    'slug' => 'test-program',
                    'title' => 'Shell Тест',
                    'teaser' => 'Короткий',
                    'description' => 'Полный текст',
                    'program_type' => ServiceProgramType::Program->value,
                    'duration_label' => '10 ч',
                    'format_label' => 'Онлайн',
                    'price_amount' => '1000',
                    'price_prefix' => 'от',
                    'is_featured' => false,
                    'is_visible' => true,
                    'sort_order' => 0,
                    'cover_image_ref' => 'https://example.com/cover-d.jpg',
                    'cover_mobile_ref' => 'https://example.com/cover-m.jpg',
                    'cover_image_alt' => 'Alt',
                    'cover_presentation' => $presentation,
                    'cover_focal_sync_mobile_desktop' => true,
                    'audience_json' => [['text' => 'Пункт аудитории']],
                    'outcomes_json' => [['text' => 'Пункт результата']],
                    default => null,
                };
            }
        );

        $this->assertSame('public_card', $data['previewEngine']);
        $this->assertIsArray($data['editorConfig']);
        $this->assertSame('public_card', $data['editorConfig']['previewEngine']);
        $this->assertTrue($data['editorConfig']['allowFocalDrag'] ?? false);
        $this->assertArrayHasKey('showFocalSafeAreaOverlay', $data['editorConfig']);
        $this->assertFalse($data['editorConfig']['showFocalSafeAreaOverlay']);
        $this->assertNotNull($data['previewProgram']);
        $this->assertArrayHasKey('previewKey', $data);
        $this->assertArrayHasKey('viewComponentKey', $data);
        $this->assertNotSame($data['previewKey'], $data['viewComponentKey'] ?? null, 'viewComponentKey must stay stable when focal is excluded from it');
    }

    public function test_public_card_partial_renders_expert_card_with_static_cta_and_scoped_class(): void
    {
        $tenant = $this->createTenantWithActiveDomain('svc-prev-ui', ['theme_key' => 'expert_auto']);
        app(CurrentTenantManager::class)->setTenant($tenant);

        $presentation = (new PresentationData(2, [
            'mobile' => ['x' => 50.0, 'y' => 50.0, 'scale' => 1.0, 'height_factor' => 1.0],
            'desktop' => ['x' => 50.0, 'y' => 50.0, 'scale' => 1.0, 'height_factor' => 1.0],
            'tablet' => ['x' => 50.0, 'y' => 50.0, 'scale' => 1.0],
        ]))->toArray();

        $factoryData = ServiceProgramCoverPreviewViewDataFactory::fromGetter(
            function (string $key) use ($presentation): mixed {
                return match ($key) {
                    'slug' => 'p1',
                    'title' => 'UI Shell',
                    'teaser' => 'Тизер',
                    'description' => 'Описание',
                    'program_type' => ServiceProgramType::Program->value,
                    'duration_label' => '',
                    'format_label' => '',
                    'price_amount' => null,
                    'price_prefix' => '',
                    'is_featured' => true,
                    'is_visible' => true,
                    'sort_order' => 0,
                    'cover_image_ref' => 'https://example.com/hero.jpg',
                    'cover_mobile_ref' => '',
                    'cover_image_alt' => '',
                    'cover_presentation' => $presentation,
                    'cover_focal_sync_mobile_desktop' => true,
                    'audience_json' => [],
                    'outcomes_json' => [],
                    default => null,
                };
            }
        );

        $field = new class
        {
            public function getId(): string
            {
                return 'test-cover-field';
            }
        };

        $html = view('filament.forms.components._service-program-cover-preview-public-card', array_merge(
            $factoryData,
            ['field' => $field]
        ))->render();

        $this->assertStringContainsString('rb-expert-card-wysiwyg', $html);
        $this->assertStringContainsString('expert-program-card', $html);
        $this->assertStringContainsString('data-svc-focal-preview', $html);
        $this->assertStringContainsString('serviceProgramCoverFocalEditor', $html, 'focal editor bootstrap present');
        $this->assertStringContainsString('cursor-default', $html, 'preview CTA is non-interactive');
        $this->assertStringContainsString('aria-hidden="true"', $html, 'static CTA for preview');
    }
}
