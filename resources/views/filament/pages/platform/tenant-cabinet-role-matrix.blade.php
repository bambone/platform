<x-filament-panels::page>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400 max-w-3xl">
        Матрица сопоставляет <strong>роль участника в команде клиента</strong> (запись в <code>tenant_user</code>)
        с проверками <code>manage_*</code> и <code>export_leads</code> в кабинете клиента. Здесь задаётся то, что реально
        использует Gate для панели клиента; роли Spatie на пользователе для этого не источник истины.
    </p>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button type="submit">
                Сохранить
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
