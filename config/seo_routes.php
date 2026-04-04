<?php

use App\Services\Seo\SeoRouteRegistry;

/**
 * Tenant public SEO templates by Laravel route name.
 * Placeholders: {site_name}, {page_name}, {motorcycle_name}
 *
 * @see SeoRouteRegistry
 */
return [
    'routes' => [
        'home' => [
            'title' => 'Аренда мотоциклов — {site_name}',
            'description' => 'Каталог мотоциклов, онлайн-бронирование и прозрачные условия проката. Выберите модель и даты на сайте {site_name}.',
        ],
        'offline' => [
            'title' => 'Офлайн — {site_name}',
            'description' => 'Страница доступна без сети.',
            'h1' => 'Офлайн',
        ],
        'contacts' => [
            'title' => 'Контакты — {site_name}',
            'description' => 'Телефон, мессенджеры и способы связи с {site_name}. Задайте вопрос по бронированию или условиям проката.',
            'h1' => 'Контакты',
        ],
        'terms' => [
            'title' => 'Условия аренды — {site_name}',
            'description' => 'Условия аренды и правила проката у {site_name}.',
            'h1' => 'Условия аренды',
        ],
        'motorcycles.index' => [
            'title' => 'Каталог мотоциклов — {site_name}',
            'description' => 'Все модели в прокате: характеристики, сценарии поездок и цены за сутки. Выберите байк и перейдите к бронированию на сайте {site_name}.',
            'h1' => 'Каталог мотоциклов',
        ],
        'prices' => [
            'title' => 'Цены на аренду мотоциклов — {site_name}',
            'description' => 'Тарифы за сутки и длительные периоды, что входит в стоимость и как рассчитать поездку с {site_name}.',
            'h1' => 'Цены на мотоциклы',
        ],
        'order' => [
            'title' => 'Заявка на аренду — {site_name}',
            'description' => 'Оставьте заявку на прокат мотоцикла: модель, даты и контакты. Менеджер {site_name} подтвердит бронь.',
            'h1' => 'Заявка на аренду',
        ],
        'reviews' => [
            'title' => 'Отзывы клиентов — {site_name}',
            'description' => 'Отзывы райдеров о прокате, сервисе и поездках с {site_name}.',
            'h1' => 'Отзывы клиентов',
        ],
        'faq' => [
            'title' => 'Вопросы и ответы — {site_name}',
            'description' => 'Документы, залог, страховка, пробег и условия выезда — ответы перед бронированием у {site_name}.',
            'h1' => 'Часто задаваемые вопросы',
        ],
        'about' => [
            'title' => 'О прокате — {site_name}',
            'description' => 'Кто мы, как работает прокат мотоциклов и чем {site_name} отличается от типичной аренды.',
            'h1' => 'О компании',
        ],
        'delivery.anapa' => [
            'title' => 'Доставка в Анапу — {site_name}',
            'description' => 'Условия доставки техники в Анапу.',
            'h1' => 'Доставка в Анапу',
        ],
        'delivery.gelendzhik' => [
            'title' => 'Доставка в Геленджик — {site_name}',
            'description' => 'Условия доставки техники в Геленджик.',
            'h1' => 'Доставка в Геленджик',
        ],
        'motorcycle.show' => [
            'title' => '{motorcycle_name} — аренда — {site_name}',
            'description' => 'Характеристики и условия проката {motorcycle_name}. Забронируйте даты на сайте {site_name}.',
            'h1' => '{motorcycle_name}',
        ],
        'booking.index' => [
            'title' => 'Онлайн-бронирование — {site_name}',
            'description' => 'Выберите мотоцикл и даты поездки, проверьте доступность и оформите бронь у {site_name}.',
            'h1' => 'Онлайн-бронирование',
        ],
        'booking.show' => [
            'title' => 'Бронирование {motorcycle_name} — {site_name}',
            'description' => 'Даты, расчёт и шаги бронирования {motorcycle_name} в прокате {site_name}.',
            'h1' => 'Бронирование: {motorcycle_name}',
        ],
        'booking.checkout' => [
            'title' => 'Оформление брони — {site_name}',
            'description' => 'Проверьте детали поездки и завершите оформление бронирования у {site_name}.',
            'h1' => 'Оформление бронирования',
        ],
        'booking.thank-you' => [
            'title' => 'Заявка принята — {site_name}',
            'description' => 'Мы получили вашу заявку на бронирование и свяжемся с вами в ближайшее время.',
            'h1' => 'Бронирование принято',
        ],
        'articles.index' => [
            'title' => 'Статьи и материалы — {site_name}',
            'description' => 'Полезные материалы о поездках, технике и прокате от {site_name}.',
            'h1' => 'Статьи',
        ],
        'page.show' => [
            'title' => '{page_name} — {site_name}',
            'description' => '{page_name} — информация на сайте {site_name}.',
            'h1' => '{page_name}',
        ],
    ],
];
