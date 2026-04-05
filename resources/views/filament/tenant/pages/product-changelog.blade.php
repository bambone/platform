@php
    /** @var \App\Filament\Tenant\Pages\TenantProductChangelogPage $this */
    $grouped = $this->groupedEntries;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">История изменений продукта</x-slot>
            <x-slot name="description">
                Обновления кабинета клиента и публичного сайта. Редактирование списка выполняет команда платформы.
            </x-slot>
        </x-filament::section>

        @if ($grouped->isEmpty())
            <p class="text-sm text-gray-600 dark:text-gray-400">Пока нет опубликованных обновлений.</p>
        @else
            <div class="space-y-10">
                @foreach ($grouped as $dateKey => $entries)
                    <section class="scroll-mt-24" aria-labelledby="tenant-changelog-day-{{ $dateKey }}">
                        <h2 id="tenant-changelog-day-{{ $dateKey }}" class="border-b border-gray-200 pb-2 text-base font-semibold text-gray-950 dark:border-white/10 dark:text-white sm:text-lg">
                            {{ \Illuminate\Support\Carbon::parse($dateKey)->translatedFormat('d F Y') }}
                        </h2>
                        <div class="mt-6 space-y-8">
                            @foreach ($entries as $entry)
                                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6">
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white sm:text-lg">{{ $entry->title }}</h3>
                                    @if (filled($entry->summary))
                                        <div class="tenant-changelog-md-summary prose prose-sm dark:prose-invert mt-3 max-w-none text-sm text-gray-700 dark:text-gray-300 [&_a]:text-primary-600 dark:[&_a]:text-primary-400">
                                            {!! \Illuminate\Support\Str::markdown($entry->summary) !!}
                                        </div>
                                    @endif
                                    @if (filled($entry->body))
                                        <div class="tenant-changelog-md prose prose-sm dark:prose-invert mt-5 max-w-none text-gray-800 dark:text-gray-200 [&_a]:text-primary-600 dark:[&_a]:text-primary-400">
                                            {!! \Illuminate\Support\Str::markdown($entry->body ?? '') !!}
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
