<x-filament-panels::page>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        Каналы для публичной формы на маркетинговом сайте. Смысл и тон подсказок согласованы с разделом «Уведомления (провайдеры)» и с логикой доставки в кабинетах тенантов — без отдельного «языка» для маркетинга.
    </p>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Сохранить
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
