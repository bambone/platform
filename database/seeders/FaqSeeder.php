<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'motolevins')->first();

        if (! $tenant) {
            $this->command?->warn('Tenant motolevins not found. FaqSeeder skipped.');

            return;
        }

        $items = [
            [
                'question' => 'Можно ли уехать в другой город?',
                'answer' => 'Да. Краснодарский край и Крым — без ограничений. Выезд в другие регионы согласовывается индивидуально. Суточный лимит — 300 км, перепробег оплачивается отдельно.',
                'category' => 'general',
                'sort_order' => 0,
                'status' => 'published',
                'show_on_home' => true,
            ],
            [
                'question' => 'Какие документы нужны для аренды?',
                'answer' => 'Паспорт (возраст от 21 года) и права категории «А» (стаж от 2 лет). Только оригиналы документов.',
                'category' => 'general',
                'sort_order' => 10,
                'status' => 'published',
                'show_on_home' => true,
            ],
            [
                'question' => 'Есть ли залог?',
                'answer' => 'Да, предусмотрен возвратный депозит, размер которого зависит от класса мотоцикла (от 30 000 до 80 000 рублей). Он блокируется на карте или вносится наличными и возвращается сразу после сдачи техники без повреждений.',
                'category' => 'general',
                'sort_order' => 20,
                'status' => 'published',
                'show_on_home' => true,
            ],
            [
                'question' => 'Есть ли страховка?',
                'answer' => 'ОСАГО — на всех мотоциклах. КАСКО без франшизы — опция при бронировании. Защищает от финансовой ответственности при ДТП по чужой вине.',
                'category' => 'general',
                'sort_order' => 30,
                'status' => 'published',
                'show_on_home' => true,
            ],
            [
                'question' => 'Что если сломается в дороге?',
                'answer' => 'Поддержка 24/7. Если поломка по нашей вине — заменим мотоцикл или вернём деньги за неиспользованные дни. Техника проходит ТО перед каждой выдачей, поломки редки.',
                'category' => 'general',
                'sort_order' => 40,
                'status' => 'published',
                'show_on_home' => true,
            ],
        ];

        foreach ($items as $item) {
            Faq::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'question' => $item['question'],
                ],
                array_merge($item, ['tenant_id' => $tenant->id])
            );
        }
    }
}
