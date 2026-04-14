<?php

namespace App\Filament\Tenant\Support;

use Filament\Facades\Filament;
use Filament\Tables\Table;

final class TenantFilamentTablePagination
{
    /**
     * Унифицированные настройки постраничности для панели tenant `admin`:
     * без 5/10, минимум 25 записей по умолчанию (первый пункт селекта после сортировки).
     */
    public static function applyForAdminPanel(Table $table): void
    {
        if (Filament::getCurrentOrDefaultPanel()?->getId() !== 'admin') {
            return;
        }

        if (! $table->isPaginated()) {
            return;
        }

        $options = $table->getPaginationPageOptions();

        $numeric = [];
        $suffix = [];
        foreach ($options as $option) {
            if ($option === 'all') {
                $suffix[] = 'all';

                continue;
            }

            if (is_int($option)) {
                if ($option === 5 || $option === 10) {
                    continue;
                }
                $numeric[] = $option;

                continue;
            }

            if (is_string($option) && ctype_digit($option)) {
                $n = (int) $option;
                if ($n === 5 || $n === 10) {
                    continue;
                }
                $numeric[] = $n;

                continue;
            }

            $suffix[] = $option;
        }

        foreach ([25, 50, 100] as $mandatory) {
            if (! in_array($mandatory, $numeric, true)) {
                $numeric[] = $mandatory;
            }
        }

        sort($numeric);

        $newOptions = array_merge($numeric, $suffix);

        $table->paginationPageOptions($newOptions);

        if ($newOptions === []) {
            return;
        }

        $default = $table->getDefaultPaginationPageOption();
        if (in_array($default, [5, 10, '5', '10'], true)) {
            $table->defaultPaginationPageOption(25);

            return;
        }

        if (! in_array($default, $newOptions, true)) {
            $table->defaultPaginationPageOption(25);
        }
    }
}
