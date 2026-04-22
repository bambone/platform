<?php

namespace App\Filament\Tenant\Support;

use App\Filament\Tenant\Resources\TenantServiceProgramResource;
use App\MediaPresentation\Profiles\ServiceProgramCardPresentationProfile;
use App\MediaPresentation\ServiceProgramCardPresentationResolver;
use App\MediaPresentation\ViewportFraming;
use App\MediaPresentation\ViewportKey;
use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use App\Support\Storage\TenantPublicAssetResolver;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;

/**
 * Сборка данных для {@see ViewField} «Обложка» (WYSIWYG карточки + focal editor).
 * Вынесено из {@see TenantServiceProgramResource}, чтобы ресурс оставался тонким.
 */
final class ServiceProgramCoverPreviewViewDataFactory
{
    /**
     * @return array{
     *     previewEngine: string,
     *     tenant: ?Tenant,
     *     previewProgram: ?TenantServiceProgram,
     *     articleStyle: string,
     *     hasPaneMedia: bool,
     *     tabletFocalSummary: string,
     *     tiles: list<array<string, mixed>>,
     *     safeArea: array<string, mixed>,
     *     editorConfig: array<string, mixed>,
     *     previewKey: string,
     *     viewComponentKey: string,
     *     overlayMobile: array<string, mixed>,
     *     overlayDesktop: array<string, mixed>,
     * }
     */
    public static function make(Get $get): array
    {
        return self::fromGetter(static fn (string $key) => $get($key));
    }

