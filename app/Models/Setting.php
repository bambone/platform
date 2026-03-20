<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'settings.'.$key;

        return Cache::rememberForever($cacheKey, function () use ($key, $default) {
            $parts = explode('.', $key, 2);
            $group = $parts[0] ?? 'general';
            $k = $parts[1] ?? $parts[0];

            $setting = static::where('group', $group)->where('key', $k)->first();

            if (! $setting) {
                return $default;
            }

            return static::castValue($setting->value, $setting->type);
        });
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        $parts = explode('.', $key, 2);
        $group = $parts[0] ?? 'general';
        $k = $parts[1] ?? $parts[0];

        $value = match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };

        static::updateOrCreate(
            ['group' => $group, 'key' => $k],
            ['value' => $value, 'type' => $type]
        );

        Cache::forget('settings.'.$key);
    }

    protected static function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => $value ? json_decode($value, true) : null,
            default => $value,
        };
    }
}
