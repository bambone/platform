<x-filament-panels::page>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400 max-w-3xl">
        Пошагово создайте клиента платформы: запись, копию сайта из шаблона, поддомен и стартовые настройки.
        Шаги 5–6 подсказывают, что сделать дальше после создания.
    </p>
    <form wire:submit="create">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Создать клиента
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
