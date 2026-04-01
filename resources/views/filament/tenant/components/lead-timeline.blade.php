<div class="space-y-4">
    <div class="fi-drawer-timeline border-l-2 border-gray-200 dark:border-gray-700 ml-3 pl-4 space-y-4">
        @php
            $logs = $getRecord() ? $getRecord()->activityLogs()->with('actor')->get() : [];
        @endphp

        @forelse($logs as $log)
            <div class="relative">
                <div class="absolute -left-[1.35rem] mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                    @if($log->type === 'status_change')
                        <x-crm.svg-icon name="heroicon-o-arrow-path" size="md" class="text-primary-500" />
                    @elseif($log->type === 'call_made')
                        <x-crm.svg-icon name="heroicon-o-phone" size="md" class="text-gray-500 dark:text-gray-400" />
                    @elseif($log->type === 'whatsapp_sent')
                        <x-crm.svg-icon name="heroicon-o-chat-bubble-left" size="md" class="text-green-600 dark:text-green-400" />
                    @elseif($log->type === 'reverted')
                        <x-crm.svg-icon name="heroicon-o-arrow-uturn-left" size="md" class="text-danger-500" />
                    @else
                        <x-crm.svg-icon name="heroicon-o-chat-bubble-bottom-center-text" size="md" class="text-gray-500 dark:text-gray-400" />
                    @endif
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        @if($log->type === 'status_change')
                            Статус изменен на «{{ \App\Models\Lead::statuses()[$log->payload['new_status'] ?? ''] ?? 'Неизвестно' }}»
                        @elseif($log->type === 'reverted')
                            Действие отменено (возврат к «{{ \App\Models\Lead::statuses()[$log->payload['new_status'] ?? ''] ?? '' }}»)
                        @else
                            {{ $log->comment ?: 'Действие' }}
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ $log->created_at->format('d.m.Y H:i') }} • 
                        @if($log->actor)
                            {{ $log->actor->name }}
                        @else
                            Система
                        @endif
                    </div>
                    @if($log->type === 'status_change' && $log->comment)
                        <div class="text-xs mt-1 text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700/50 p-2 rounded">
                            {{ $log->comment }}
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-sm text-gray-500 dark:text-gray-400 italic">Истории пока нет</div>
        @endforelse
    </div>
</div>
