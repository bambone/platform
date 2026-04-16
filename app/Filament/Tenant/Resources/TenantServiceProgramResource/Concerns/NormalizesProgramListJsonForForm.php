<?php

namespace App\Filament\Tenant\Resources\TenantServiceProgramResource\Concerns;

use App\MediaPresentation\LegacyCoverObjectPositionParser;
use App\MediaPresentation\PresentationData;

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
        foreach (['audience_json', 'outcomes_json'] as $key) {
            $data[$key] = $this->repeaterStateToJsonLines($data[$key] ?? null);
        }

        return $this->normalizeCoverPresentationForSave($data);
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
            $data['cover_presentation'] = PresentationData::fromArray($raw)->toArray();
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
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
            $a = $legacy->toArray();
            $data['cover_presentation']['viewport_focal_map'] = [
                'default' => $a,
                'mobile' => $a,
                'desktop' => $a,
            ];
        }

        $this->ensurePresentationShape($data['cover_presentation']);

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
        $m['mobile'] = $m['mobile'] ?? ['x' => 50.0, 'y' => 52.0];
        $m['desktop'] = $m['desktop'] ?? ['x' => 50.0, 'y' => 48.0];
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
