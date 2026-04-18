# Маппинг вопросов онбординга (slug → ветка, роль, apply)

Связка с полной анкетой: [../operations/guide-onboarding-booking-notifications-questionnaire.md](../operations/guide-onboarding-booking-notifications-questionnaire.md).  
Расширенная модель уровней: [tenant-cabinet-guide-brief-model.md](tenant-cabinet-guide-brief-model.md).

## Колонки матрицы

| Колонка | Значение |
|---------|----------|
| `question_slug` | Стабильный ключ из анкеты |
| `apply_class` | `apply` / `conditional_apply` / `guide_only` / `product_signal` |
| `allowed_branches` | `*` или список: `crm_only`, `slot_booking`, `mixed` |
| `forbidden_branches` | Список веток, где вопрос N/A |
| `branch_decision_role` | `decisive` / `supportive` / `informational` |
| `branch_decision_weight` | Число (опц.); выше — сильнее влияние при конфликте decisive |
| `target` | Куда пишется: `tenant_setting`, `Filament resource`, KB only |

## Примеры строк

| question_slug | apply_class | allowed_branches | forbidden_branches | branch_decision_role | branch_decision_weight | target |
|---------------|-------------|------------------|--------------------|----------------------|------------------------|--------|
| `biz.online_booking.need` | conditional_apply | slot_booking, mixed | crm_only | decisive | 100 | derived `desired_branch` + platform flags |
| `biz.vertical` | guide_only | * | — | supportive | 20 | гид / `setup.profile` notes |
| `meta.brand_name` | apply | * | — | supportive | 10 | UI бриф / `tenant_settings` via applier prefill |
| `sched.calendar.integrations` | product_signal | * | — | informational | 0 | `setup.product_signals.calendar_signals` |
| `notif.dest.web_push` | conditional_apply | * | — | supportive | 30 | `NotificationDestination` (вне брифа UI) |
| `Q3.6` SMS/VK | product_signal | * | — | informational | 0 | KB / backlog |

## MVP веток

Для decision graph используются только `crm_only`, `slot_booking`, `mixed`. Остальные сценарии — через `primary_goal` и будущие `secondary_intents`.

## Календари

Структурированный сигнал хранится в `setup.product_signals` (см. `SetupProductSignalsRepository`), не в одном textarea. Поля см. спецификацию JSON в репозитории.
