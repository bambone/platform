<?php

namespace App\MediaPresentation\Casts;

use App\MediaPresentation\PresentationData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<PresentationData|null, mixed>
 */
final class PresentationDataCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?PresentationData
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof PresentationData) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (! is_array($decoded)) {
                return PresentationData::empty();
            }
            $value = $decoded;
        }
        if (! is_array($value)) {
            return PresentationData::empty();
        }

        return PresentationData::fromArray($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof PresentationData) {
            return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            $decoded = json_decode($trimmed, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                return null;
            }
            $value = $decoded;
        }
        if (is_array($value)) {
            return json_encode(PresentationData::fromArray($value)->toArray(), JSON_THROW_ON_ERROR);
        }

        return null;
    }
}
