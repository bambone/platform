{{-- Показывается на время Livewire-запроса при изменении data.type (см. Select::make('type')->live()). --}}
<div
    wire:loading
    wire:target="data.type"
    class="flex shrink-0 items-center justify-center"
    role="status"
    aria-live="polite"
    aria-label="Обновление полей формы"
>
    <x-filament::loading-indicator class="h-5 w-5 text-primary-600 dark:text-primary-400" />
</div>
