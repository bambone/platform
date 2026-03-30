<?php

namespace App\Terminology;

use App\Models\DomainLocalizationPresetTerm;
use App\Models\DomainTerm;
use App\Models\Tenant;
use App\Models\TenantTermOverride;
use Illuminate\Support\Facades\Cache;

final class TenantTerminologyService
{
    private const CACHE_VERSION = 'v1';

    public function label(Tenant $tenant, string $termKey, ?string $locale = null): string
    {
        $dict = $this->dictionary($tenant, $locale);
        $entry = $dict[$termKey] ?? null;

        return is_array($entry) ? (string) ($entry['label'] ?? $termKey) : $termKey;
    }

    public function shortLabel(Tenant $tenant, string $termKey, ?string $locale = null): ?string
    {
        $dict = $this->dictionary($tenant, $locale);
        $entry = $dict[$termKey] ?? null;
        if (! is_array($entry)) {
            return null;
        }
        $short = $entry['short_label'] ?? null;

        return $short !== null && $short !== '' ? (string) $short : null;
    }

    /**
     * @param  list<string>  $termKeys
     * @return array<string, string> term_key => label
     */
    public function many(Tenant $tenant, array $termKeys, ?string $locale = null): array
    {
        $dict = $this->dictionary($tenant, $locale);
        $out = [];
        foreach ($termKeys as $key) {
            $entry = $dict[$key] ?? null;
            $out[$key] = is_array($entry) ? (string) ($entry['label'] ?? $key) : $key;
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, short_label: ?string}>
     */
    public function dictionary(Tenant $tenant, ?string $locale = null): array
    {
        $loc = $this->resolveLocale($tenant, $locale);
        $cacheKey = $this->cacheKey($tenant->id, $loc);

        return Cache::rememberForever($cacheKey, function () use ($tenant): array {
            return $this->buildDictionary($tenant);
        });
    }

    public function forgetTenant(int $tenantId): void
    {
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            return;
        }
        Cache::forget($this->cacheKey($tenantId, $this->resolveLocale($tenant, null)));
    }

    public function forgetTenantsUsingPreset(int $presetId): void
    {
        $ids = Tenant::query()
            ->where('domain_localization_preset_id', $presetId)
            ->pluck('id');
        foreach ($ids as $id) {
            $this->forgetTenant((int) $id);
        }
    }

    public function forgetAllTenants(): void
    {
        Tenant::query()
            ->select(['id', 'locale'])
            ->orderBy('id')
            ->chunkById(200, function ($tenants): void {
                foreach ($tenants as $t) {
                    Cache::forget($this->cacheKey((int) $t->id, $this->resolveLocale($t, null)));
                }
            });
    }

    public function cacheKey(int $tenantId, string $locale): string
    {
        return 'terminology.'.self::CACHE_VERSION.'.'.$tenantId.'.'.$locale;
    }

    /**
     * Cache key used by {@see dictionary()} / {@see forgetTenant()} for this tenant (app locale when tenant locale empty).
     */
    public function dictionaryCacheKey(Tenant $tenant): string
    {
        return $this->cacheKey($tenant->id, $this->resolveLocale($tenant, null));
    }

    private function resolveLocale(Tenant $tenant, ?string $locale): string
    {
        $l = $locale ?? $tenant->locale;

        return $l !== null && $l !== '' ? strtolower((string) $l) : strtolower((string) config('app.locale', 'ru'));
    }

    /**
     * @return array<string, array{label: string, short_label: ?string}>
     */
    private function buildDictionary(Tenant $tenant): array
    {
        $terms = DomainTerm::query()
            ->where('is_active', true)
            ->get()
            ->keyBy('term_key');

        if ($terms->isEmpty()) {
            return [];
        }

        $termIds = $terms->pluck('id')->all();

        $overridesByTermId = TenantTermOverride::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('term_id', $termIds)
            ->get()
            ->keyBy('term_id');

        $presetTermsByTermId = collect();
        if ($tenant->domain_localization_preset_id !== null) {
            $presetTermsByTermId = DomainLocalizationPresetTerm::query()
                ->where('preset_id', $tenant->domain_localization_preset_id)
                ->whereIn('term_id', $termIds)
                ->get()
                ->keyBy('term_id');
        }

        $map = [];
        foreach ($terms as $termKey => $term) {
            /** @var DomainTerm $term */
            $override = $overridesByTermId->get($term->id);
            $presetRow = $presetTermsByTermId->get($term->id);

            $label = $override?->label
                ?? $presetRow?->label
                ?? $term->default_label
                ?? $termKey;

            $short = $override?->short_label ?? $presetRow?->short_label;
            if ($short === '') {
                $short = null;
            }

            $source = 'default';
            if ($override !== null) {
                $source = 'override';
            } elseif ($presetRow !== null) {
                $source = 'preset';
            }

            $map[$termKey] = [
                'label' => (string) $label,
                'short_label' => $short,
                'source' => $source,
            ];
        }

        return $map;
    }
}
