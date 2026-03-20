<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use Filament\Pages\Page;
use UnitEnum;

class BillingPage extends Page
{
    use GrantsPlatformPageAccess;

    protected string $view = 'filament.pages.platform.placeholder';

    protected static ?string $navigationLabel = 'Биллинг';

    protected static ?string $title = 'Биллинг';

    protected static ?string $slug = 'billing';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';
}
