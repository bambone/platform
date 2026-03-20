<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use Filament\Pages\Page;
use UnitEnum;

class SupportImpersonationPage extends Page
{
    use GrantsPlatformPageAccess;

    protected string $view = 'filament.pages.platform.placeholder';

    protected static ?string $navigationLabel = 'Support / Impersonation';

    protected static ?string $title = 'Support / Impersonation';

    protected static ?string $slug = 'support-impersonation';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';
}
