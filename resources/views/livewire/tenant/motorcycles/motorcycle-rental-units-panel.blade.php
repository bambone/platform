<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 p-4 shadow-sm dark:border-white/10">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Единицы парка</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Экземпляры техники для учёта и бронирования. CSV только для этой карточки — подробности в форме у полей (иконка «i»).
        </p>

        @if ($unitsCount === 0)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
                <p class="font-medium">Список пуст</p>
                <p class="mt-1 text-amber-900/90 dark:text-amber-100/90">
                    Добавьте строку через «Добавить единицу» или импорт CSV.
                </p>
            </div>
        @endif

        <p class="mt-4 text-sm font-medium text-gray-800 dark:text-gray-200">
            Единиц в карточке: {{ $unitsCount }}
        </p>
    </div>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
