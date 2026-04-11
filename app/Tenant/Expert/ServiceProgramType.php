<?php

namespace App\Tenant\Expert;

use App\Models\TenantServiceProgram;

/**
 * Allowed values for {@see TenantServiceProgram::$program_type} (MVP).
 */
enum ServiceProgramType: string
{
    case Program = 'program';
    case SingleSession = 'single_session';
    case RouteTraining = 'route_training';
    case SportSupport = 'sport_support';

    public function label(): string
    {
        return match ($this) {
            self::Program => 'Программа / курс',
            self::SingleSession => 'Разовое занятие',
            self::RouteTraining => 'Маршрут / практика',
            self::SportSupport => 'Спорт / сопровождение',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
