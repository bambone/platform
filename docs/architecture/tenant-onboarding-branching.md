# Ветвление онбординга: desired vs effective branch

Реализация: `App\TenantSiteSetup\TenantOnboardingBranchResolver`, данные профиля `SetupProfileRepository::SETTING_KEY` (`setup.profile`).

## Поля профиля (JSON)

| Поле | Описание |
|------|-----------|
| `desired_branch` | Желаемая продуктовая ветка: `crm_only`, `slot_booking`, `mixed` (MVP). Пусто — выводится из `primary_goal` эвристикой в резолвере. |
| `primary_goal` | Приоритет шагов (`SetupJourneyOrdering`): `leads`, `info`, `booking`, `catalog` — **не заменяет** ветку, см. приоритеты ниже. |
| `schema_version` | Версия схемы профиля (≥2 содержит поддержку `desired_branch`). |

## Вычисляемые поля (не хранятся в профиле, только в DTO)

| Поле | Описание |
|------|-----------|
| `effective_branch` | Что реально вести сейчас с учётом `scheduling_module_enabled` и (для смешанных сценариев) доступности записи. |
| `branch_consistency_status` | `ok`, `warning`, `blocked`, `needs_platform_action` |
| `blocking_reason` | Например `scheduling_module_disabled`, `none`, `missing_manage_scheduling` |
| `resolution_action` | Например `platform_enablement_required`, `user_permission_grant`, `none`, `client_adjust_expectations` |

## Приоритет разрешения конфликтов

1. Hard constraints платформы (`scheduling_module_enabled`, права `manage_scheduling` для предупреждений по UX).
2. Явный `desired_branch` (decisive) или эвристика из `primary_goal`.
3. Вывод `effective_branch` и статуса согласованности.
4. Сортировка шагов (`SetupJourneyOrdering`) — **после** применимости, не вместо ветки.

## Семантика `mixed`

Два first-class пути: CRM intake и слот-букинг; уведомления могут различаться по типу входа. Не «всё сразу без определения».

## Расширенный словарь веток (не MVP)

`info_site_only`, `catalog_first` — в продукте пока как `secondary_intents` / цели, отдельные `branch_id` можно добавить позже.
