# Модель: кабинет тенанта, гид, бриф (RentBase)

Цель: **не смешивать** полный функционал кабинета, путь онбординга и автоприменение настроек. Подробный сценарий по этапам жизни клиента и ветвлению — в [tenant-onboarding-branching.md](tenant-onboarding-branching.md) (контракт `desired_branch` / `effective_branch`).

## Три уровня (инвариант)

1. **Полный кабинет тенанта** — всё, что клиент может настраивать в Filament (`/admin`): настройки, контент, каталог, маркетинг, инфраструктура, запись, операции, команда. Это **максимальный контур**, не равен «мастеру запуска».

2. **Онбординг / гид / launch center** — **сценарий запуска**: приоритеты, чеклист, подсказки. Реестр шагов: `App\TenantSiteSetup\SetupItemRegistry` и провайдеры. Явно зафиксировано в коде: реестр **не исчерпывает** весь кабинет.

3. **Автоприменение** — то, что запись в БД выполняется **без участия человека** по нажатию «Применить». Сейчас: `App\TenantSiteSetup\BookingNotificationsBriefingApplier` (пресет мастера, получатели email/Telegram, подписки на события). Это **узкий** слой внутри брифа.

## Четвёртый связанный слой (документ для гида)

**Полная анкета Markdown** для человека-гида: [../operations/guide-onboarding-booking-notifications-questionnaire.md](../operations/guide-onboarding-booking-notifications-questionnaire.md) (`rentbase-appointment-notifications-v1`). Она **шире**, чем поля страницы брифа в кабинете, и **шире**, чем автоприменение.

| Слой | Охват |
|------|--------|
| Документ для гида | Мета, бизнес-контекст, команда, каналы, матрица событий, расписание, календари, проверки |
| Страница «Запись и уведомления (бриф)» | `TenantSiteSetupBookingNotificationsPage`: бренд, TZ, пресет, один email, один Telegram, список событий |
| Автоприменение | Только пресет (если модуль записи + права), два получателя, правила по выбранным событиям |

**Правильная формулировка для продукта и поддержки:** гид собирает **structured onboarding brief**; кабинет **шире**; автоприменение — **часть** брифа. Не называть бриф или гид «полной настройкой кабинета».

## Ссылки

- Шпаргалка гида → сущности UI: [../operations/guide-onboarding-booking-notifications-mapping.md](../operations/guide-onboarding-booking-notifications-mapping.md)
- Полная анкета vs UI vs applier: [../operations/guide-brief-vs-ui-vs-applier.md](../operations/guide-brief-vs-ui-vs-applier.md)
- Реестр полей (инвентаризация): [tenant-admin-field-registry.md](tenant-admin-field-registry.md)
- Маппинг вопросов: [tenant-onboarding-question-mapping.md](tenant-onboarding-question-mapping.md)
- Master-questionnaire (10 блоков): [../operations/master-questionnaire-spec.md](../operations/master-questionnaire-spec.md)
- Ветвление и согласованность с платформой: [tenant-onboarding-branching.md](tenant-onboarding-branching.md)
- Product signals (JSON): `App\TenantSiteSetup\SetupProductSignalsRepository`, ключ `setup.product_signals`

## Код (ориентиры)

| Что | Где |
|-----|-----|
| Группы меню tenant admin | `App\Providers\Filament\AdminPanelProvider` |
| Чеклист / guided session | `App\TenantSiteSetup\*` |
| Бриф | `App\Filament\Tenant\Pages\TenantSiteSetupBookingNotificationsPage` |
| Автоприменение | `App\TenantSiteSetup\BookingNotificationsBriefingApplier` |
| Профиль запуска (цель, желаемая ветка) | `SetupProfileRepository`, `TenantSiteSetupProfilePage` |
| Разрешение ветки desired vs effective | `App\TenantSiteSetup\TenantOnboardingBranchResolver` |
