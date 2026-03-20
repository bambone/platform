<?php

namespace App\Filament\Platform\Pages;

use UnitEnum;

class SystemAuditPage extends PlatformPlaceholderPage
{
    protected static ?string $navigationLabel = 'Аудит и здоровье';

    protected static ?string $title = 'Аудит и состояние системы';

    protected static ?string $slug = 'system-audit';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';

    protected static function placeholderMeta(): array
    {
        return [
            'headline' => 'Аудит и мониторинг',
            'intro' => 'Централизованный обзор важных событий, ошибок и проверок работоспособности окружения.',
            'future' => 'Журнал действий, проверки интеграций, метрики доступности и уведомления о сбоях.',
            'audience' => 'Администраторы платформы и техническая поддержка.',
        ];
    }
}
