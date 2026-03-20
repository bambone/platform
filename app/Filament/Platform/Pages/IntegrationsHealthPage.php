<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use Filament\Pages\Page;
use UnitEnum;

class IntegrationsHealthPage extends Page
{
    use GrantsPlatformPageAccess;

    protected string $view = 'filament.pages.platform.placeholder';

    protected static ?string $navigationLabel = 'Интеграции';

    protected static ?string $title = 'Integrations Health';

    protected static ?string $slug = 'integrations-health';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';
}
