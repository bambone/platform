<?php

declare(strict_types=1);

namespace App\Tenant\ExpertPr;

use App\Support\Storage\TenantStorage;
use Illuminate\Support\Facades\DB;

/**
 * Canonical hero/portrait defaults for Magas tenant (slug {@see self::SLUG}).
 * Used by bootstrap and tenant admin UX — **not** a seeder-only implementation.
 *
 * Bootstrap brand label stays in {@see \Database\Seeders\Tenant\MagasExpertBootstrap::BRAND};
 * hero alt/copy here must stay aligned with that constant.
 */
final class MagasHeroDefaults
{
    public const SLUG = 'sergey-magas';

    /**
     * Fallback when tenant public hero files are absent (canonical external asset).
     */
    private const EXTERNAL_HERO_FALLBACK_URL = 'https://sergeymagas.ru/magas.png';

    /**
     * Resolves portrait URL/key for hero: prefers tenant public storage, else canonical HTTP URL.
     */
    public function resolvedHeroImageUrl(int $tenantId): string
    {
        if ($tenantId <= 0) {
            return self::EXTERNAL_HERO_FALLBACK_URL;
        }
        $ts = TenantStorage::forTrusted($tenantId);
        foreach (['site/brand/magas-hero.png', 'site/brand/magas-hero.jpg'] as $rel) {
            if ($ts->existsPublic($rel)) {
                return $rel;
            }
        }

        return self::EXTERNAL_HERO_FALLBACK_URL;
    }

    /**
     * Keys merged into expert_hero data_json during bootstrap portrait ensure.
     *
     * @return array<string, mixed>
     */
    public function portraitPresentationPayload(int $tenantId): array
    {
        return [
            'hero_image_url' => $this->resolvedHeroImageUrl($tenantId),
            'hero_image_alt' => $this->defaultHeroAlt(),
            'hero_background_presentation' => $this->defaultHeroBackgroundPresentation(),
        ];
    }

    /**
     * Fills canonical portrait defaults only where data is absent (preserve manual edits on re-bootstrap).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mergePortraitDefaultsOnlyMissing(int $tenantId, array $data): array
    {
        if ($tenantId <= 0 || ! $this->isMagasTenantId($tenantId)) {
            return $data;
        }
        $canonical = $this->portraitPresentationPayload($tenantId);
        if (trim((string) ($data['hero_image_url'] ?? '')) === '') {
            $data['hero_image_url'] = $canonical['hero_image_url'];
        }
        if (trim((string) ($data['hero_image_alt'] ?? '')) === '') {
            $data['hero_image_alt'] = $canonical['hero_image_alt'];
        }
        $existingBg = $data['hero_background_presentation'] ?? null;
        $hasBg = is_array($existingBg) && (($existingBg['viewport_focal_map'] ?? []) !== []);
        if (! $hasBg) {
            $data['hero_background_presentation'] = $canonical['hero_background_presentation'];
        }

        return $data;
    }

    /**
     * @return array{version:int,viewport_focal_map:array<string, mixed>}
     */
    private function defaultHeroBackgroundPresentation(): array
    {
        return [
            'version' => 2,
            'viewport_focal_map' => [
                'mobile' => ['x' => 80.0, 'y' => 28.0, 'scale' => 0.92],
                'tablet' => ['x' => 74.0, 'y' => 17.0, 'scale' => 0.90],
                'desktop' => ['x' => 80.0, 'y' => 18.0, 'scale' => 0.92],
                'default' => ['x' => 80.0, 'y' => 18.0, 'scale' => 0.92],
            ],
        ];
    }

    /**
     * Fills missing `hero_image_url` / `hero_image_alt` on the home `expert_hero` row for Magas slug.
     *
     * @return bool whether a DB row was updated; does **not** clear {@see \App\Http\Controllers\HomeController} cache —
     *               callers that must refresh cached public payloads should invoke
     *               {@see \App\Http\Controllers\HomeController::forgetCachedPayloadForTenant()} when returning `true`
     */
    public function fillMissingHomeHeroImage(int $tenantId): bool
    {
        if ($tenantId <= 0 || ! $this->isMagasTenantId($tenantId)) {
            return false;
        }
        $homeId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
        if ($homeId <= 0) {
            return false;
        }
        $row = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $homeId)
            ->where('section_key', 'expert_hero')
            ->first();
        if ($row === null) {
            return false;
        }
        $data = json_decode((string) ($row->data_json ?? ''), true);
        $data = is_array($data) ? $data : [];

        $changed = false;
        if (trim((string) ($data['hero_image_url'] ?? '')) === '') {
            $data['hero_image_url'] = $this->resolvedHeroImageUrl($tenantId);
            $changed = true;
        }
        if (trim((string) ($data['hero_image_alt'] ?? '')) === '') {
            $data['hero_image_alt'] = $this->defaultHeroAlt();
            $changed = true;
        }
        if (! $changed) {
            return false;
        }
        DB::table('page_sections')->where('id', (int) $row->id)->update([
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Editor preview only: inject default portrait when DB has empty URLs (no persistence).
     *
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public function mergeMissingHeroImageForEditor(int $tenantId, array $existing): array
    {
        if ($tenantId <= 0 || ! $this->isMagasTenantId($tenantId)) {
            return $existing;
        }
        if (trim((string) ($existing['hero_image_url'] ?? '')) !== '') {
            return $existing;
        }
        $existing['hero_image_url'] = $this->resolvedHeroImageUrl($tenantId);
        if (trim((string) ($existing['hero_image_alt'] ?? '')) === '') {
            $existing['hero_image_alt'] = $this->defaultHeroAlt();
        }

        return $existing;
    }

    private function defaultHeroAlt(): string
    {
        // Keep aligned with {@see MagasExpertBootstrap::BRAND} + hero alt suffix used in bootstrap.
        return 'Sergei Magas — B2B PR & narrative portrait';
    }

    private function isMagasTenantId(int $tenantId): bool
    {
        $slug = (string) DB::table('tenants')->where('id', $tenantId)->value('slug');

        return $slug === self::SLUG;
    }
}
