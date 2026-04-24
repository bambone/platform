<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Models\TenantServiceProgram;

/**
 * Секции по умолчанию для посадочной услуги (как в {@see \Database\Seeders\Tenant\BlackDuckBootstrap::defServiceLanding}).
 * Используется, если страница создана sync’ом, а не полным сидом.
 */
final class BlackDuckServiceLandingPageFactory
{
    /**
     * @return list<array{section_key: string, section_type: string, title: ?string, sort_order: int, data_json: string, is_visible: bool, status: string}>
     */
    public static function defaultPageSectionsForProgram(TenantServiceProgram $p): array
    {
        $slug = (string) $p->slug;
        $name = (string) $p->title;
        $lead = (string) ($p->teaser ?? '');
        $inquiry = BlackDuckContentConstants::contactsInquiryUrlForServiceSlug($slug);

        $enc = static function (array $d): string {
            return json_encode($d, JSON_UNESCAPED_UNICODE) ?: '{}';
        };
        $sec = static function (string $key, string $type, ?string $title, int $order, array $data, bool $isVisible = true) use ($enc): array {
            return [
                'section_key' => $key,
                'section_type' => $type,
                'title' => $title,
                'sort_order' => $order,
                'data_json' => $enc($data),
                'is_visible' => $isVisible,
                'status' => 'published',
            ];
        };

        $rows = [
            $sec('hero', 'hero', 'Hero', 0, [
                'variant' => 'full_background',
                'heading' => $name,
                'subheading' => $lead,
                'button_text' => 'Состав и этапы',
                'button_url' => '#bd-service-included',
                'secondary_button_text' => 'Записаться',
                'secondary_button_url' => BlackDuckContentConstants::serviceLandingBookIntentUrl($slug),
                'overlay_dark' => true,
            ]),
            $sec('body_intro', 'rich_text', 'О услуге', 8, [
                'content' => '<p class="text-pretty leading-relaxed">'.e($lead).'</p>',
            ]),
            $sec('service_included', 'list_block', 'Что входит', 12, [
                'title' => 'Что входит',
                'variant' => 'bullets',
                'items' => [
                    ['title' => 'Согласование', 'text' => 'Объём и срок после осмотра или заявки.'],
                ],
            ]),
            $sec('body', 'rich_text', 'Суть', 18, [
                'content' => '',
            ]),
            $sec('service_faq', 'faq', 'FAQ', 25, [
                'section_heading' => 'Вопросы по услуге',
                'source' => 'faqs_table_service',
                'faq_category' => $slug,
                'items' => [],
            ]),
            $sec('service_review_feed', 'review_feed', 'Отзывы', 27, [
                'heading' => 'Отзывы клиентов',
                'subheading' => 'Выдержки с 2ГИС и Яндекс Карт по этой услуге.',
                'layout' => 'service_maps_compact',
                'limit' => BlackDuckMapsReviewCatalog::REVIEWS_PER_LANDING,
                'category_key' => $slug,
                'section_id' => 'bd-service-reviews',
                'maps_link_2gis' => BlackDuckContentConstants::URL_2GIS_REVIEWS_TAB,
                'maps_link_yandex' => BlackDuckContentConstants::URL_YANDEX_MAPS_REVIEWS_TAB,
                'show_maps_cta' => true,
            ]),
        ];
        $rows[] = $sec('service_proof', 'case_study_cards', 'На фото', 40, [
            'heading' => 'На фото',
            'items' => [],
        ]);
        $rows[] = $sec('service_final_cta', 'rich_text', 'Заявка', 50, [
            'content' => '<p class="text-zinc-300">Нужен расчёт или запись? <a class="font-medium text-[#36C7FF] underline" href="'.e($inquiry).'">Оставьте заявку</a> — в форме можно выбрать направление «'.e($name).'».</p>',
        ]);

        return $rows;
    }
}
