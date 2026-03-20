<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use Filament\Pages\Page;
use UnitEnum;

class SystemAuditPage extends Page
{
    use GrantsPlatformPageAccess;

    protected string $view = 'filament.pages.platform.placeholder';

    protected static ?string $navigationLabel = 'Аудит и здоровье';

    protected static ?string $title = 'System Audit / Health';

    protected static ?string $slug = 'system-audit';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';
}
