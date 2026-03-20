<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use Filament\Pages\Page;

/**
 * Единый шаблон страниц «в разработке» для консоли платформы.
 *
 * @phpstan-type PlaceholderMeta array{
 *   headline: string,
 *   intro: string,
 *   future: string,
 *   audience: string,
 *   status_note?: string
 * }
 */
abstract class PlatformPlaceholderPage extends Page
{
    use GrantsPlatformPageAccess;

    protected string $view = 'filament.pages.platform.placeholder';

    /**
     * @return PlaceholderMeta
     */
    abstract protected static function placeholderMeta(): array;

    /**
     * @return PlaceholderMeta
     */
    public function getPlaceholderMeta(): array
    {
        return static::placeholderMeta();
    }
}
