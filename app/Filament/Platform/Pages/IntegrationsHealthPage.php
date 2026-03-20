<?php

namespace App\Filament\Platform\Pages;

use UnitEnum;

class IntegrationsHealthPage extends PlatformPlaceholderPage
{
    protected static ?string $navigationLabel = 'Состояние интеграций';

    protected static ?string $title = 'Интеграции платформы';

    protected static ?string $slug = 'integrations-health';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';

    protected static function placeholderMeta(): array
    {
        return [
            'headline' => 'Здоровье интеграций',
            'intro' => 'Обзор внешних сервисов, от которых зависит платформа: статусы API, очереди, ошибки webhook.',
            'future' => 'Сводка по ключевым интеграциям, последние ошибки, ссылки в логи.',
            'audience' => 'Администраторы платформы и DevOps.',
        ];
    }
}
