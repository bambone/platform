@php
    $record = $getRecord();
@endphp

@if($record)
    <div class="fi-drawer-sticky-bottom flex sm:hidden items-center justify-between">
        <div class="flex w-full items-center gap-2">
            <a href="tel:{{ preg_replace('/[^0-9]/', '', $record->phone) }}"
               class="flex flex-1 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-gray-100 px-4 py-3 text-sm font-medium text-gray-900 shadow-sm transition active:bg-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:active:bg-gray-700">
                <x-crm.svg-icon name="heroicon-o-phone" size="md" class="text-gray-600 dark:text-gray-400" />
                <span>Позвонить</span>
            </a>
            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $record->phone) }}?text={{ urlencode('Здравствуйте! Пишу по поводу '.tenant_term(\App\Terminology\DomainTermKeys::LEAD).'…') }}"
               target="_blank" rel="noopener noreferrer"
               class="flex flex-1 items-center justify-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 shadow-sm transition active:bg-green-100 dark:border-green-800 dark:bg-green-900/30 dark:text-green-400 dark:active:bg-green-900/50">
                <x-crm.svg-icon name="heroicon-o-chat-bubble-left-ellipsis" size="md" class="text-green-700 dark:text-green-400" />
                <span>WhatsApp</span>
            </a>
        </div>
    </div>
@endif
