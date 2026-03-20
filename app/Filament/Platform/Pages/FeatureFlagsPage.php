<?php

namespace App\Filament\Platform\Pages;

use UnitEnum;

class FeatureFlagsPage extends PlatformPlaceholderPage
{
    protected static ?string $navigationLabel = 'Функции (флаги)';

    protected static ?string $title = 'Функции платформы';

    protected static ?string $slug = 'feature-flags';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';

    protected static function placeholderMeta(): array
    {
        return [
            'headline' => 'Управление функциями',
            'intro' => 'Раздел для поэтапного включения возможностей платформы у клиентов без публикации нового кода.',
            'future' => 'Список функций, включение для выбранных клиентов или тарифов, журнал изменений.',
            'audience' => 'Владелец платформы и администраторы.',
        ];
    }
}
