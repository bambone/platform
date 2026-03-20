<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    protected static function booted(): void
    {
        static::saved(function (PlatformSetting $setting): void {
            Cache::forget('platform_settings.'.$setting->key);
        });

        static::deleted(function (PlatformSetting $setting): void {
            Cache::forget('platform_settings.'.$setting->key);
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'platform_settings.'.$key;

        return Cache::rememberForever($cacheKey, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            return static::castValue($setting->value, $setting->type);
        });
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        $value = match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );

        Cache::forget('platform_settings.'.$key);
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
