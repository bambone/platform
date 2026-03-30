@php
    $record = $getRecord();
@endphp

@if($record)
    <div class="fi-drawer-sticky-bottom hidden sm:hidden items-center justify-between">
        <div class="flex items-center gap-2 w-full">
            <a href="tel:{{ preg_replace('/[^0-9]/', '', $record->phone) }}" 
               class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-medium rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 active:bg-gray-200 dark:active:bg-gray-700 transition">
                <x-heroicon-o-phone class="w-5 h-5 text-gray-500" />
                <span>Call</span>
            </a>
            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $record->phone) }}?text={{ urlencode('Здравствуйте! Пишу по поводу '.tenant_term(\App\Terminology\DomainTermKeys::LEAD).'…') }}" 
               target="_blank"
               class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 font-medium rounded-lg shadow-sm border border-green-200 dark:border-green-800 active:bg-green-100 dark:active:bg-green-900/50 transition">
                <x-heroicon-o-chat-bubble-left-ellipsis class="w-5 h-5" />
                <span>WA</span>
            </a>
        </div>
    </div>
@endif
