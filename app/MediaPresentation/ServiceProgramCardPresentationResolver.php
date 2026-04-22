<?php

namespace App\MediaPresentation;

use App\MediaPresentation\Profiles\ServiceProgramCardPresentationProfile;
use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use App\Support\Storage\TenantPublicAssetResolver;

/**
 * Resolves source URLs, focal points, and overlay vars for {@code service_program_card} slot.
 * Single place for public site + Filament preview (production-faithful).
 *
 * Public + Filament preview (same CSS variables and composition):
 * {@code img}: {@code object-fit: cover} + {@code object-position} from focal (x%, y%).
 * {@code .expert-program-card__media-layer}: {@code transform: scale(var(--svc-program-scale-*))}
 * with {@code transform-origin} at those focal percentages. Нижняя маска — на {@code .expert-program-card__media}, не на слое zoom.
 * Drag/clamp в админке используют {@see FocalCoverPreviewGeometry} (translate в пикселях); при необходимости точной идентичности
 * каждого кадра при zoom сверяйте превью с публичной карточкой на тех же данных.
 */
final class ServiceProgramCardPresentationResolver
{
    /**
     * Build inline style fragment for program card article (CSS variables: focal + scale + overlay from profile).
     */
    public function articleStyleAttribute(TenantServiceProgram $program): string
    {
        $mobile = $this->resolveFramingDetails($program, ViewportKey::Mobile);
        $desktop = $this->resolveFramingDetails($program, ViewportKey::Desktop);
        $dims = ServiceProgramCardPresentationProfile::mediaAspectDimensionsForServiceCard(
            $mobile['heightFactor'],
            $desktop['heightFactor'],
        );

        $parts = [
            '--svc-program-focal-x-mobile: '.$mobile['focal']->x.'%',
            '--svc-program-focal-y-mobile: '.$mobile['focal']->y.'%',
            '--svc-program-focal-x-desktop: '.$desktop['focal']->x.'%',
            '--svc-program-focal-y-desktop: '.$desktop['focal']->y.'%',
            '--svc-program-scale-mobile: '.$mobile['scale'],
            '--svc-program-scale-desktop: '.$desktop['scale'],
            '--svc-program-media-aspect-w-mobile: '.$dims['w_mobile'],
            '--svc-program-media-aspect-h-mobile: '.$dims['h_mobile'],
            '--svc-program-media-aspect-w-desktop: '.$dims['w_desktop'],
            '--svc-program-media-aspect-h-desktop: '.$dims['h_desktop'],
        ];
        foreach (ServiceProgramCardPresentationProfile::articleOverlayCssVariables() as $name => $value) {
            $parts[] = '--'.$name.': '.$value;
        }

        return implode('; ', $parts);
    }

    /**
     * Resolve focal + source for a viewport (preview or single-frame checks).
     */
    public function resolveForViewport(
        TenantServiceProgram $program,
        ViewportKey $viewport,
        Tenant $tenant,
    ): ResolvedPresentation {
        return $this->resolveFocal($program, $viewport, $tenant);
    }

    private function resolveFocal(
        TenantServiceProgram $program,
        ViewportKey $viewport,
        Tenant $tenant,
    ): ResolvedPresentation {
        $desktopUrl = TenantPublicAssetResolver::resolveForTenantModel(
            trim((string) $program->cover_image_ref) !== '' ? trim((string) $program->cover_image_ref) : null,
            $tenant
        );
        $mobileRefUrl = TenantPublicAssetResolver::resolveForTenantModel(
            trim((string) $program->cover_mobile_ref) !== '' ? trim((string) $program->cover_mobile_ref) : null,
            $tenant
        );

        $fallbackSourceUsed = false;

        $useMobileUrl = $viewport === ViewportKey::Mobile
            || $viewport === ViewportKey::Tablet;

        $resolvedUrl = null;
        if ($useMobileUrl) {
            if ($mobileRefUrl !== null) {
                $resolvedUrl = $mobileRefUrl;
            } else {
                if (trim((string) $program->cover_mobile_ref) !== '') {
                    $fallbackSourceUsed = true;
                }
                $resolvedUrl = $desktopUrl;
            }
        } else {
            $resolvedUrl = $desktopUrl;
        }

        $missingSource = $resolvedUrl === null;

        $details = $this->resolveFramingDetails($program, $viewport);
        $focal = $details['focal'];
        $scale = $details['scale'];
        $legacyUsed = $details['legacyUsed'];

        $overlay = $viewport === ViewportKey::Desktop
            ? ServiceProgramCardPresentationProfile::overlayVariablesDesktop()
            : ServiceProgramCardPresentationProfile::overlayVariablesMobile();

        $safe = ServiceProgramCardPresentationProfile::safeAreaBottomBand();

        return new ResolvedPresentation(
            resolvedSourceUrl: $resolvedUrl,
            resolvedFocal: $focal,
            resolvedUserScale: $scale,
            overlayCssVariables: $overlay,
            activeViewportKey: $viewport,
            safeAreaMeta: $safe,
            missingSource: $missingSource,
            fallbackSourceUsed: $fallbackSourceUsed,
            legacyFocalUsed: $legacyUsed,
        );
    }

    /**
     * Framing (focal + user scale + media height factor) — used by article inline style and by {@see resolveFocal}.
     *
     * @return array{focal: FocalPoint, scale: float, heightFactor: float, legacyUsed: bool}
     */
    private function resolveFramingDetails(TenantServiceProgram $program, ViewportKey $viewport): array
    {
        $slotId = ServiceProgramCardPresentationProfile::SLOT_ID;

        $presentation = $program->cover_presentation_json;
        $map = $presentation instanceof PresentationData ? $presentation->viewportFocalMap : [];

        $legacyUsed = false;
        $framing = FocalMapViewport::pickFramingFromMap($map, $viewport);
        if ($framing !== null) {
            return [
                'focal' => $framing->toFocalPoint(),
                'scale' => $framing->scale,
                'heightFactor' => $framing->heightFactor,
                'legacyUsed' => false,
            ];
        }

        $legacy = LegacyCoverObjectPositionParser::parse($program->cover_object_position);
        if ($legacy !== null) {
            return [
                'focal' => $legacy,
                'scale' => 1.0,
                'heightFactor' => ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_DEFAULT,
                'legacyUsed' => true,
            ];
        }

        $focal = MediaPresentationRegistry::defaultFocalForSlot($slotId, $viewport);

        return [
            'focal' => $focal,
            'scale' => 1.0,
            'heightFactor' => ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_DEFAULT,
            'legacyUsed' => false,
        ];
    }
}
