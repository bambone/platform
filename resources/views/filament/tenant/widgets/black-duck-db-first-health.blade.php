@php
    $flags = $this->flags;
    $show = ($flags['media_empty_db'] ?? false) || ($flags['service_catalog_degraded'] ?? false);
@endphp
@if ($show)
    <x-filament-widgets::widget class="!bg-transparent dark:!bg-transparent">
        <div
            class="rounded-xl border border-amber-200/90 bg-amber-50/90 p-4 shadow-sm sm:p-5 dark:border-amber-500/20 dark:bg-amber-950/35 dark:shadow-[0_1px_2px_rgba(0,0,0,0.28)]"
            role="status"
        >
            <h3 class="text-base font-semibold tracking-tight text-amber-950 dark:text-amber-100/95">
                Black Duck: внимание к DB-first
            </h3>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-amber-950/90 dark:text-amber-100/80">
                @if ($flags['media_empty_db'] ?? false)
                    <li>
                        <strong>Каталог медиа в БД пуст</strong> (таблица
                        <code class="text-xs">tenant_media_assets</code>
                        существует, строк нет). Сайт читает БД, а не
                        <code class="text-xs">media-catalog.json</code>
                        — портфолио, proof, часть главной будут пустыми, пока не выполните
                        <code class="text-xs">php artisan tenant:black-duck:import-media-catalog-to-db</code>
                        (и при необходимости затем
                        <code class="text-xs">tenant:black-duck:refresh-content</code>).
                    </li>
                @endif
                @if ($flags['service_catalog_degraded'] ?? false)
                    <li>
                        <strong>Нет видимых услуг в каталоге</strong> — форма контактов и селектор направлений
                        ориентируются на
                        <code class="text-xs">tenant_service_programs</code>: пока нет подходящих записей, выбор
                        услуги не отобразится, поле
                        <code class="text-xs">inquiry_service_slug</code>
                        не требуется. Наполните каталог (услуги) или отметьте программы как видимые.
                    </li>
                @endif
            </ul>
        </div>
    </x-filament-widgets::widget>
@endif
