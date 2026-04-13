# Календарь бронирований (tenant admin)

## URL и панель

- Путь: `/admin/bookings/calendar` (Filament Page панели `admin`, slug `bookings/calendar`).
- Имя маршрута: `filament.admin.pages.bookings.calendar`.
- Доступ: `BookingPolicy::viewAny` (ability `manage_bookings` через Gate в tenant-панели). Отдельной ability в v1 нет.

## Фильтры, терминология и доступность

- Подписи фильтров календаря берутся из **TenantTerminologyService** (`DomainTermKeys::FLEET_UNIT`, `RESOURCE_PLURAL`, `CATEGORY`) — совпадают с preset терминологии тенанта (`domain_localization_preset_id`), а не только с темой сайта.
- При **`theme_key = expert_auto`** скрыт фильтр по **единице парка** (сценарий записей/занятий без «аренды юнита»); при mount сбрасывается `rental_unit_id` из query string.
- **Программы обучения** (`TenantServiceProgramResource`, тема `expert_auto`): ссылка в шапке страницы и в блоке быстрых ссылок над календарём (даже если модуль расписания выключен).
- **Свободные слоты и график** — при `scheduling_module_enabled` и праве `manage_scheduling` в том же блоке: ресурсы расписания, услуги, при `calendar_integrations_enabled` — календарные подключения (группа меню **«Запись и расписание»**).

## Источник событий

- Только модель `Booking` со статусами из `Booking::occupyingStatusValues()` (`pending`, `awaiting_payment`, `confirmed`).
- CRM-заявки и сырые лиды **не** определяют занятость.
- События отдаёт Livewire-метод `BookingCalendarPage::fetchEvents` (без отдельного публичного REST API).

### CRM «подтвердили» vs строка в `bookings`

Полоса таймлайна в CRM и слоты календаря парка строятся из **`bookings`**, а не из текста в ленте активности и не из произвольных подписей статусов.

- Вход с формы бронирования создаёт `CrmRequest` + `Lead`, но **не** создаёт `Booking` автоматически при создании лида.
- При переводе CRM-заявки в статус **«Конверсия»** (`CrmRequest::STATUS_CONVERTED`) для `request_type = tenant_booking` вызывается `TenantBookingFromCrmConverter`: создаётся бронь `confirmed` по данным Lead (источник `crm_converted`, идемпотентно).
- Если в кабинете у обращения (`Lead`) выставлен статус **«Подтверждена»** и заполнены техника и даты аренды, при сохранении через `LeadObserver` также создаётся бронь при отсутствии строки по `lead_id` (источник `lead_confirmed`).
- Backfill: `php artisan bookings:materialize-from-crm-converted` досоздаёт брони и для CRM-конверсий, и для уже подтверждённых обращений без `Booking` (опционально `--tenant=slug_или_id`).

## Query string как источник истины для навигации

- `view` (`month` | `week`) и `date` (`Y-m-d`) синхронизируются с URL (`#[Url(history: true)]`) и обновляются при смене вида/даты в FullCalendar (`syncCalendarNav`).
- Нормализация `date`: для `month` / `week` открывается период, **содержащий** дату; при невалидном или пустом значении — **today** в timezone тенанта.
- Дополнительно: `rental_unit_id`, `motorcycle_id`, `booking_id` (подсветка), `crm_request_id` (prefilter через `whereHas lead`).

## Нормализация интервалов

Единый слой: `App\Bookings\Calendar\BookingCalendarRangeNormalizer`.

- **Timed:** если заданы **оба** `start_at` и `end_at` и при наличии `start_date`/`end_date` календарный первый/последний день интервала в TZ тенанта **совпадает** с этими колонками — в FullCalendar уходят как есть (в TZ тенанта).
- Если даты и время заданы, но суточный период по timestamp **не совпадает** с `start_date`/`end_date` (как в CRM-таймлайне по дням), календарь строит **all-day** по колонкам дат — чтобы не терять бронь при «кривых» `start_at`/`end_at`.
- **Иначе:** all-day по `start_date` / `end_date`; для FullCalendar конец **exclusive**: `normalized_end = end_date + 1 day` (дата строкой `Y-m-d`).
- Если заполнен только один из `start_at` / `end_at` — **не** строим timed; используется ветка all-day по датам.

Выборка SQL (`BookingCalendarQuery`) пересекает окно либо по `start_at`/`end_at`, либо по `start_date`/`end_date` (без требования «обнулить» время), в духе CRM и сервисов доступности.

## Конфликты

- `BookingCalendarConflictDetector`: пересечение только при одинаковом непустом `rental_unit_id` и успешной нормализации интервала у **обеих** броней.
- По одному `motorcycle_id` без юнита конфликты в v1 **не** помечаются.

## Фильтры

- `BookingCalendarFiltersData`: нормализация из Livewire + query; проверка принадлежности ID текущему tenant.
- Фильтры применяются в `BookingCalendarQuery` на уровне SQL (`where` / `whereHas`).

## Лимит диапазона `fetchEvents`

- Максимум `App\Bookings\Calendar\BookingCalendarConstants::MAX_VISIBLE_RANGE_DAYS` (62) календарных дней окна.
- При превышении — `ValidationException`; на странице перехватывается, показывается Filament Notification, события не отдаются (пустой набор).

## CRM-ссылка в payload

- Формируется только при `lead_id`, `lead.crm_request_id`, и `lead.tenant_id` совпадает с текущим tenant.
- Подгрузка: `with(['lead', ...])` без N+1.

## Стили FullCalendar

- Файл: `resources/css/booking-calendar.css` — отдельный entry в `vite.config.js` и в шаблоне страницы: `@vite(['resources/css/booking-calendar.css', 'resources/js/booking-calendar.js'])`, чтобы в HTML всегда был явный `<link rel="stylesheet" …>` на `booking-calendar-*.css` (не только CSS-чанк у JS).
- После правок на проде: `npm run build` и выкладка всего `public/build` + актуальный `manifest.json`.
- Переопределения завязаны на `#booking-calendar-host`, чтобы перебить инжектированный FullCalendar stylesheet по специфичности.
- Неделя (`timeGridWeek`): в `booking-calendar.js` задано `slotDuration` / `snapDuration` / `slotLabelInterval` **1 час** (вместо дефолтных 30 минут), в CSS — ослаблены лишние границы слотов и колонок, чтобы сетка не выглядела «решетом».

## Цвета статусов

- Только `BookingStatusPresentation` (PHP). JS не содержит своей карты статус→цвет.
- Таблица бронирований (`BookingResource`) использует те же подписи и цвета badge.

## Расширения (не в v1)

- События из `availability_calendar` без брони, resource timeline, day view, drag-and-drop, отдельная ability `view_booking_calendar`.
