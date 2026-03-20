<?php

namespace App\Filament\Platform\Resources\PlanResource\Pages\Concerns;

use App\Filament\Support\PlanUiSchema;

trait NormalizesPlanJsonForm
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizePlanFormData(array $data): array
    {
        $limits = [];
        foreach (array_keys(PlanUiSchema::limitFields()) as $k) {
            $field = 'limit_'.$k;
            if (array_key_exists($field, $data) && $data[$field] !== '' && $data[$field] !== null) {
                $limits[$k] = (int) $data[$field];
            }
        }
        $extra = $data['limits_extra'] ?? [];
        if (is_array($extra)) {
            foreach ($extra as $ek => $ev) {
                if ($ek === '' || $ek === null) {
                    continue;
                }
                if (is_numeric($ev)) {
                    $limits[$ek] = (int) $ev;
                } else {
                    $limits[$ek] = $ev;
                }
            }
        }
        $data['limits_json'] = $limits;
        $data['features_json'] = array_values($data['plan_features'] ?? []);

        foreach (array_keys(PlanUiSchema::limitFields()) as $k) {
            unset($data['limit_'.$k]);
        }
        unset($data['limits_extra'], $data['plan_features']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function expandPlanFormData(array $data): array
    {
        $limits = $data['limits_json'] ?? [];
        if (! is_array($limits)) {
            $limits = [];
        }
        foreach (array_keys(PlanUiSchema::limitFields()) as $k) {
            $data['limit_'.$k] = $limits[$k] ?? null;
        }
        $known = array_keys(PlanUiSchema::limitFields());
        $data['limits_extra'] = collect($limits)->except($known)->all();
        $data['plan_features'] = $data['features_json'] ?? [];
        if (! is_array($data['plan_features'])) {
            $data['plan_features'] = [];
        }

        return $data;
    }
}
