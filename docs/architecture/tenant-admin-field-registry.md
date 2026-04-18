# Реестр полей кабинета тенанта (инвентаризация)

**Назначение:** опора для «полного покрытия» вопросником и mapping; пополнять при новых полях Filament. Полный перечень колонок моделей не дублируется — только входы администратора по зонам меню.

Колонки-цель для расширения строк: `screen`, `resource_or_page`, `field_key`, `type`, `required`, `visibility_condition`, `question_slug`, `apply_strategy`, `owner`, `version`.

## Запуск сайта| resource_or_page | Поля / смысл |
|------------------|--------------|
| `TenantSiteSetupCenterPage` | Обзор чеклиста (не поля сущности) |
| `TenantSiteSetupProfilePage` | `business_focus`, `primary_goal`, `desired_branch`, `additional_notes` |
| `TenantSiteSetupBookingNotificationsPage` | см. [guide-brief-vs-ui-vs-applier.md](../operations/guide-brief-vs-ui-vs-applier.md) |

## Настройки

| resource_or_page | Поля / смысл |
|------------------|--------------|
| `Settings` | `general.*`, `branding.*`, `contacts.*`, `programs.*`, `reviews.*`, аналитика (вкладки) — см. `FORM_FIELD_TO_SETTING_KEY` в классе |
| `ContactChannelsPage` | Состояние каналов для форм (`contact_channels.state`) |
| `TerminologySettings` | Термины навигации |
| `NotificationDestinationResource` | Получатели: тип, имя, config, shared |
| `NotificationSubscriptionResource` | Правила: event_key, получатели |
| `NotificationBrowserSettingsPage` | Браузерные уведомления |
| `TenantPushPwaSettingsPage` | PWA / push |
| `UserResource` | Команда, роли pivot |

## Каталог

| resource_or_page | Поля / смысл |
|------------------|--------------|
| `TenantServiceProgramResource` | Программы / услуги витрины |
| `MotorcycleResource` | Витрина (мото) |
| `RentalUnitResource` | Единицы аренды |

## Контент

| resource_or_page | Поля / смысл |
|------------------|--------------|
| `PageResource` | Конструктор страниц, секции |
| `FaqResource` | FAQ |
| `ReviewResource` | Отзывы |
| `TenantFilesPage` | Файлы |

## Маркетинг

| resource_or_page | Поля / смысл |
|------------------|--------------|
| `SeoLandingPageResource` | SEO лендинги |
| `LocationLandingPageResource` | Локационные лендинги |
| `RedirectResource` | Редиректы |
| `SeoFiles` | SEO файлы |

## Инфраструктура

| resource_or_page | Поля / смысл |
|------------------|--------------|
| `CustomDomainResource` | Домены |
| `TenantLocationResource` | Локации |
| `IntegrationResource` | Интеграции |
| `StorageMonitoringPage` | Квоты |

## Запись (4 группы)

| resource_or_page | Поля / смысл |
|------------------|--------------|
| `SchedulingTargetResource`, `SchedulingResourceResource`, `BookableServiceResource`, `BookingSettingsPresetResource` | Ядро записи |
| `AvailabilityRuleResource`, `AvailabilityExceptionResource`, `ManualBusyBlockResource` | Доступность |
| `CalendarConnectionResource`, `CalendarOccupancyMappingResource`, `CalendarSyncHealthPage` | Календари |
| `OccupancyPreviewPage`, `SlotDebugPage` | Инструменты |

## Операции

| resource_or_page | Поля / смысл |
|------------------|--------------|
| `LeadResource`, `CrmRequestResource`, `BookingResource`, `BookingCalendarPage` | Лиды, CRM, брони |

Условие доступности группы «Запись»: `Tenant.scheduling_module_enabled` и соответствующие ability.
