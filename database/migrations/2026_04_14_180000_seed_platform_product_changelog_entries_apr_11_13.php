<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Записи после 2026-04-10 (тема Expert Auto, каналы и файлы, медиа/CDN, форма заявки, консоль платформы).
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $now): array
    {
        return [
            [
                'entry_date' => '2026-04-11',
                'title' => 'Тема Expert Auto, публичное хранилище и программы обслуживания',
                'summary' => 'Улучшены публичные URL медиа и компоненты темы; маршрутизация страницы контактов; обложки и карточки программ обслуживания.',
                'body' => "### Публичный сайт\nСтабильнее отдача ассетов и разметка в теме **Expert Auto**; контакты — предсказуемые URL.\n\n### Кабинет\n**Каталог / программы обслуживания**: обложки и медиа без лишних ручных правок путей.\n",
                'sort_weight' => 20,
                'is_published' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'entry_date' => '2026-04-12',
                'title' => 'Каналы связи, каталог файлов и календарь бронирований',
                'summary' => 'ВКонтакте в каналах контактов; доступ к разделу мотоциклов по теме сайта; файлы сайта; фильтры и удобства календаря; выбор пользователя в получателях уведомлений.',
                'body' => "### Где\n**Настройки → Каналы связи** (в т.ч. ВКонтакте), **Контент → Файлы сайта**, **Операции → Календарь бронирований**, уведомления — **получатели**.\n\n### Зачем\nМеньше «лишних» разделов в меню там, где ниша не мото; быстрее закрывать сценарии бронирования и доставки сообщений.\n",
                'sort_weight' => 20,
                'is_published' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'entry_date' => '2026-04-13',
                'title' => 'Медиа, CDN, форма заявки Expert и диагностика консоли',
                'summary' => 'Публичные URL для аватаров и медиа техники; CDN и единый резолвер ассетов; команды для хранилища; форма заявки с предпочтительным временем; улучшения виджетов платформы.',
                'body' => "### Публичный сайт\nКорректные ссылки на изображения в каталоге и карточках.\n\n### Кабинет и платформа\nТема **Expert**: удобнее отправить заявку с пожеланием по времени.\nОперациям и поддержке: обновлены экраны диагностики и виджеты в консоли платформы — проще видеть состояние после выката.\n",
                'sort_weight' => 20,
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
                'Тема Expert Auto, публичное хранилище и программы обслуживания',
                'Каналы связи, каталог файлов и календарь бронирований',
                'Медиа, CDN, форма заявки Expert и диагностика консоли',
            ])
            ->delete();
    }
};
