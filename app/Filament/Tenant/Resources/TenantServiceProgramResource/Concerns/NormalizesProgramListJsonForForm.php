<?php

namespace App\Filament\Tenant\Resources\TenantServiceProgramResource\Concerns;

/**
 * Конвертация audience_json / outcomes_json между форматом БД (список строк)
 * и состоянием Repeater в форме ([['text' => '…'], …]).
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

        return $data;
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

        return $data;
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
