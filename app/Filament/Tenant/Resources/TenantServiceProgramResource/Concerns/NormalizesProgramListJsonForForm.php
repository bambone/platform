<?php

namespace App\Filament\Tenant\Resources\TenantServiceProgramResource\Concerns;

use App\MediaPresentation\LegacyCoverObjectPositionParser;
use App\MediaPresentation\PresentationData;
use App\MediaPresentation\Profiles\ServiceProgramCardPresentationProfile;
use App\MediaPresentation\ViewportFraming;
use App\MediaPresentation\ViewportKey;
use App\Models\TenantServiceProgram;
use Illuminate\Validation\ValidationException;

/**
 * Конвертация audience_json / outcomes_json между форматом БД (список строк)
 * и состоянием Repeater в форме ([['text' => '…'], …]).
 *
 * Presentation: {@code cover_presentation_json} ↔ поле формы {@code cover_presentation}.
 */
trait NormalizesProgramListJsonForForm
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeProgramJsonListsForForm(array $data): array
    {
        foreach (['audience_json', 'outcomes_json'] as $key) {
            $data[$key] = $this->jsonLinesToRepeaterState($data[$key] ?? null);
        }

        return $this->normalizeCoverPresentationForFormFill($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeProgramJsonListsForSave(array $data): array
    {
        $data = $this->applyServiceProgramPublicSlugToFormDataForSave($data);

        foreach (['audience_json', 'outcomes_json'] as $key) {
            $data[$key] = $this->repeaterStateToJsonLines($data[$key] ?? null);
        }

        return $this->normalizeCoverPresentationForSave($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyServiceProgramPublicSlugToFormDataForSave(array $data): array
    {
        if (! array_key_exists('slug', $data)) {
            return $data;
        }
        $tenantId = (int) (currentTenant()?->id ?? 0);
        if ($tenantId < 1) {
            throw ValidationException::withMessages(['slug' => 'Контекст клиента не определён.']);
        }
        $ignore = null;
        if (method_exists($this, 'getRecord') && $this->getRecord() !== null) {
            $ignore = (int) $this->getRecord()->getKey();
        }
        $data['slug'] = TenantServiceProgram::normalizedPublicInquirySlugOrFailForTenant(
            (string) $data['slug'],
            $tenantId,
            $ignore,
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeCoverPresentationForFormFill(array $data): array
    {
        $raw = $data['cover_presentation_json'] ?? null;
        if ($raw instanceof PresentationData) {
            $data['cover_presentation'] = $raw->toArray();
        } elseif (is_array($raw)) {
            $raw = $this->unwrapPresentationPayloadIfList($raw);
            $data['cover_presentation'] = PresentationData::fromArray($raw)->toArray();
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $decoded = $this->unwrapPresentationPayloadIfList($decoded);
            }
            $data['cover_presentation'] = is_array($decoded)
                ? PresentationData::fromArray($decoded)->toArray()
                : PresentationData::empty()->toArray();
        } else {
            $data['cover_presentation'] = PresentationData::empty()->toArray();
        }

        $map = $data['cover_presentation']['viewport_focal_map'] ?? [];
        if (! is_array($map)) {
            $map = [];
        }
        if ($map === [] && ($legacy = LegacyCoverObjectPositionParser::parse($data['cover_object_position'] ?? null))) {
            $a = ViewportFraming::normalized($legacy->x, $legacy->y, ServiceProgramCardPresentationProfile::FRAMING_SCALE_DEFAULT)->toArray();
            $data['cover_presentation']['viewport_focal_map'] = [
                'default' => $a,
                'mobile' => $a,
                'desktop' => $a,
            ];
        }

        $this->ensurePresentationShape($data['cover_presentation']);

        // Filament fills from $record->attributesToArray(); cast returns PresentationData here — Livewire cannot dehydrate that type.
        unset($data['cover_presentation_json']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeCoverPresentationForSave(array $data): array
    {
        if (! isset($data['cover_presentation']) || ! is_array($data['cover_presentation'])) {
            $data['cover_presentation'] = PresentationData::empty()->toArray();
        }
        $this->ensurePresentationShape($data['cover_presentation']);

        $form = $data['cover_presentation'];
        unset($data['cover_presentation']);

        if (is_array($form)) {
            $data['cover_presentation_json'] = PresentationData::fromArray([
                'version' => (int) ($form['version'] ?? PresentationData::CURRENT_VERSION),
                'viewport_focal_map' => is_array($form['viewport_focal_map'] ?? null) ? $form['viewport_focal_map'] : [],
            ])->toArray();
        }

        $data['cover_object_position'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $presentation
     */
    private function ensurePresentationShape(array &$presentation): void
    {
        $presentation['version'] = PresentationData::CURRENT_VERSION;
        if (! isset($presentation['viewport_focal_map']) || ! is_array($presentation['viewport_focal_map'])) {
            $presentation['viewport_focal_map'] = [];
        }
        $m = &$presentation['viewport_focal_map'];
        $defT = ServiceProgramCardPresentationProfile::defaultFocalForViewport(ViewportKey::Tablet);
        $m['mobile'] = $this->normalizeViewportRowForForm($m['mobile'] ?? null, 50.0, 52.0);
        $m['tablet'] = $this->normalizeViewportRowForForm($m['tablet'] ?? null, $defT->x, $defT->y);
        $m['desktop'] = $this->normalizeViewportRowForForm($m['desktop'] ?? null, 50.0, 48.0);
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array{x: float, y: float, scale: float, height_factor: float}
     */
    private function normalizeViewportRowForForm(?array $row, float $defaultX, float $defaultY): array
    {
        $pair = $this->normalizeFocalPairForForm($row, $defaultX, $defaultY);
        $scale = isset($row['scale']) && is_numeric($row['scale'])
            ? ViewportFraming::clampScale((float) $row['scale'])
            : ServiceProgramCardPresentationProfile::FRAMING_SCALE_DEFAULT;
        $hf = $row !== null
            ? ViewportFraming::heightFactorFromRow($row)
            : ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_DEFAULT;

        return ViewportFraming::normalized($pair['x'], $pair['y'], $scale, $hf)->toArray();
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array{x: float, y: float}
     */
    private function normalizeFocalPairForForm(?array $row, float $defaultX, float $defaultY): array
    {
        if ($row === null) {
            return ['x' => $defaultX, 'y' => $defaultY];
        }
        $x = $row['x'] ?? $defaultX;
        $y = $row['y'] ?? $defaultY;

        return [
            'x' => is_numeric($x) ? (float) $x : $defaultX,
            'y' => is_numeric($y) ? (float) $y : $defaultY,
        ];
    }

    /**
     * Some legacy / mistaken JSON stores one object inside a single-element array: [{ "version": … }].
     *
     * @param  array<int|string, mixed>  $raw
     * @return array<int|string, mixed>
     */
    private function unwrapPresentationPayloadIfList(array $raw): array
    {
        if (! array_is_list($raw) || count($raw) !== 1) {
            return $raw;
        }
        $only = $raw[0];

        return is_array($only) && isset($only['version']) ? $only : $raw;
    }

    /**
     * @return list<array{text: string}>
     */
    private function jsonLinesToRepeaterState(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }
        $out = [];
        foreach ($json as $item) {
            if (is_string($item)) {
                $t = trim($item);
                if ($t !== '') {
                    $out[] = ['text' => $t];
                }
            } elseif (is_array($item) && filled($item['text'] ?? null)) {
                $t = trim((string) $item['text']);
                if ($t !== '') {
                    $out[] = ['text' => $t];
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function repeaterStateToJsonLines(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }
        $lines = [];
        foreach ($state as $row) {
            if (! is_array($row)) {
                continue;
            }
            $t = trim((string) ($row['text'] ?? ''));
            if ($t !== '') {
                $lines[] = $t;
            }
        }

        return $lines;
    }
}
