<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use Filament\Pages\Page;
use UnitEnum;

class FeatureFlagsPage extends Page
{
    use GrantsPlatformPageAccess;

    protected string $view = 'filament.pages.platform.placeholder';

    protected static ?string $navigationLabel = 'Feature Flags';

    protected static ?string $title = 'Feature Flags';

    protected static ?string $slug = 'feature-flags';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';
}
