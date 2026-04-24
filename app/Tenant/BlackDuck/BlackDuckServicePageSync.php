<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Models\TenantServiceProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Синхронизирует {@see Page} с каталогом услуг: посадочные с has_landing, скрытие при отсутствии публичности.
 * Работает только при {@see BlackDuckServiceProgramCatalog::databaseHasCatalog()}.
 */
final class BlackDuckServicePageSync
{
    public function syncForTenant(int $tenantId): void
    {
        if (! BlackDuckServiceProgramCatalog::databaseHasCatalog($tenantId)) {
            return;
        }
        if (! Schema::hasTable('pages') || ! Schema::hasTable('page_sections')) {
            return;
        }
        $now = now();
        foreach (BlackDuckServiceProgramCatalog::allProgramsOrdered($tenantId) as $p) {
            $this->syncOne($tenantId, $p, $now);
        }
    }

    private function syncOne(int $tenantId, TenantServiceProgram $p, $now): void
    {
        $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];
        $hasLanding = (bool) ($meta['has_landing'] ?? true);
        $slug = (string) $p->slug;
        if (str_starts_with($slug, '#')) {
            return;
        }
        $pageId = (int) DB::table('pages')
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->value('id');

        if (! $hasLanding || ! $p->is_visible) {
            if ($pageId > 0) {
                DB::table('pages')
                    ->where('id', $pageId)
                    ->update([
                        'status' => 'hidden',
                        'name' => (string) $p->title,
                        'updated_at' => $now,
                    ]);
            }

            return;
        }
        if ($pageId < 1) {
            $pageId = (int) DB::table('pages')->insertGetId([
                'tenant_id' => $tenantId,
                'slug' => $slug,
                'name' => (string) $p->title,
                'template' => 'default',
                'status' => 'published',
                'published_at' => $now,
                'show_in_main_menu' => false,
                'main_menu_sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach (BlackDuckServiceLandingPageFactory::defaultPageSectionsForProgram($p) as $s) {
                $exists = DB::table('page_sections')
                    ->where('tenant_id', $tenantId)
                    ->where('page_id', $pageId)
                    ->where('section_key', $s['section_key'])
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('page_sections')->insert(array_merge($s, [
                    'page_id' => $pageId,
                    'tenant_id' => $tenantId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }

            return;
        }
        DB::table('pages')
            ->where('id', $pageId)
            ->update([
                'name' => (string) $p->title,
                'status' => 'published',
                'updated_at' => $now,
            ]);
    }
}
