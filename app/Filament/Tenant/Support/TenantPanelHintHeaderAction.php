<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Support;

use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Единый значок «?» в шапке списков/страниц: подсказка в tooltip без длинного подзаголовка.
 *
 * Для переносов строк передайте {@see HtmlString} (HTML с &lt;br&gt;) или {@see makeLines()}.
 */
final class TenantPanelHintHeaderAction
{
    public static function make(string $name, string|Htmlable $tooltip, ?string $ariaLabel = null): Action
    {
        return Action::make($name)
            ->label('')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->extraAttributes([
                'aria-label' => $ariaLabel ?? 'Справка по разделу',
            ])
            ->tooltip($tooltip)
            ->action(function (): void {});
    }

    /**
     * @param  list<string>  $lines  Строки одного абзаца разделяются одним переносом; пустая строка — разрыв между абзацами (больший интервал).
     */
    public static function makeLines(string $name, array $lines, ?string $ariaLabel = null): Action
    {
        $paragraphs = [];
        $buffer = [];
        foreach ($lines as $line) {
            if ($line === '') {
                if ($buffer !== []) {
                    $paragraphs[] = implode('<br>', array_map(static fn (string $s): string => e($s), $buffer));
                    $buffer = [];
                }

                continue;
            }
            $buffer[] = $line;
        }
        if ($buffer !== []) {
            $paragraphs[] = implode('<br>', array_map(static fn (string $s): string => e($s), $buffer));
        }

        $html = implode('<br><br>', $paragraphs);

        return self::make($name, new HtmlString($html), $ariaLabel);
    }
}
