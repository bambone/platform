<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

enum TenantOnboardingBlockingReason: string
{
    case None = 'none';
    case SchedulingModuleDisabled = 'scheduling_module_disabled';
    case MissingManageScheduling = 'missing_manage_scheduling';

    public function label(): string
    {
        return match ($this) {
            self::None => '',
            self::SchedulingModuleDisabled => 'Модуль записи и расписания выключен для этого клиента.',
            self::MissingManageScheduling => 'У текущего пользователя нет права «Запись и расписание» — часть шагов настроит другой администратор.',
        };
    }
}
