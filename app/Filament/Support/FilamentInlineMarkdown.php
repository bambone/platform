<?php

namespace App\Filament\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Inline markdown for Filament helperText / Section::description (avoids nested &lt;p&gt; from full markdown).
 */
final class FilamentInlineMarkdown
{
    public static function toHtml(string $markdown): Htmlable
    {
        return new HtmlString(Str::inlineMarkdown($markdown));
    }
}
