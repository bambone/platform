@php($c = $this->getPlaceholderMeta())
<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
            {{ $c['status_note'] ?? 'В разработке' }}
        </p>
        <h2 class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">
            {{ $c['headline'] }}
        </h2>
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400 max-w-2xl">
            {{ $c['intro'] }}
        </p>
        <div class="mt-6 border-t border-gray-100 pt-4 dark:border-white/10">
            <p class="text-sm font-medium text-gray-950 dark:text-white">Что здесь будет</p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 max-w-2xl">
                {{ $c['future'] }}
            </p>
        </div>
        <div class="mt-4">
            <p class="text-sm font-medium text-gray-950 dark:text-white">Для кого</p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 max-w-2xl">
                {{ $c['audience'] }}
            </p>
        </div>
    </div>
</x-filament-panels::page>
