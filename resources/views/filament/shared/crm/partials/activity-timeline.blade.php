@php
    use App\Product\CRM\CrmWorkspacePresentation;

    $activities = $getRecord()->activities()
        ->with('actor')
        ->orderByDesc('id')
        ->get();
@endphp

<div class="crm-ws-activity flex flex-col gap-1">
    @if($activities->isEmpty())
        <div class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Событий пока нет</div>
    @else
        <div class="crm-ws-activity-rail relative space-y-2.5">
            @foreach($activities as $activity)
                @php
                    $row = CrmWorkspacePresentation::activityTimelineListRow($activity);
                    $metaKind = CrmWorkspacePresentation::activityTimelineMetaKind($activity);
                @endphp

                <div class="crm-ws-activity-row relative pb-1 pl-5">
                    <div class="crm-ws-activity-dot absolute -left-[13px] top-0.5 flex h-6 w-6 items-center justify-center rounded-full {{ $row['dot_classes'] }}">
                        {!! svg($row['icon'], 'pointer-events-none shrink-0', ['width' => '13', 'height' => '13', 'aria-hidden' => 'true'])->toHtml() !!}
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <div class="text-sm font-medium leading-snug {{ $row['is_important'] ? 'text-gray-900 dark:text-gray-100' : 'text-gray-800 dark:text-gray-200' }}">
                            {{ \App\Models\CrmRequestActivity::typeLabel($activity->type) }}
                        </div>
                        <div class="text-[11px] leading-tight text-gray-500 dark:text-gray-500">
                            <span class="tabular-nums">{{ $activity->created_at->format('d.m.y H:i') }}</span>
                            @if($activity->actor)
                                <span> · {{ $activity->actor->name }}</span>
                            @endif
                        </div>

                        @if($activity->meta)
                            <div class="mt-1 text-[13px] leading-relaxed text-gray-600 dark:text-gray-400">
                                @switch($metaKind)
                                    @case('status_changed')
                                        Изменен с
                                        <span class="font-medium">{{ \App\Models\CrmRequest::statusLabels()[$activity->meta['old']] ?? $activity->meta['old'] }}</span>
                                        на
                                        <span class="font-medium">{{ \App\Models\CrmRequest::statusLabels()[$activity->meta['new']] ?? $activity->meta['new'] }}</span>
                                        @break
                                    @case('priority_changed')
                                        Изменен с
                                        <span class="font-medium">{{ \App\Models\CrmRequest::priorityLabels()[$activity->meta['old']] ?? $activity->meta['old'] }}</span>
                                        на
                                        <span class="font-medium">{{ \App\Models\CrmRequest::priorityLabels()[$activity->meta['new']] ?? $activity->meta['new'] }}</span>
                                        @break
                                    @case('preview')
                                        <span class="italic text-gray-500 dark:text-gray-400 break-words line-clamp-2">"{{ $activity->meta['preview'] ?? '...' }}"</span>
                                        @break
                                    @case('follow_up')
                                        @php
                                            $followUpAt = $activity->meta['at'] ?? null;
                                        @endphp
                                        @if(is_string($followUpAt) && $followUpAt !== '')
                                            Назначено на <span class="font-medium">{{ \Carbon\Carbon::parse($followUpAt)->format('d.m.Y H:i') }}</span>
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400">{{ $activity->summaryLine() }}</span>
                                        @endif
                                        @break
                                    @case('summary_line')
                                        <span class="text-gray-600 dark:text-gray-400">{{ $activity->summaryLine() }}</span>
                                        @break
                                    @default
                                        <pre class="bg-gray-50 dark:bg-white/5 p-2 rounded-lg text-[10px] overflow-x-auto text-gray-600 dark:text-gray-400">{{ json_encode($activity->meta, JSON_UNESCAPED_UNICODE) }}</pre>
                                @endswitch
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
