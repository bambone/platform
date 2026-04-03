<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pageIds = DB::table('pages')->where('slug', 'home')->pluck('id');
        foreach ($pageIds as $pageId) {
            $exists = DB::table('page_sections')
                ->where('page_id', $pageId)
                ->where('section_type', 'motorcycle_catalog')
                ->exists();
            if ($exists) {
                continue;
            }
            $tenantId = (int) DB::table('pages')->where('id', $pageId)->value('tenant_id');
            DB::table('page_sections')->insert([
                'tenant_id' => $tenantId,
                'page_id' => $pageId,
                'section_key' => 'motorcycle_catalog',
                'section_type' => 'motorcycle_catalog',
                'title' => 'Каталог мотоциклов',
                'data_json' => json_encode([
                    'heading' => 'Наш автопарк',
                    'subheading' => 'Премиальная техника для любого стиля. Ограниченное количество мотоциклов — бронируйте заранее.',
                ], JSON_THROW_ON_ERROR),
                'sort_order' => 25,
                'is_visible' => true,
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('page_sections')
            ->where('section_type', 'motorcycle_catalog')
            ->where('section_key', 'motorcycle_catalog')
            ->delete();
    }
};
