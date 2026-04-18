# Полная анкета гида vs UI брифа vs автоприменение

Краткая сводка для поддержки и разработки. Общая модель уровней: [../architecture/tenant-cabinet-guide-brief-model.md](../architecture/tenant-cabinet-guide-brief-model.md).

## 1. Полная анкета (Markdown для гида)

**Файл:** [guide-onboarding-booking-notifications-questionnaire.md](guide-onboarding-booking-notifications-questionnaire.md)  
**Slug документа:** `rentbase-appointment-notifications-v1`

Содержит разделы 0–6, приложения A–C, стабильные machine-slug вопросов (`meta.*`, `biz.*`, `sched.*`, `notif.*`, …). Используется человеком для преднастройки всего контура «запись + уведомления» и контекста бизнеса.

## 2. Страница в кабинете (бриф)

**Маршрут:** `/admin/site-setup-booking-notifications`  
**Класс:** `App\Filament\Tenant\Pages\TenantSiteSetupBookingNotificationsPage`  
**Хранение черновика:** `tenant_settings` → JSON `setup.booking_notifications_questionnaire` (`BookingNotificationsQuestionnaireRepository::SETTING_KEY`)

**Поля UI (фактически):**

- `meta_brand_name`, `meta_timezone`
- Параметры пресета: `sched_duration_min`, `sched_slot_step_min`, `sched_buffer_before`, `sched_buffer_after`, `sched_horizon_days`, `sched_notice_min`, `sched_requires_confirmation` (блок виден только при `scheduling_module_enabled`)
- Получатели брифа: `dest_email`, `dest_telegram_chat_id`
- `events_enabled` — список `event_key` из `NotificationEventRegistry` (события `booking.*` скрыты, если модуль записи выключен)

Остальные вопросы полной анкеты **не** продублированы в этом UI; на странице указано, что полный перечень — в документе для гида.

## 3. Автоприменение

**Класс:** `App\TenantSiteSetup\BookingNotificationsBriefingApplier`  
**Действие:** кнопка «Применить к кабинету».

**Что создаётся/обновляется:**

- Один `BookingSettingsPreset` с именем маркера мастера (`BookingNotificationsBriefingWizardMarkers::PRESET_NAME`) — **только если** у пользователя есть `manage_scheduling` и у тенанта включён `scheduling_module_enabled`.
- До двух `NotificationDestination`: email и Telegram с фиксированными именами маркера мастера — при соответствующих правах (`manage_notification_destinations` или `manage_notifications`).
- `NotificationSubscription` на каждый отмеченный валидный `event_key` — при правах на подписки/уведомления.

**Что не делает автоприменение:** цели расписания, ресурсы, правила доступности, подключения календарей, webhook-получатели, несколько почт в брифе, организационные ответы гида.

**Ветка `crm_only`:** при несовместимости сценария с записью пресет не создаётся (см. проверку в applier по результату `TenantOnboardingBranchResolver`).

## 4. Отметка «применено»

`setup.booking_notifications_applied_at` — строка ISO8601; используется чеклистом и аудитом, не заменяет проверку фактических сущностей.
