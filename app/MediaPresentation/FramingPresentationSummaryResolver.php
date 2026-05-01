<?php

namespace App\MediaPresentation;

use App\MediaPresentation\Contracts\SlotPresentationProfileInterface;

/**
 * Framing state + profile → short label / badge variant for Filament (no hand-built strings in forms).
 */
final class FramingPresentationSummaryResolver
{
    /**
     * @param  array<string, mixed>|null  $presentationRow  {@see PresentationData} row or null
     * @return array{label: string, variant: string}
     */
    public function summarize(?array $presentationRow, SlotPresentationProfileInterface $profile): array
    {
        $pd = PresentationData::fromArray(
            $presentationRow,
            $profile->framingScaleMin(),
            $profile->framingScaleMax(),
            $profile->framingScaleStep(),
        );
        $map = $pd->viewportFocalMap;
        if ($map === []) {
            return ['label' => 'Кадрирование: значения по умолчанию', 'variant' => 'neutral'];
        }

        $slotId = $profile->slotId();
        $defaultsMatch = true;
        foreach ([ViewportKey::Mobile, ViewportKey::Tablet, ViewportKey::Desktop] as $vk) {
            $fr = FocalMapViewport::pickFramingFromMap(
                $map,
                $vk,
                $profile->framingScaleMin(),
                $profile->framingScaleMax(),
                $profile->framingScaleStep(),
            );
            $defF = MediaPresentationRegistry::defaultFocalForSlot($slotId, $vk);
            $defS = $profile->framingScaleDefault();
            if ($fr === null) {
                $defaultsMatch = false;

                break;
            }
            $f = $fr->toFocalPoint();
            if (
                abs($f->x - $defF->x) > 0.05
                || abs($f->y - $defF->y) > 0.05
                || abs($fr->scale - $defS) > 0.001
                || abs($fr->heightFactor - 1.0) > 0.001
            ) {
                $defaultsMatch = false;

                break;
            }
        }

        if ($defaultsMatch) {
            return ['label' => 'Кадрирование: значения по умолчанию', 'variant' => 'neutral'];
        }

        return ['label' => 'Кадрирование настроено', 'variant' => 'success'];
    }
}
