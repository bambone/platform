<?php

namespace App\Filament\Platform\Pages;

use UnitEnum;

class SupportImpersonationPage extends PlatformPlaceholderPage
{
    protected static ?string $navigationLabel = 'Поддержка и вход от имени';

    protected static ?string $title = 'Поддержка клиентов';

    protected static ?string $slug = 'support-impersonation';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';

    protected static function placeholderMeta(): array
    {
        return [
            'headline' => 'Инструменты поддержки',
            'intro' => 'Безопасные сценарии помощи клиенту: просмотр контекста и (при политике компании) вход от имени пользователя с журналированием.',
            'future' => 'Запрос доступа к кабинету клиента, ограниченное время сессии, отчёт кто и когда выполнял действия.',
            'audience' => 'Специалисты поддержки и администраторы платформы.',
        ];
    }
}
