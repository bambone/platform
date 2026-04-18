# Шпаргалка гида: ответы анкеты → кабинет клиента (RentBase)

Краткая связка **slug / раздел анкеты** с действиями в tenant-панели (`/admin`). Полная анкета: [guide-onboarding-booking-notifications-questionnaire.md](guide-onboarding-booking-notifications-questionnaire.md).

Слои «анкета / UI брифа / автоприменение»: [guide-brief-vs-ui-vs-applier.md](guide-brief-vs-ui-vs-applier.md). Модель кабинета и гида: [../architecture/tenant-cabinet-guide-brief-model.md](../architecture/tenant-cabinet-guide-brief-model.md). Ветки онбординга (`crm_only` / `slot_booking` / `mixed`): профиль `/admin/site-setup-profile`, код `TenantOnboardingBranchResolver`.

## Где в кабинете

| Зона меню | Что настраивается |
|-----------|---------------------|
| **Запись и расписание** | Услуги (запись), группы настроек записи, цели расписания, ресурсы, доступность, исключения, занятости, календари |
| **Настройки → Получатели уведомлений** | `NotificationDestination` |
| **Настройки → Правила уведомлений** | `NotificationSubscription` + `event_key` из реестра |
| **Настройки → История доставок** | Проверка ошибок (не конфигурация) |

Доступ: модуль записи — `Tenant.scheduling_module_enabled` + `manage_scheduling`; уведомления — `manage_notifications` / связанные ability.

---

## Маппинг по блокам анкеты

| Тема | Slug (ориентиры) | Действие в кабинете |
|------|------------------|---------------------|
| Каталог и онлайн-запись | `biz.*`, `sched.services.online_enabled`, `sched.preset.groups` | Включить онлайн-запись у нужных услуг; согласовать число **групп настроек записи** |
| Пресеты | `sched.booking.confirmation_mode`, `sched.preset.duration_min`, `sched.preset.slot_step_min`, `sched.horizon_and_notice`, `sched.buffers`, `sched.preset.groups` | **Группы настроек записи**: длительность, шаг слота, буферы, горизонт, min notice, ручное подтверждение |
| Цели расписания | `biz.schedules.count`, `sched.targets_resources` | **Цели расписания** (`SchedulingTarget`) |
| Ресурсы | те же + уточнение параллельных линий | **Ресурсы расписания** (`SchedulingResource`) |
| Доступность | `sched.availability.rules` | **Правила доступности**, **исключения**, при необходимости **ручные занятости** |
| Календари и риск | `sched.calendar.integrations`, `sched.integration_risk_policy` | **Календари (подключения)**; политики на тенанте (ошибки интеграции / stale busy) |
| Получатели | `team.*`, `notif.dest.*` | **Получатели уведомлений**: тип канала, shared/personal, `config_json` |
| Правила | матрица раздела 4, `team.critical_24x7` | **Правила уведомлений**: событие → получатели; эскалации; digest |
| Проверка | раздел 6 анкеты | Смоук-тесты, **История доставок**, при календарях — страница здоровья синка |

---

## События (`event_key`) → напоминание

Список актуален, пока совпадает с `App\NotificationCenter\NotificationEventRegistry`:

`crm_request.created`, `crm_request.status_changed`, `crm_request.note_added`, `crm_request.first_viewed`, `crm_request.follow_up_due`, `lead.created`, `crm_request.unviewed_5m`, `crm_request.unprocessed_15m`, `booking.created`, `booking.cancelled`, `digest.daily_operations`.

---

## Каналы (`NotificationChannelType`)

`email`, `telegram`, `web_push`, `web_push_onesignal`, `webhook`, `sms`, `vk`, `in_app`.

---

## Одна страница для печати

1. Получатели созданы и названы понятно для матрицы правил.  
2. Правила покрывают минимум: новая заявка + (если есть запись) новое бронирование.  
3. Эскалации и digest — осознанное «да/нет» и адресаты.  
4. После настройки — чеклист раздела 6 полной анкеты.
