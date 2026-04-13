<x-filament-panels::page>
    <p class="mb-4 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
        Двухконтурная схема: запись (local / R2 / dual) и отдача (локальный путь <code class="text-xs">/media/…</code> или R2).
        Подробности — <code class="text-xs">docs/operations/tenant-media-local-mirror.md</code>.
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
