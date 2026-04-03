{{--
    Нативный <select> для кастомных Blade/Livewire внутри панелей Filament.
    Класс fi-select-input совпадает с нативным Select Filament — в tenant-admin.css / platform-admin.css
    для него задан color-scheme по html.dark, чтобы список опций не оставался «светлым» в тёмной теме.
--}}
@props([])
<select {{ $attributes->class([
    'fi-select-input',
    'block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-950',
    'focus:outline-none focus:ring-2 focus:ring-primary-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white',
]) }}>
    {{ $slot }}
</select>
