<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Записи после 2026-04-16: публичные карточки программ, кабинет, конструктор.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $now): array
    {
        return [
            [
                'entry_date' => '2026-04-18',
                'title' => 'Публичный сайт: карточки программ — плавный переход фото к тексту',
                'summary' => 'В блоках с программами обслуживания (тема «Эксперт») низ обложки плавно уходит в фон к заголовку и цене: кадр остаётся чётким, без «мутного» слоя на большой площади.',
                'body' => "### Что поменялось\nЗона затухания прижата **к нижнему краю** карточки: основная часть фото не «съедается» полупрозрачностью, переход к тёмному блоку с заголовком стал **ровнее**.\n\n### Где смотреть\nПубличный сайт тенанта — разделы с **карточками программ** (каталог обучения, услуги с обложкой).\n**Кадрирование** обложки по-прежнему настраивается в карточке программы: фокус и zoom для mobile/desktop.\n",
                'sort_weight' => 20,
                'is_published' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'entry_date' => '2026-04-22',
                'title' => 'Кабинет, конструктор и медиа: мелкие доработки',
                'summary' => 'Уточнения в формах тенанта и конструкторе страниц, выбор публичных файлов, стабильность Livewire; подсказки и проверки в админ-интерфейсе.',
                'body' => "### Кабинет\nФормы и сценарии **выбора файлов** (в т.ч. публичные ассеты) — предсказуемее при работе в модальных окнах и при сохранении.\n**Конструктор страниц** — правки стабильности и отображения при многошаговом редактировании.\n\n### Разработка\nНебольшие правки **локализации** и валидации, чтобы реже «спотыкаться» о устаревшие подсказки в интерфейсе.\n",
                'sort_weight' => 15,
                'is_published' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('platform_product_changelog_entries')) {
            return;
        }

        $now = now()->toDateTimeString();
        DB::table('platform_product_changelog_entries')->insert($this->rows($now));
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_product_changelog_entries')) {
            return;
        }

        DB::table('platform_product_changelog_entries')
            ->whereIn('title', [
                'Публичный сайт: карточки программ — плавный переход фото к тексту',
                'Кабинет, конструктор и медиа: мелкие доработки',
            ])
            ->delete();
    }
};
