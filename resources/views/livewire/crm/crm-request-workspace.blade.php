@php
    use App\Models\CrmRequest;
    use App\Models\CrmRequestActivity;
    use App\Product\CRM\CrmWorkspacePresentation;

    $emailRaw = $crm->email;
    $phoneRaw = $crm->phone;
    $emailValid = is_string($emailRaw) && $emailRaw !== '' && filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
    $phoneForTel = is_string($phoneRaw) && $phoneRaw !== '' ? preg_replace('/[^\d+]/', '', $phoneRaw) : '';
    $payload = $crm->payload_json;
    $hasPayloadJson = is_array($payload) && count($payload) > 0;
    $hasTechnicalBlock = filled($crm->ip) || filled($crm->user_agent) || $hasPayloadJson;
    $notesCount = $crm->notes->count();
    $followUpOverdue = $crm->isFollowUpOverdue();
    $followUpUnset = $crm->next_follow_up_at === null;
@endphp

<div class="space-y-5 text-sm text-zinc-900 dark:text-zinc-100">
    {{-- Header --}}
    <div class="space-y-3 border-b border-zinc-200/80 pb-4 dark:border-white/10">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold leading-tight tracking-tight text-zinc-950 dark:text-white">
                    {{ $crm->name !== '' ? $crm->name : 'Без имени' }}
                </h2>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Создана {{ $crm->created_at?->format('d.m.Y H:i') ?? '—' }}
                    @if($crm->first_viewed_at)
                        · Первый просмотр {{ $crm->first_viewed_at->format('d.m.Y H:i') }}
                    @endif
                </p>
            </div>
            <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
                <span @class([
                    'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium',
                    CrmWorkspacePresentation::statusBadgeClasses($crm->status),
                ])>
                    {{ CrmRequest::statusLabels()[$crm->status] ?? $crm->status }}
                </span>
                <span @class([
                    'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium',
                    CrmWorkspacePresentation::priorityBadgeClasses($crm->priority),
                ])>
                    {{ CrmRequest::priorityLabels()[$crm->priority ?? CrmRequest::PRIORITY_NORMAL] ?? ($crm->priority ?? '—') }}
                </span>
            </div>
        </div>

        {{-- Operator summary strip --}}
        <div class="flex flex-wrap gap-x-4 gap-y-2 rounded-xl bg-zinc-950/[0.03] px-3 py-2.5 text-xs dark:bg-white/[0.04]">
            <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                <x-heroicon-o-flag class="h-3.5 w-3.5 shrink-0 text-zinc-400 dark:text-zinc-500" />
                <span class="text-zinc-500 dark:text-zinc-500">Статус</span>
                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ CrmRequest::statusLabels()[$crm->status] ?? $crm->status }}</span>
            </div>
            <span class="hidden text-zinc-300 sm:inline dark:text-zinc-600" aria-hidden="true">|</span>
            <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                <x-heroicon-o-signal class="h-3.5 w-3.5 shrink-0 text-zinc-400 dark:text-zinc-500" />
                <span class="text-zinc-500 dark:text-zinc-500">Приоритет</span>
                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ CrmRequest::priorityLabels()[$crm->priority ?? CrmRequest::PRIORITY_NORMAL] ?? '—' }}</span>
            </div>
            <span class="hidden text-zinc-300 sm:inline dark:text-zinc-600" aria-hidden="true">|</span>
            <div @class([
                'flex flex-wrap items-center gap-x-1.5 gap-y-1 text-zinc-600 dark:text-zinc-400',
                'rounded-md px-1.5 py-0.5 -mx-1.5 -my-0.5 bg-amber-500/10 ring-1 ring-amber-500/25 dark:bg-amber-500/10' => $followUpOverdue,
            ])>
                <x-heroicon-o-clock @class([
                    'h-3.5 w-3.5 shrink-0',
                    'text-amber-600 dark:text-amber-400' => $followUpOverdue,
                    'text-zinc-400 dark:text-zinc-500' => ! $followUpOverdue,
                ]) />
                <span class="text-zinc-500 dark:text-zinc-500">Follow-up</span>
                <span @class([
                    'font-medium',
                    'text-amber-900 dark:text-amber-100' => $followUpOverdue,
                    'text-zinc-800 dark:text-zinc-200' => ! $followUpOverdue,
                ])>{{ $crm->next_follow_up_at?->format('d.m.Y H:i') ?? '—' }}</span>
                @if($followUpOverdue)
                    <span class="rounded bg-amber-500/20 px-1.5 py-px text-[10px] font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-200">Просрочено</span>
                @elseif($followUpUnset)
                    <span class="text-[10px] font-medium text-zinc-400 dark:text-zinc-500">не задано</span>
                @endif
            </div>
            <span class="hidden text-zinc-300 sm:inline dark:text-zinc-600" aria-hidden="true">|</span>
            <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                <x-heroicon-o-bolt class="h-3.5 w-3.5 shrink-0 text-zinc-400 dark:text-zinc-500" />
                <span class="text-zinc-500 dark:text-zinc-500">Активность</span>
                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $crm->last_activity_at?->format('d.m.Y H:i') ?? '—' }}</span>
            </div>
            <span class="hidden text-zinc-300 sm:inline dark:text-zinc-600" aria-hidden="true">|</span>
            <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                <x-heroicon-o-chat-bubble-left-ellipsis class="h-3.5 w-3.5 shrink-0 text-zinc-400 dark:text-zinc-500" />
                <span class="text-zinc-500 dark:text-zinc-500">Заметки</span>
                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $notesCount }}</span>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Left: work area --}}
        <div class="space-y-5">
            <section class="rounded-2xl bg-zinc-950/[0.02] p-4 shadow-sm ring-1 ring-zinc-950/5 dark:bg-white/[0.03] dark:ring-white/10">
                <h3 class="mb-4 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Операторские поля</h3>
                <div class="space-y-4">
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300" for="ws-status">Статус CRM</label>
                            <div class="flex min-h-[1.25rem] min-w-[5.5rem] items-center justify-end gap-2">
                                <span wire:loading.delay.shortest class="text-[10px] text-zinc-400 dark:text-zinc-500" wire:target="statusLocal">Сохранение…</span>
                                <span
                                    wire:loading.remove.delay.shortest
                                    wire:target="statusLocal"
                                    class="text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                                    @if($autosaveInlineHint !== 'status') hidden @endif
                                >Сохранено</span>
                            </div>
                        </div>
                        <select
                            id="ws-status"
                            @class([
                                'fi-select-input block w-full rounded-lg border bg-white px-3 py-2 text-sm text-zinc-950 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white',
                                'border-red-500/60 dark:border-red-500/50' => $errors->has('statusLocal'),
                                'border-zinc-200 dark:border-white/10' => ! $errors->has('statusLocal'),
                            ])
                            wire:model.live.debounce.400ms="statusLocal"
                        >
                            @foreach(CrmRequest::statusLabels() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[10px] text-zinc-500 dark:text-zinc-500">Сохраняется при смене значения</p>
                        @error('statusLocal')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300" for="ws-priority">Приоритет</label>
                            <div class="flex min-h-[1.25rem] min-w-[5.5rem] items-center justify-end gap-2">
                                <span wire:loading.delay.shortest class="text-[10px] text-zinc-400 dark:text-zinc-500" wire:target="priorityLocal">Сохранение…</span>
                                <span
                                    wire:loading.remove.delay.shortest
                                    wire:target="priorityLocal"
                                    class="text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                                    @if($autosaveInlineHint !== 'priority') hidden @endif
                                >Сохранено</span>
                            </div>
                        </div>
                        <select
                            id="ws-priority"
                            @class([
                                'fi-select-input block w-full rounded-lg border bg-white px-3 py-2 text-sm text-zinc-950 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white',
                                'border-red-500/60 dark:border-red-500/50' => $errors->has('priorityLocal'),
                                'border-zinc-200 dark:border-white/10' => ! $errors->has('priorityLocal'),
                            ])
                            wire:model.live.debounce.400ms="priorityLocal"
                        >
                            @foreach(CrmRequest::priorityLabels() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[10px] text-zinc-500 dark:text-zinc-500">Сохраняется при смене значения</p>
                        @error('priorityLocal')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300" for="ws-followup">Следующий контакт (follow-up)</label>
                            @if($followUpOverdue)
                                <span class="rounded-md bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 ring-1 ring-amber-500/25 dark:text-amber-200">Просрочено</span>
                            @endif
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                            <input
                                id="ws-followup"
                                type="datetime-local"
                                wire:model="followUpLocal"
                                @class([
                                    'fi-input block w-full min-w-0 rounded-lg border bg-white px-3 py-2 text-sm text-zinc-950 dark:bg-white/5 dark:text-white',
                                    'border-red-500/60 dark:border-red-500/50' => $errors->has('followUpLocal'),
                                    'border-amber-500/55 ring-1 ring-amber-500/35 dark:border-amber-500/40' => $followUpOverdue && ! $errors->has('followUpLocal'),
                                    'border-zinc-200 dark:border-white/10' => ! $followUpOverdue && ! $errors->has('followUpLocal'),
                                ])
                            />
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <button
                                    type="button"
                                    wire:click="saveFollowUp"
                                    wire:loading.attr.disabled
                                    wire:target="saveFollowUp"
                                    class="fi-btn fi-btn-size-sm fi-color-custom fi-btn-color-primary rounded-lg px-3 py-2 text-xs font-medium disabled:opacity-50"
                                >
                                    <span wire:loading.remove wire:target="saveFollowUp">Сохранить</span>
                                    <span wire:loading wire:target="saveFollowUp">Сохранение…</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="clearFollowUp"
                                    wire:loading.attr.disabled
                                    wire:target="clearFollowUp"
                                    class="rounded-lg px-3 py-2 text-xs font-medium text-zinc-600 ring-1 ring-zinc-200 transition hover:bg-zinc-50 disabled:opacity-50 dark:text-zinc-300 dark:ring-white/15 dark:hover:bg-white/5"
                                >
                                    <span wire:loading.remove wire:target="clearFollowUp">Сбросить</span>
                                    <span wire:loading wire:target="clearFollowUp">…</span>
                                </button>
                            </div>
                        </div>
                        @error('followUpLocal')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-700 dark:text-zinc-300" for="ws-summary">Внутреннее резюме</label>
                        <textarea
                            id="ws-summary"
                            wire:model="internalSummary"
                            rows="4"
                            @class([
                                'fi-input block w-full rounded-lg border bg-white px-3 py-2 text-sm text-zinc-950 dark:bg-white/5 dark:text-white',
                                'border-red-500/60 dark:border-red-500/50' => $errors->has('internalSummary'),
                                'border-zinc-200 dark:border-white/10' => ! $errors->has('internalSummary'),
                            ])
                        ></textarea>
                        @error('internalSummary')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                wire:click="saveSummary"
                                wire:loading.attr.disabled
                                wire:target="saveSummary"
                                class="fi-btn fi-btn-size-sm fi-color-custom fi-btn-color-primary rounded-lg px-3 py-2 text-xs font-medium disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="saveSummary">Сохранить резюме</span>
                                <span wire:loading wire:target="saveSummary">Сохранение…</span>
                            </button>
                            <span wire:loading.remove wire:target="saveSummary" class="text-[10px] text-zinc-500 dark:text-zinc-500">Явное сохранение</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl bg-zinc-950/[0.02] p-4 shadow-sm ring-1 ring-zinc-950/5 dark:bg-white/[0.03] dark:ring-white/10">
                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Сообщение клиента</h3>
                <div class="whitespace-pre-wrap text-sm leading-relaxed text-zinc-800 dark:text-zinc-200">
                    {{ $crm->message !== null && $crm->message !== '' ? $crm->message : '—' }}
                </div>
            </section>

            <section class="rounded-2xl bg-zinc-950/[0.02] p-4 shadow-sm ring-1 ring-zinc-950/5 dark:bg-white/[0.03] dark:ring-white/10">
                <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Рабочие заметки</h3>

                @if($crm->notes->isEmpty())
                    <div class="mb-4 rounded-xl border border-dashed border-zinc-200/90 px-4 py-6 text-center dark:border-white/10">
                        <x-heroicon-o-chat-bubble-left-right class="mx-auto h-8 w-8 text-zinc-300 dark:text-zinc-600" />
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Пока нет внутренних комментариев.</p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">Добавьте заметку ниже — она увидит только команда.</p>
                    </div>
                @else
                    <ul class="mb-4 max-h-72 space-y-2 overflow-y-auto pr-1">
                        @foreach($crm->notes as $note)
                            <li @class([
                                'rounded-xl px-3 py-2.5 transition',
                                'bg-amber-500/[0.07] ring-1 ring-amber-500/20 dark:bg-amber-500/10' => $note->is_pinned,
                                'bg-zinc-950/[0.03] dark:bg-white/[0.04]' => ! $note->is_pinned,
                            ])>
                                <div class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                                    @if($note->is_pinned)
                                        <span class="inline-flex items-center gap-0.5 rounded bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">
                                            <x-heroicon-m-bookmark class="h-3 w-3" />
                                            Важно
                                        </span>
                                    @endif
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $note->user?->name ?? 'Система' }}</span>
                                    <span class="text-zinc-400 dark:text-zinc-500">{{ $note->created_at?->format('d.m.Y H:i') }}</span>
                                </div>
                                <div class="whitespace-pre-wrap text-sm leading-relaxed text-zinc-900 dark:text-zinc-100">{{ $note->body }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="border-t border-zinc-200/80 pt-4 dark:border-white/10" x-data="{ submit() { $wire.addNote() } }" @keydown.ctrl.enter.prevent="submit()" @keydown.meta.enter.prevent="submit()">
                    <label class="mb-1 block text-xs font-medium text-zinc-700 dark:text-zinc-300" for="ws-note">Новый комментарий</label>
                    <textarea
                        id="ws-note"
                        wire:model="noteDraft"
                        rows="3"
                        @class([
                            'fi-input mb-2 block w-full rounded-lg border bg-white px-3 py-2 text-sm text-zinc-950 dark:bg-white/5 dark:text-white',
                            'border-red-500/60 dark:border-red-500/50' => $errors->has('noteDraft'),
                            'border-zinc-200 dark:border-white/10' => ! $errors->has('noteDraft'),
                        ])
                        placeholder="Внутренний комментарий для команды…"
                    ></textarea>
                    @error('noteDraft')
                        <p class="mb-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <label class="mb-2 flex cursor-pointer items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                        <input type="checkbox" wire:model="notePinImportant" class="rounded border-zinc-300 text-primary-600 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5" />
                        <span>Пометить как важное (закрепить сверху)</span>
                    </label>
                    <button
                        type="button"
                        wire:click="addNote"
                        wire:loading.attr.disabled
                        wire:target="addNote"
                        class="fi-btn fi-btn-size-sm fi-color-custom fi-btn-color-primary rounded-lg px-3 py-2 text-xs font-medium disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="addNote">Добавить комментарий</span>
                        <span wire:loading wire:target="addNote">Отправка…</span>
                    </button>
                    <p class="mt-1.5 text-[10px] text-zinc-500 dark:text-zinc-500">Ctrl+Enter — отправить</p>
                </div>
            </section>
        </div>

        {{-- Right: metadata + timeline --}}
        <div class="space-y-5">
            <section class="rounded-2xl bg-zinc-950/[0.02] p-4 shadow-sm ring-1 ring-zinc-950/5 dark:bg-white/[0.03] dark:ring-white/10">
                <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Контакты</h3>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-500">Email</dt>
                        <dd class="mt-0.5 flex flex-wrap items-center gap-2">
                            @if($emailValid)
                                <a href="mailto:{{ $emailRaw }}" class="text-primary-600 underline decoration-primary-600/30 underline-offset-2 hover:decoration-primary-600 dark:text-primary-400 dark:decoration-primary-400/30">{{ $emailRaw }}</a>
                                <button
                                    type="button"
                                    class="rounded px-1.5 py-0.5 text-[10px] font-medium text-zinc-500 ring-1 ring-zinc-200/80 hover:bg-zinc-100 dark:text-zinc-400 dark:ring-white/15 dark:hover:bg-white/5"
                                    x-data="{ copied: false }"
                                    x-on:click="navigator.clipboard.writeText(@js($emailRaw)).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
                                >
                                    <span x-show="!copied">Копировать</span>
                                    <span x-show="copied" x-cloak>Скопировано</span>
                                </button>
                            @elseif(filled($emailRaw))
                                <span class="break-all text-zinc-800 dark:text-zinc-200">{{ $emailRaw }}</span>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-500">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-500">Телефон</dt>
                        <dd class="mt-0.5 flex flex-wrap items-center gap-2">
                            @if(filled($phoneRaw))
                                @if($phoneForTel !== '')
                                    <a href="tel:{{ $phoneForTel }}" class="text-primary-600 underline decoration-primary-600/30 underline-offset-2 hover:decoration-primary-600 dark:text-primary-400 dark:decoration-primary-400/30">{{ $phoneRaw }}</a>
                                    <button
                                        type="button"
                                        class="rounded px-1.5 py-0.5 text-[10px] font-medium text-zinc-500 ring-1 ring-zinc-200/80 hover:bg-zinc-100 dark:text-zinc-400 dark:ring-white/15 dark:hover:bg-white/5"
                                        x-data="{ copied: false }"
                                        x-on:click="navigator.clipboard.writeText(@js($phoneRaw)).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
                                    >
                                        <span x-show="!copied">Копировать</span>
                                        <span x-show="copied" x-cloak>Скопировано</span>
                                    </button>
                                @else
                                    <span>{{ $phoneRaw }}</span>
                                @endif
                            @else
                                <span class="text-zinc-500 dark:text-zinc-500">—</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl bg-zinc-950/[0.02] p-4 shadow-sm ring-1 ring-zinc-950/5 dark:bg-white/[0.03] dark:ring-white/10">
                <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Ключевая атрибуция</h3>
                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-zinc-500 dark:text-zinc-500">Ответственный</dt>
                        <dd class="mt-0.5 font-medium text-zinc-800 dark:text-zinc-200">{{ $crm->assignedUser?->name ?? '—' }}</dd>
                    </div>
                    <div><dt class="text-xs text-zinc-500 dark:text-zinc-500">Тип заявки</dt><dd class="mt-0.5">{{ $crm->request_type }}</dd></div>
                    <div><dt class="text-xs text-zinc-500 dark:text-zinc-500">Источник</dt><dd class="mt-0.5">{{ $crm->source ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-zinc-500 dark:text-zinc-500">Канал</dt><dd class="mt-0.5">{{ $crm->channel }}</dd></div>
                    <div><dt class="text-xs text-zinc-500 dark:text-zinc-500">Воронка</dt><dd class="mt-0.5">{{ $crm->pipeline }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-zinc-500 dark:text-zinc-500">Страница входа</dt><dd class="mt-0.5 break-all text-zinc-800 dark:text-zinc-200">{{ $crm->landing_page ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-zinc-500 dark:text-zinc-500">Referrer</dt><dd class="mt-0.5 break-all text-zinc-800 dark:text-zinc-200">{{ $crm->referrer ?? '—' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl bg-zinc-950/[0.02] p-4 shadow-sm ring-1 ring-zinc-950/5 dark:bg-white/[0.03] dark:ring-white/10">
                <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Маркетинг UTM</h3>
                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-zinc-500 dark:text-zinc-500">utm_source</dt><dd class="mt-0.5 break-all">{{ $crm->utm_source ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-zinc-500 dark:text-zinc-500">utm_medium</dt><dd class="mt-0.5 break-all">{{ $crm->utm_medium ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-zinc-500 dark:text-zinc-500">utm_campaign</dt><dd class="mt-0.5 break-all">{{ $crm->utm_campaign ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-zinc-500 dark:text-zinc-500">utm_content</dt><dd class="mt-0.5 break-all">{{ $crm->utm_content ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-zinc-500 dark:text-zinc-500">utm_term</dt><dd class="mt-0.5 break-all">{{ $crm->utm_term ?? '—' }}</dd></div>
                </dl>
            </section>

            @if($hasTechnicalBlock)
                <details class="group rounded-2xl bg-zinc-950/[0.02] p-4 shadow-sm ring-1 ring-zinc-950/5 dark:bg-white/[0.03] dark:ring-white/10">
                    <summary class="cursor-pointer list-none text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 [&::-webkit-details-marker]:hidden">
                        <span class="inline-flex items-center gap-2">
                            Технические данные
                            <x-heroicon-o-chevron-down class="h-3.5 w-3.5 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="mt-4 space-y-4 border-t border-zinc-200/80 pt-4 dark:border-white/10">
                        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                            @if(filled($crm->ip))
                                <div><dt class="text-xs text-zinc-500 dark:text-zinc-500">IP</dt><dd class="mt-0.5 font-mono text-xs">{{ $crm->ip }}</dd></div>
                            @endif
                            @if(filled($crm->user_agent))
                                <div class="sm:col-span-2"><dt class="text-xs text-zinc-500 dark:text-zinc-500">User-Agent</dt><dd class="mt-0.5 break-all font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $crm->user_agent }}</dd></div>
                            @endif
                        </dl>
                        @if($hasPayloadJson)
                            <pre class="max-h-48 overflow-auto rounded-lg bg-zinc-950/90 p-3 text-xs leading-relaxed text-zinc-200">{{ json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                        @else
                            <p class="text-xs text-zinc-500 dark:text-zinc-500">Дополнительный payload формы отсутствует.</p>
                        @endif
                    </div>
                </details>
            @endif

            <section class="rounded-2xl bg-zinc-950/[0.02] p-4 shadow-sm ring-1 ring-zinc-950/5 dark:bg-white/[0.03] dark:ring-white/10">
                <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Лента активности</h3>
                @if($crm->activities->isEmpty())
                    <div class="rounded-xl border border-dashed border-zinc-200/90 px-4 py-8 text-center dark:border-white/10">
                        <x-heroicon-o-queue-list class="mx-auto h-8 w-8 text-zinc-300 dark:text-zinc-600" />
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Событий пока нет</p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">История появится после действий по заявке.</p>
                    </div>
                @else
                    <ul class="max-h-[22rem] space-y-2 overflow-y-auto pr-1">
                        @foreach($crm->activities as $activity)
                            @php
                                $visuals = CrmWorkspacePresentation::activityTimelineVisuals($activity);
                            @endphp
                            <li class="flex gap-3 rounded-xl bg-zinc-950/[0.02] py-2.5 pe-3 ps-2 dark:bg-white/[0.02]">
                                <div class="flex shrink-0 flex-col items-center pt-0.5">
                                    <span @class([$visuals['iconWrap'], 'flex h-8 w-8 items-center justify-center rounded-lg'])>
                                        <x-dynamic-component :component="$visuals['icon']" class="h-4 w-4 shrink-0" />
                                    </span>
                                </div>
                                <div @class(['min-w-0 flex-1 border-s ps-3', $visuals['rail']])>
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                        <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-100">{{ CrmRequestActivity::typeLabel($activity->type) }}</span>
                                        <span class="text-[11px] text-zinc-500 dark:text-zinc-500">{{ $activity->created_at?->format('d.m.Y H:i') }}</span>
                                        @if($activity->actor)
                                            <span class="text-[11px] text-zinc-500 dark:text-zinc-500">· {{ $activity->actor->name }}</span>
                                        @endif
                                    </div>
                                    @if($activity->summaryLine() !== '')
                                        <p class="mt-1 text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $activity->summaryLine() }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</div>
