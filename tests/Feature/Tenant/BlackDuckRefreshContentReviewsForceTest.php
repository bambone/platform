<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use App\Tenant\BlackDuck\BlackDuckMapsReviewCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Регрессии: {@see BlackDuckContentRefresher} — голый {@code --force} не затирает отзывы из кабинета; {@code --overwrite-reviews} восстанавливает управляемые пулы.
 */
final class BlackDuckRefreshContentReviewsForceTest extends TestCase
{
    use RefreshDatabase;

    private function makeBlackDuckTenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'BD Reviews T',
            'slug' => 'bd-revfc-'.substr(uniqid('', true), -8),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
    }

    /**
     * Существующий отзыв {@code source = site} не удаляется при {@code refresh-content --force}.
     */
    #[Test]
    public function force_without_overwrite_does_not_remove_site_review(): void
    {
        $tenant = $this->makeBlackDuckTenant();
        $tid = (int) $tenant->id;

        $now = now();
        DB::table('reviews')->insert([
            'tenant_id' => $tid,
            'name' => 'CUSTOM_KEEP_SITE',
            'text' => 'Полный текст кастомного отзыва.',
            'rating' => 5,
            'source' => 'site',
            'status' => 'published',
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $exit = Artisan::call('tenant:black-duck:refresh-content', [
            'tenant' => $tenant->slug,
            '--force' => true,
        ]);
        $this->assertSame(0, $exit);

        $this->assertSame(
            1,
            (int) DB::table('reviews')->where('tenant_id', $tid)->where('name', 'CUSTOM_KEEP_SITE')->count(),
        );
    }

    #[Test]
    public function force_without_overwrite_does_not_remove_import_review(): void
    {
        $tenant = $this->makeBlackDuckTenant();
        $tid = (int) $tenant->id;
        $now = now();

        DB::table('reviews')->insert([
            'tenant_id' => $tid,
            'name' => 'CUSTOM_KEEP_IMPORT',
            'text' => 'Импорт.',
            'rating' => 5,
            'source' => 'import',
            'status' => 'published',
            'sort_order' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Artisan::call('tenant:black-duck:refresh-content', [
            'tenant' => $tenant->slug,
            '--force' => true,
        ]);

        $this->assertSame(
            1,
            (int) DB::table('reviews')->where('tenant_id', $tid)->where('name', 'CUSTOM_KEEP_IMPORT')->count(),
        );
    }

    /**
     * {@code --force --overwrite-reviews}: управляемые отзывы site/import пересоздаются (bootstrap-набор включает «Сергей» и т.д.).
     */
    #[Test]
    public function force_with_overwrite_reviews_replaces_site_pool(): void
    {
        $tenant = $this->makeBlackDuckTenant();
        $tid = (int) $tenant->id;
        $now = now();

        DB::table('reviews')->insert([
            'tenant_id' => $tid,
            'name' => 'WILL_REPLACE',
            'text' => 'Старый.',
            'rating' => 4,
            'source' => 'site',
            'status' => 'published',
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $exit = Artisan::call('tenant:black-duck:refresh-content', [
            'tenant' => $tenant->slug,
            '--force' => true,
            '--overwrite-reviews' => true,
        ]);
        $this->assertSame(0, $exit);

        $this->assertSame(
            0,
            (int) DB::table('reviews')->where('tenant_id', $tid)->where('name', 'WILL_REPLACE')->count(),
        );
        $this->assertGreaterThanOrEqual(
            1,
            (int) DB::table('reviews')->where('tenant_id', $tid)->where('name', 'Сергей')->count(),
        );
    }

    /**
     * Отзыв с {@see BlackDuckMapsReviewCatalog::SOURCE} сохраняется при голом {@code --force}.
     */
    #[Test]
    public function force_without_overwrite_preserves_maps_curated_review(): void
    {
        $tenant = $this->makeBlackDuckTenant();
        $tid = (int) $tenant->id;
        $now = now();

        DB::table('reviews')->insert([
            'tenant_id' => $tid,
            'name' => 'MAPS_KEEP_UNIQUE',
            'text' => 'Текст с карт.',
            'rating' => 5,
            'source' => BlackDuckMapsReviewCatalog::SOURCE,
            'status' => 'published',
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Artisan::call('tenant:black-duck:refresh-content', [
            'tenant' => $tenant->slug,
            '--force' => true,
        ]);

        $this->assertSame(
            1,
            (int) DB::table('reviews')
                ->where('tenant_id', $tid)
                ->where('source', BlackDuckMapsReviewCatalog::SOURCE)
                ->where('name', 'MAPS_KEEP_UNIQUE')
                ->count(),
        );
    }

    /**
     * При {@code --overwrite-reviews} сидинг кураторских карт может заменить ряды источника {@code maps_curated}.
     */
    #[Test]
    public function overwrite_reviews_may_refresh_maps_seed_when_catalog_yields_rows(): void
    {
        $tenant = $this->makeBlackDuckTenant();
        $tid = (int) $tenant->id;
        $now = now();

        DB::table('reviews')->insert([
            'tenant_id' => $tid,
            'name' => 'MAPS_REPLACE_ME',
            'text' => 'Старый curated.',
            'rating' => 5,
            'source' => BlackDuckMapsReviewCatalog::SOURCE,
            'status' => 'published',
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rows = BlackDuckMapsReviewCatalog::rowsForDatabaseSeed($tid);

        Artisan::call('tenant:black-duck:refresh-content', [
            'tenant' => $tenant->slug,
            '--force' => true,
            '--overwrite-reviews' => true,
        ]);

        if ($rows === []) {
            $this->assertSame(
                0,
                (int) DB::table('reviews')
                    ->where('tenant_id', $tid)
                    ->where('source', BlackDuckMapsReviewCatalog::SOURCE)
                    ->where('name', 'MAPS_REPLACE_ME')
                    ->count(),
                'Пустой каталог: строки источника maps_curated могли быть удалены без вставки',
            );

            return;
        }

        $this->assertSame(
            0,
            (int) DB::table('reviews')
                ->where('tenant_id', $tid)
                ->where('name', 'MAPS_REPLACE_ME')
                ->count(),
        );
        $this->assertGreaterThanOrEqual(
            1,
            (int) DB::table('reviews')
                ->where('tenant_id', $tid)
                ->where('source', BlackDuckMapsReviewCatalog::SOURCE)
                ->count(),
        );
    }
}
