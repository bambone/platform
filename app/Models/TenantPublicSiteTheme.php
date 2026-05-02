<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

final class TenantPublicSiteTheme extends Model
{
    protected $table = 'tenant_public_site_themes';

    protected $fillable = [
        'theme_key',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (TenantPublicSiteTheme $theme): void {
            if (Tenant::query()->where('theme_key', $theme->theme_key)->exists()) {
                throw ValidationException::withMessages([
                    'tenant_public_site_theme' => sprintf(
                        'Нельзя удалить тему «%s» — есть клиенты с ключом `%s`. Сначала перенесите клиентов на другую тему.',
                        $theme->name,
                        $theme->theme_key
                    ),
                ]);
            }
        });
    }

    /**
     * @return HasMany<Tenant>
     */
    public function tenantsByThemeKey(): HasMany
    {
        return $this->hasMany(Tenant::class, 'theme_key', 'theme_key');
    }

    /**
     * Список «значение => подпись» для Select в форме клиента платформы.
     *
     * @return array<string, string>
     */
    public static function optionsForTenantForm(?string $currentTenantThemeKey = null): array
    {
        $current = trim((string) $currentTenantThemeKey);
        /** @var \Illuminate\Support\Collection<int, self> $rows */
        $rows = static::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $options = [];

        foreach ($rows as $row) {
            if (! $row->is_active && $row->theme_key !== $current) {
                continue;
            }
            $label = $row->name;
            if (! $row->is_active && $row->theme_key === $current) {
                $label .= ' (в каталоге отключена)';
            }
            $options[$row->theme_key] = $label;
        }

        if ($current !== '' && ! array_key_exists($current, $options)) {
            $options[$current] = $current.' (нет в каталоге — добавьте строку темы)';
        }

        if ($options === [] && $current === '') {
            $options['default'] = 'По умолчанию (нет строк каталога — выполните миграции)';
        }

        return $options;
    }

    /** Ключ по умолчанию для новой записи клиента; fallback — default. */
    public static function defaultThemeKey(): string
    {
        /** @var null|string $k */
        $k = static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('theme_key');

        return $k !== null && $k !== '' ? $k : 'default';
    }
}