    /**
     * Та же логика, что {@see self::make}, но с произвольным getter — удобно в тестах без Filament-контекста.
     *
     * @param  callable(string): mixed  $get
     */
    public static function fromGetter(callable $get): array
    {
        $t = currentTenant();
        $frames = ServiceProgramCardPresentationProfile::previewFrames();
        $safeArea = ServiceProgramCardPresentationProfile::safeAreaBottomBand();
        $cover = $get('cover_presentation') ?? [];
        $map = is_array($cover['viewport_focal_map'] ?? null) ? $cover['viewport_focal_map'] : [];
        $mobileFr = ViewportFraming::fromArray(is_array($map['mobile'] ?? null) ? $map['mobile'] : null);
        $tabletFr = ViewportFraming::fromArray(is_array($map['tablet'] ?? null) ? $map['tablet'] : null);
        $desktopFr = ViewportFraming::fromArray(is_array($map['desktop'] ?? null) ? $map['desktop'] : null);
        $defM = ServiceProgramCardPresentationProfile::defaultFocalForViewport(ViewportKey::Mobile);
        $defT = ServiceProgramCardPresentationProfile::defaultFocalForViewport(ViewportKey::Tablet);
        $defD = ServiceProgramCardPresentationProfile::defaultFocalForViewport(ViewportKey::Desktop);
        $mx = $mobileFr ? $mobileFr->x : $defM->x;
        $my = $mobileFr ? $mobileFr->y : $defM->y;
        $ms = $mobileFr ? $mobileFr->scale : ServiceProgramCardPresentationProfile::FRAMING_SCALE_DEFAULT;
        $mhf = $mobileFr ? $mobileFr->heightFactor : ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_DEFAULT;
        $tx = $tabletFr ? $tabletFr->x : $defT->x;
        $ty = $tabletFr ? $tabletFr->y : $defT->y;
        $ts = $tabletFr ? $tabletFr->scale : ServiceProgramCardPresentationProfile::FRAMING_SCALE_DEFAULT;
        $dx = $desktopFr ? $desktopFr->x : $defD->x;
        $dy = $desktopFr ? $desktopFr->y : $defD->y;
        $ds = $desktopFr ? $desktopFr->scale : ServiceProgramCardPresentationProfile::FRAMING_SCALE_DEFAULT;
        $dhf = $desktopFr ? $desktopFr->heightFactor : ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_DEFAULT;
        $tenantId = $t ? (int) $t->id : 0;
        $desktopUrl = $tenantId !== 0
            ? TenantPublicAssetResolver::resolve(trim((string) ($get('cover_image_ref') ?? '')), $tenantId)
            : null;
        $mobileUrl = $tenantId !== 0
            ? TenantPublicAssetResolver::resolve(trim((string) ($get('cover_mobile_ref') ?? '')), $tenantId)
            : null;
        if (($mobileUrl === null || $mobileUrl === '') && $desktopUrl) {
            $mobileUrl = $desktopUrl;
        }

        $mobileSourceLabel = (trim((string) ($get('cover_mobile_ref') ?? '')) !== '')
            ? 'Мобильный файл'
            : 'Общий баннер (как на сайте)';
        $desktopSourceLabel = 'Баннер для компьютера';

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
                $sourceLabel = $mobileSourceLabel.' · планшет (768–1023px) · только превью; высота как у mobile';
            } else {
                $fx = $mx;
                $fy = $my;
                $src = $mobileUrl;
                $sourceLabel = $mobileSourceLabel;
            }
            $refW = (int) ($frame['width'] ?? 200);
            $wh = ServiceProgramCardPresentationProfile::serviceCardPreviewFrameSizeForKey(
                $key,
                $refW,
                $mhf,
                $dhf,
            );
            $tiles[] = [
                'key' => $key,
                'label' => (string) ($frame['label'] ?? $key),
                'width' => $wh['width'],
                'height' => $wh['height'],
                'fx' => $fx,
                'fy' => $fy,
                'src' => $src,
                'editable' => $isDesktop || $isTablet || $key === 'mobile',
                'sourceLabel' => $sourceLabel,
            ];
        }

        $syncDefault = (bool) ($get('cover_focal_sync_mobile_desktop') ?? true);
        $stableViewportKey = hash('sha256', (string) json_encode([
            $get('cover_image_ref'),
            $get('cover_mobile_ref'),
            $syncDefault,
        ]));
        $previewKey = hash('sha256', (string) json_encode([
            $stableViewportKey,
            $get('cover_presentation'),
            $get('title'),
            $get('is_featured'),
            $get('teaser'),
            $get('description'),
            $get('audience_json'),
            $get('outcomes_json'),
            $get('price_amount'),
            $get('price_prefix'),
            $get('format_label'),
            $get('duration_label'),
            $get('program_type'),
            $get('cover_image_alt'),
        ]));

        /** Без кадра в hash: иначе при каждом commit focal менялся wire:key — сбрасывался Alpine (табы вьюпорта). */
        $viewComponentKey = hash('sha256', (string) json_encode([
            is_numeric($get('id') ?? null) ? (int) $get('id') : 0,
            (string) ($get('cover_image_ref') ?? ''),
            (string) ($get('cover_mobile_ref') ?? ''),
            (string) ($get('slug') ?? ''),
        ]));

        $previewProgram = $t
            ? TenantServiceProgramFormPreview::makeFromGetCallable(
                $get,
                $t,
                is_numeric($get('id')) ? (int) $get('id') : null
            )
            : null;
        $hasPaneMedia = $previewProgram && filled($previewProgram->coverDesktopPublicUrl($t));
        $articleStyle = ($hasPaneMedia && $previewProgram)
            ? app(ServiceProgramCardPresentationResolver::class)->articleStyleAttribute($previewProgram)
            : '';
        $tTablet = $map['tablet'] ?? [];
        $tabletFocalSummary = is_array($tTablet) && $tTablet !== []
            ? 'x='.(string) ($tTablet['x'] ?? '').' y='.(string) ($tTablet['y'] ?? '')
                .' z='.(string) ($tTablet['scale'] ?? '')
            : '';

        /** @var Collection<string, array<string, mixed>> $tilesByKey */
        $tilesByKey = collect($tiles)->keyBy(fn (array $row): string => (string) ($row['key'] ?? ''));
        $labelFrom = static function (string $k) use ($tilesByKey): string {
            $row = $tilesByKey->get($k);

            return is_array($row) ? (string) ($row['sourceLabel'] ?? '') : '';
        };
        $editorConfig = [
            'mobile' => ['x' => $mx, 'y' => $my, 's' => $ms, 'heightFactor' => $mhf],
            'tablet' => ['x' => $tx, 'y' => $ty, 's' => $ts, 'heightFactor' => $mhf],
            'desktop' => ['x' => $dx, 'y' => $dy, 's' => $ds, 'heightFactor' => $dhf],
            'tileMeta' => [
                'mobile' => [
                    'sourceLabel' => $labelFrom('mobile'),
                    'role' => 'Основной кадр: телефон и экраны до 1023px',
                ],
                'tablet' => [
                    'sourceLabel' => $labelFrom('tablet'),
                    'role' => 'Только превью; на сайте используется mobile',
                ],
                'desktop' => [
                    'sourceLabel' => $labelFrom('desktop'),
                    'role' => 'Широкий экран от 1024px',
                ],
            ],
            'defaults' => [
                'mobile' => [
                    'x' => $defM->x,
                    'y' => $defM->y,
                    's' => ServiceProgramCardPresentationProfile::FRAMING_SCALE_DEFAULT,
                    'heightFactor' => ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_DEFAULT,
                ],
                'tablet' => [
                    'x' => $defT->x,
                    'y' => $defT->y,
                    's' => ServiceProgramCardPresentationProfile::FRAMING_SCALE_DEFAULT,
                    'heightFactor' => ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_DEFAULT,
                ],
                'desktop' => [
                    'x' => $defD->x,
                    'y' => $defD->y,
                    's' => ServiceProgramCardPresentationProfile::FRAMING_SCALE_DEFAULT,
                    'heightFactor' => ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_DEFAULT,
                ],
            ],
            'mediaBase' => [
                'wNarrow' => ServiceProgramCardPresentationProfile::MEDIA_BASE_W_NARROW,
                'hNarrow' => ServiceProgramCardPresentationProfile::MEDIA_BASE_H_NARROW,
                'wSm' => ServiceProgramCardPresentationProfile::MEDIA_BASE_W_SM,
                'hSm' => ServiceProgramCardPresentationProfile::MEDIA_BASE_H_SM,
                'wDesktop' => ServiceProgramCardPresentationProfile::MEDIA_BASE_W_DESKTOP,
                'hDesktop' => ServiceProgramCardPresentationProfile::MEDIA_BASE_H_DESKTOP,
            ],
            'refWidths' => [
                'mobile' => 420,
                'tablet' => 640,
                'desktop' => 960,
            ],
            'scaleMin' => ServiceProgramCardPresentationProfile::FRAMING_SCALE_MIN,
            'scaleMax' => ServiceProgramCardPresentationProfile::FRAMING_SCALE_MAX,
            'scaleStep' => ServiceProgramCardPresentationProfile::FRAMING_SCALE_STEP,
            'heightFactorMin' => ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_MIN,
            'heightFactorMax' => ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_MAX,
            'heightFactorStep' => ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_STEP,
            'wirePathPrefix' => 'data.cover_presentation.viewport_focal_map',
            'syncDefault' => $syncDefault,
            'viewportStorageId' => $stableViewportKey,
            'previewEngine' => 'public_card',
            'safeAreaBottomPercent' => (float) (ServiceProgramCardPresentationProfile::safeAreaBottomBand()['bottomPercent'] ?? 38),
            'safeAreaLabel' => (string) ($safeArea['label'] ?? 'Текст / CTA'),
            /**
             * У карточки программы медиа = отдельный блок; подпись и CTA — под ним, не в нижней полосе кадра.
             * Пунктир «Текст / CTA» внутри рамки редактора визуально совпадал с реальностью плохо — скрываем.
             */
            'showFocalSafeAreaOverlay' => false,
            /** Разрешает перетаскивание фокуса в колонке «кадр» при previewEngine=public_card (слайдеры + WYSIWYG разведены). */
            'allowFocalDrag' => true,
            /** Те же CSS-переменные, что в {@see ServiceProgramCardPresentationResolver::articleStyleAttribute()} (оверлей/маски). */
            'articleStyleOverlay' => ServiceProgramCardPresentationProfile::articleOverlayCssVariables(),
        ];

        return [
            'previewEngine' => 'public_card',
            'tenant' => $t,
            'previewProgram' => $previewProgram,
            'articleStyle' => $articleStyle,
            'hasPaneMedia' => $hasPaneMedia,
            'tabletFocalSummary' => $tabletFocalSummary,
            'tiles' => $tiles,
            'safeArea' => $safeArea,
            'editorConfig' => $editorConfig,
            'previewKey' => $previewKey,
            'viewComponentKey' => $viewComponentKey,
            'overlayMobile' => ServiceProgramCardPresentationProfile::overlayVariablesMobile(),
            'overlayDesktop' => ServiceProgramCardPresentationProfile::overlayVariablesDesktop(),
        ];
    }
}
