<?php

namespace App\MediaPresentation;

use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use App\MediaPresentation\Profiles\ServiceProgramCardPresentationProfile;
use App\Support\Storage\TenantPublicAssetResolver;

/**
 * Resolves source URLs, focal points, and overlay vars for {@code service_program_card} slot.
 * Single place for public site + Filament preview (production-faithful).
 */
final class ServiceProgramCardPresentationResolver
{
    /**
     * Build inline style fragment for program card article (CSS variables: focal + overlay from profile).
     */
    public function articleStyleAttribute(TenantServiceProgram $program): string
    {
        $mobile = $this->resolveFocalPointDetails($program, ViewportKey::Mobile);
        $desktop = $this->resolveFocalPointDetails($program, ViewportKey::Desktop);

        $parts = [
            '--svc-program-focal-x-mobile: '.$mobile['focal']->x.'%',
            '--svc-program-focal-y-mobile: '.$mobile['focal']->y.'%',
            '--svc-program-focal-x-desktop: '.$desktop['focal']->x.'%',
            '--svc-program-focal-y-desktop: '.$desktop['focal']->y.'%',
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

        $details = $this->resolveFocalPointDetails($program, $viewport);
        $focal = $details['focal'];
        $legacyUsed = $details['legacyUsed'];

        $overlay = $viewport === ViewportKey::Desktop
            ? ServiceProgramCardPresentationProfile::overlayVariablesDesktop()
            : ServiceProgramCardPresentationProfile::overlayVariablesMobile();

        $safe = ServiceProgramCardPresentationProfile::safeAreaBottomBand();

        return new ResolvedPresentation(
            resolvedSourceUrl: $resolvedUrl,
            resolvedFocal: $focal,
            overlayCssVariables: $overlay,
            activeViewportKey: $viewport,
            safeAreaMeta: $safe,
            missingSource: $missingSource,
            fallbackSourceUsed: $fallbackSourceUsed,
            legacyFocalUsed: $legacyUsed,
        );
    }

    /**
     * Focal only (no asset URLs) — used by article inline style and by {@see resolveFocal}.
     *
     * @return array{focal: FocalPoint, legacyUsed: bool}
     */
    private function resolveFocalPointDetails(TenantServiceProgram $program, ViewportKey $viewport): array
    {
        $slotId = ServiceProgramCardPresentationProfile::SLOT_ID;

        $presentation = $program->cover_presentation_json;
        $map = $presentation instanceof PresentationData ? $presentation->viewportFocalMap : [];

        $legacyUsed = false;
        $focal = $this->pickFocalFromMap($map, $viewport);
        if ($focal === null) {
            $legacy = LegacyCoverObjectPositionParser::parse($program->cover_object_position);
            if ($legacy !== null) {
                $focal = $legacy;
                $legacyUsed = true;
            }
        }
        if ($focal === null) {
            $focal = MediaPresentationRegistry::defaultFocalForSlot($slotId, $viewport);
        }

        return ['focal' => $focal, 'legacyUsed' => $legacyUsed];
    }

    /**
     * @param  array<string, array{x: float, y: float}>  $map
     */
    private function pickFocalFromMap(array $map, ViewportKey $viewport): ?FocalPoint
    {
        $order = match ($viewport) {
            ViewportKey::Tablet => ['tablet', 'mobile', 'default'],
            ViewportKey::Mobile => ['mobile', 'default'],
            ViewportKey::Desktop => ['desktop', 'default'],
            ViewportKey::Default => ['default'],
        };
        foreach ($order as $k) {
            if (! isset($map[$k])) {
                continue;
            }
            $fp = FocalPoint::tryFromArray($map[$k]);
            if ($fp !== null) {
                return $fp;
            }
        }

        return null;
    }
}
