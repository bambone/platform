<?php

/**
 * Лендинг тенанта: дефолты контента и имена файлов.
 *
 * Публичные картинки/видео темы отдавайте через {@see theme_platform_asset_url()} и
 * {@see tenant_theme_public_url()}; префикс legacy — {@see config('themes.legacy_asset_url_prefix')}.
 *
 * @deprecated motolevins_public_prefix — оставлен для обратной совместимости, предпочтительно themes + helper.
 */
return [
    'motolevins_public_prefix' => 'images/motolevins',

    /** Имя файла hero-видео внутри public/{motolevins_public_prefix}/videos/ */
    'motolevins_hero_video' => 'Moto_levins_1.mp4',

    /**
     * Fallback для карточек каталога на главной: если у мотоцикла не заполнены поля в БД,
     * подставляются значения по slug категории (редакторский текст, не техданные).
     * Ключ = category.slug.
     */
    'catalog_card_defaults_by_category_slug' => [
        'sport-turist' => [
            'positioning' => 'Баланс динамики и комфорта на длинной дистанции.',
            'scenario' => 'Туристу и трассе',
            'highlights' => ['long_route', 'wind_protection', 'passenger'],
            'detail_use_case' => [
                'Комфорт на длинных днях в седле',
                'Удобно с багажом и пассажиром',
                'Хорошая защита от встречного ветра',
            ],
        ],
        'neiked' => [
            'positioning' => 'Прямая посадка и отзывчивый характер в городе.',
            'scenario' => 'Город и короткие выезды',
            'highlights' => ['city', 'agile', 'easy_ride'],
            'detail_use_case' => [
                'Быстрые перемещения по городу',
                'Обзорность и контроль в потоке',
                'Легко парковаться и маневрировать',
            ],
        ],
        'maksiskuter' => [
            'positioning' => 'Минимум переключений — максимум спокойствия в потоке.',
            'scenario' => 'Новичку и ежедневным поездкам',
            'highlights' => ['automatic', 'city', 'comfortable_seat'],
            'detail_use_case' => [
                'Не нужно отрабатывать сцепление в пробках',
                'Предсказуемый характер для спокойной езды',
                'Удобная посадка на каждый день',
            ],
        ],
        'kruizer' => [
            'positioning' => 'Расслабленная посадка и ровный ход на дальняк.',
            'scenario' => 'Путешествия вдвоём',
            'highlights' => ['long_route', 'passenger', 'comfortable_seat'],
            'detail_use_case' => [
                'Расслабленная посадка на много часов',
                'Комфортно вдвоём на маршруте',
                'Ровный, предсказуемый ход',
            ],
        ],
        'doroznyi' => [
            'positioning' => 'Универсал для трассы, загорода и спокойной езды.',
            'scenario' => 'Смешанный сценарий',
            'highlights' => ['mixed_road', 'comfortable_seat', 'passenger'],
            'detail_use_case' => [
                'И город, и выезд на трассу без компромисса',
                'Удобно для спокойного темпа',
                'Подходит для поездок вдвоём',
            ],
        ],
        'turenduro' => [
            'positioning' => 'Высокий клиренс и уверенность на неровном покрытии.',
            'scenario' => 'Побережье и лёгкий оффроуд',
            'highlights' => ['high_seat', 'versatility', 'travel'],
            'detail_use_case' => [
                'Увереннее на неровном покрытии и грунте',
                'Высокая посадка и хороший обзор',
                'Универсал для смешанного маршрута',
            ],
        ],
    ],
];
