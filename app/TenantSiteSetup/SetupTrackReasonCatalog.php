<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

/**
 * Коды причин suppressed-треков → подписи для UI (без разрозненности по Blade).
 */
final class SetupTrackReasonCatalog
{
    /**
     * @return array<string, SetupTrackReasonText>
     */
    public function all(): array
    {
        return [
            'scheduling_module_disabled' => new SetupTrackReasonText(
                title: 'Модуль записи и расписания выключен',
                body: 'Дорожка «Запись и расписание» недоступна, пока модуль не включён для клиента.',
                actionHint: 'Включите модуль в настройках клиента на платформе или обратитесь в поддержку.',
            ),
            'permission_or_surface_missing' => new SetupTrackReasonText(
                title: 'Нет доступа к разделу',
                body: 'Дорожка скрыта: у текущей роли нет прав на нужный раздел кабинета или он недоступен.',
                actionHint: 'Попросите владельца кабинета выдать права или откройте раздел с учётной записью с нужной ролью.',
            ),
            'feature_or_plan' => new SetupTrackReasonText(
                title: 'Функция недоступна по тарифу или настройкам',
                body: 'Дорожка не показывается: функция отключена для этого проекта (тариф, флаги, политика).',
                actionHint: 'Уточните на платформе или у поддержки, можно ли подключить функцию для клиента.',
            ),
            'onboarding_branch_crm_only' => new SetupTrackReasonText(
                title: 'Ветка онбординга — CRM без онлайн-записи',
                body: 'Дорожка «Запись и расписание» скрыта: в профиле запуска выбран сценарий без обязательной записи по слотам (фактическая ветка CRM).',
                actionHint: 'Чтобы вести гид по записи, смените ветку в профиле запуска или согласуйте сценарий на платформе.',
            ),
        ];
    }

    public function forCode(string $code): ?SetupTrackReasonText
    {
        return $this->all()[$code] ?? null;
    }

    public function forCodeOrFallback(string $code): SetupTrackReasonText
    {
        return $this->forCode($code) ?? new SetupTrackReasonText(
            title: 'Дорожка недоступна',
            body: 'Причина: '.$code.'.',
            actionHint: null,
        );
    }
}
