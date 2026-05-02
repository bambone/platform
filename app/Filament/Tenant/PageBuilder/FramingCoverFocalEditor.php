<?php

namespace App\Filament\Tenant\PageBuilder;

use App\MediaPresentation\Contracts\SlotPresentationProfileInterface;
use App\MediaPresentation\Profiles\PageHeroCoverPresentationProfile;
use App\MediaPresentation\Profiles\ServiceProgramCardPresentationProfile;
use App\MediaPresentation\ViewportFraming;
use App\MediaPresentation\ViewportKey;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Reusable Filament {@see ViewField} for framing preview + {@see PresentationData} wire paths (Page Builder + programs).
 */
final class FramingCoverFocalEditor
{
    public static function focalPreviewFitForSlotId(string $slotId): string
    {
        return $slotId === PageHeroCoverPresentationProfile::SLOT_ID
            ? 'height_fit'
            : 'cover';
    }

    /**
     * @param  callable(Get):(?string)  $resolveDesktopImageUrl  Absolute URL for desktop frame source
     * @param  callable(Get):(?string)  $resolveMobileImageUrl  Absolute URL for mobile/tablet frames (fallback to desktop)
     * @param  (callable(Get): bool)|null  $resolveSyncDefault  Sync all viewports in preview (if null: {@code data_json.hero_focal_sync_all_viewports}, legacy {@code hero_focal_sync_mobile_desktop})
     */
    public static function make(
        string $name,
        string $presentationStateKey,
        SlotPresentationProfileInterface $profile,
        string $wirePathPrefix,
        callable $resolveDesktopImageUrl,
        callable $resolveMobileImageUrl,
        ?callable $resolveSyncDefault = null,
    ): ViewField {
        $slotId = $profile->slotId();

        return ViewField::make($name)
            ->hiddenLabel()
            ->view('filament.forms.components.service-program-cover-preview')
            ->viewData(function (Get $get) use ($name, $profile, $wirePathPrefix, $presentationStateKey, $resolveDesktopImageUrl, $resolveMobileImageUrl, $resolveSyncDefault, $slotId): array {
                $t = currentTenant();
                $frames = $profile->previewFrames();
                $safeArea = $profile->safeAreaBottomBand();
                $cover = $get($presentationStateKey) ?? [];
                $map = is_array($cover['viewport_focal_map'] ?? null) ? $cover['viewport_focal_map'] : [];
                $sMin = $profile->framingScaleMin();
                $sMax = $profile->framingScaleMax();
                $sStep = $profile->framingScaleStep();
                $mobileFr = ViewportFraming::fromArray(is_array($map['mobile'] ?? null) ? $map['mobile'] : null, $sMin, $sMax, $sStep);
                $tabletFr = ViewportFraming::fromArray(is_array($map['tablet'] ?? null) ? $map['tablet'] : null, $sMin, $sMax, $sStep);
                $desktopFr = ViewportFraming::fromArray(is_array($map['desktop'] ?? null) ? $map['desktop'] : null, $sMin, $sMax, $sStep);
                $defM = $profile->defaultFocalForViewport(ViewportKey::Mobile);
                $defT = $profile->defaultFocalForViewport(ViewportKey::Tablet);
                $defD = $profile->defaultFocalForViewport(ViewportKey::Desktop);
                $mx = $mobileFr ? $mobileFr->x : $defM->x;
                $my = $mobileFr ? $mobileFr->y : $defM->y;
                $ms = $mobileFr ? $mobileFr->scale : $profile->framingScaleDefault();
                $mhf = $mobileFr ? $mobileFr->heightFactor : 1.0;
                $tx = $tabletFr ? $tabletFr->x : $defT->x;
                $ty = $tabletFr ? $tabletFr->y : $defT->y;
                $ts = $tabletFr ? $tabletFr->scale : $profile->framingScaleDefault();
                $dx = $desktopFr ? $desktopFr->x : $defD->x;
                $dy = $desktopFr ? $desktopFr->y : $defD->y;
                $ds = $desktopFr ? $desktopFr->scale : $profile->framingScaleDefault();
                $dhf = $desktopFr ? $desktopFr->heightFactor : 1.0;
                $tenantId = $t ? (int) $t->id : 0;
                $desktopUrl = $tenantId !== 0 ? $resolveDesktopImageUrl($get) : null;
                $mobileUrl = $tenantId !== 0 ? $resolveMobileImageUrl($get) : null;
                if (($mobileUrl === null || $mobileUrl === '') && $desktopUrl) {
                    $mobileUrl = $desktopUrl;
                }

                $isHeroSlot = $slotId === PageHeroCoverPresentationProfile::SLOT_ID;
                $mobileSourceLabel = $isHeroSlot
                    ? 'Фон hero (один URL для всех ширин)'
                    : 'Обложка · mobile/tablet';
                $desktopSourceLabel = $isHeroSlot
                    ? 'Фон hero (один URL для всех ширин)'
                    : 'Обложка · desktop';

                $tiles = [];
                foreach ($frames as $frame) {
                    $key = (string) ($frame['key'] ?? '');
                    $isDesktop = $key === 'desktop';
                    $isTablet = $key === 'tablet';
                    if ($isDesktop) {
                        $fx = $dx;
                        $fy = $dy;
                        $src = $desktopUrl;
                        $sourceLabel = $desktopSourceLabel;
                    } elseif ($isTablet) {
                        $fx = $tx;
                        $fy = $ty;
                        $src = $mobileUrl;
                        $sourceLabel = $mobileSourceLabel.' · планшет (768–1023px)';
                    } else {
                        $fx = $mx;
                        $fy = $my;
                        $src = $mobileUrl;
                        $sourceLabel = $mobileSourceLabel;
                    }
                    $tiles[] = [
                        'key' => $key,
                        'label' => (string) ($frame['label'] ?? $key),
                        'width' => (int) ($frame['width'] ?? 200),
                        'height' => (int) ($frame['height'] ?? 120),
                        'fx' => $fx,
                        'fy' => $fy,
                        'src' => $src,
                        'editable' => $isDesktop || $isTablet || $key === 'mobile',
                        'sourceLabel' => $sourceLabel,
                    ];
                }
                $staticPreviewFrames = [];
                foreach ($tiles as $t) {
                    $k = (string) ($t['key'] ?? '');
                    if ($k !== '') {
                        $staticPreviewFrames[$k] = [
                            'w' => (int) ($t['width'] ?? 200),
                            'h' => (int) ($t['height'] ?? 120),
                        ];
                    }
                }

                $syncDefault = $resolveSyncDefault !== null
                    ? (bool) $resolveSyncDefault($get)
                    : (bool) ($get('data_json.hero_focal_sync_all_viewports') ?? $get('data_json.hero_focal_sync_mobile_desktop') ?? false);
                /**
                 * Стабильный ключ DOM / sessionStorage: без focal map, zoom и sync — иначе при каждом commit Livewire
                 * или переключении «Синхронизировать» менялся wire:key → remount Alpine и активная вкладка слетала.
                 */
                $viewComponentKey = hash('sha256', (string) json_encode([
                    $tenantId,
                    $wirePathPrefix,
                    $name,
                    $desktopUrl,
                    $mobileUrl,
                    $slotId,
                ], JSON_UNESCAPED_UNICODE));
                $previewKey = hash('sha256', (string) json_encode([
                    $viewComponentKey,
                    $slotId,
                    $desktopUrl,
                    $mobileUrl,
                    $map,
                ], JSON_UNESCAPED_UNICODE));

                $editorConfig = [
                    'mobile' => ['x' => $mx, 'y' => $my, 's' => $ms, 'heightFactor' => $mhf],
                    'tablet' => ['x' => $tx, 'y' => $ty, 's' => $ts, 'heightFactor' => $mhf],
                    'desktop' => ['x' => $dx, 'y' => $dy, 's' => $ds, 'heightFactor' => $dhf],
                    'defaults' => [
                        'mobile' => [
                            'x' => $defM->x,
                            'y' => $defM->y,
                            's' => $profile->framingScaleDefault(),
                            'heightFactor' => 1.0,
                        ],
                        'tablet' => [
                            'x' => $defT->x,
                            'y' => $defT->y,
                            's' => $profile->framingScaleDefault(),
                            'heightFactor' => 1.0,
                        ],
                        'desktop' => [
                            'x' => $defD->x,
                            'y' => $defD->y,
                            's' => $profile->framingScaleDefault(),
                            'heightFactor' => 1.0,
                        ],
                    ],
                    'staticPreviewFrames' => $staticPreviewFrames,
                    'scaleMin' => $profile->framingScaleMin(),
                    'scaleMax' => $profile->framingScaleMax(),
                    'scaleStep' => $profile->framingScaleStep(),
                    'heightFactorMin' => $slotId === PageHeroCoverPresentationProfile::SLOT_ID
                        ? 1.0
                        : ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_MIN,
                    'heightFactorMax' => $slotId === PageHeroCoverPresentationProfile::SLOT_ID
                        ? 1.0
                        : ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_MAX,
                    'heightFactorStep' => $slotId === PageHeroCoverPresentationProfile::SLOT_ID
                        ? 1.0
                        : ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_STEP,
                    'wirePathPrefix' => $wirePathPrefix,
                    'slotId' => $slotId,
                    'syncDefault' => $syncDefault,
                    'safeAreaBottomPercent' => (float) ($profile->safeAreaBottomBand()['bottomPercent'] ?? 38),
                    'safeAreaLabel' => (string) ($safeArea['label'] ?? ''),
                    'viewportStorageId' => $viewComponentKey,
                    /**
                     * Hero (page_hero_cover): height_fit / contain alignment with public CSS.
                     * Program cards: cover — must not inherit hero-only preview geometry.
                     */
                    'focalPreviewFit' => self::focalPreviewFitForSlotId($slotId),
                ];

                return [
                    'previewEngine' => 'simulated',
                    'tiles' => $tiles,
                    'safeArea' => $safeArea,
                    'editorConfig' => $editorConfig,
                    'previewKey' => $previewKey,
                    'viewComponentKey' => $viewComponentKey,
                    'overlayMobile' => self::overlayMobileForPreview($profile),
                    'overlayDesktop' => self::overlayDesktopForPreview($profile),
                ];
            })
            ->columnSpanFull();
    }

    /**
     * @return array<string, string>
     */
    private static function overlayMobileForPreview(SlotPresentationProfileInterface $profile): array
    {
        $vars = $profile->articleOverlayCssVariables();
        $mobile = [];
        foreach ($vars as $k => $v) {
            if (str_ends_with((string) $k, '-mobile')) {
                $base = substr((string) $k, 0, -strlen('-mobile'));
                $mobile[$base] = $v;
            }
        }

        return $mobile !== [] ? $mobile : ['svc-program-mask-fade-start' => '78%', 'svc-program-mask-fade-mid' => '90%'];
    }

    /**
     * @return array<string, string>
     */
    private static function overlayDesktopForPreview(SlotPresentationProfileInterface $profile): array
    {
        $vars = $profile->articleOverlayCssVariables();
        $desktop = [];
        foreach ($vars as $k => $v) {
            if (str_ends_with((string) $k, '-desktop')) {
                $base = substr((string) $k, 0, -strlen('-desktop'));
                $desktop[$base] = $v;
            }
        }

        return $desktop !== [] ? $desktop : ['svc-program-mask-fade-start' => '80%', 'svc-program-mask-fade-mid' => '91%'];
    }
}
