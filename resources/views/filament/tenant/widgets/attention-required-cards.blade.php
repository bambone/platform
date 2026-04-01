@php
    use App\Filament\Tenant\Resources\CrmRequestResource;
    use App\Filament\Tenant\Resources\LeadResource;
    use App\Support\FilamentMotorcycleThumbnail;
@endphp

{{-- Оболочка виджета без светлой заливки в dark: только border/padding на внутреннем блоке --}}
<x-filament-widgets::widget
    class="fi-attention-required-widget !bg-transparent dark:!bg-transparent"
>
    <div
        class="fi-attention-required-shell rounded-xl border border-zinc-200/90 bg-zinc-50 p-4 shadow-sm sm:p-5 dark:border-white/[0.08] dark:bg-[#161822] dark:shadow-[0_1px_2px_rgba(0,0,0,0.28)]"
    >
        <h3 class="text-base font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">
            Требует внимания
            @if ($queueTotal > 0)
                <span class="font-semibold text-amber-600/90 dark:text-amber-400/85">({{ $queueTotal }})</span>
            @endif
        </h3>

        @if ($queueTotal === 0)
            <div
                class="mt-5 flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300/90 bg-zinc-100/60 px-4 py-9 text-center dark:border-white/10 dark:bg-[#12141c]"
                role="status"
            >
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">
                    Новых заявок пока нет. Отличная работа.
                </p>
            </div>
        @else
            <ul class="mt-4 flex flex-col gap-2.5" role="list">
                @foreach ($leads as $lead)
                    @php
                        $isStale = $lead->created_at->diffInHours(now()) > 24;
                        $statusLabel = $isStale ? 'Просрочено (>24ч)' : 'Ждёт ответа';
                        $badgeClasses = $isStale
                            ? 'border border-red-500/30 bg-red-500/[0.1] text-red-800 dark:border-red-500/30 dark:bg-red-500/[0.12] dark:text-red-200/90'
                            : 'border border-amber-400/30 bg-amber-500/[0.12] text-amber-900 dark:border-amber-400/25 dark:bg-amber-500/[0.1] dark:text-amber-200/90';
                        $modelName = $lead->motorcycle?->name;
                        $modelDisplay = filled($modelName) ? $modelName : 'Без модели в каталоге';
                        $thumbSrc = FilamentMotorcycleThumbnail::coverUrlOrPlaceholder($lead->motorcycle);
                        $telDigits = preg_replace('/[^0-9+]/', '', (string) $lead->phone);
                        $openUrl = $lead->crm_request_id !== null
                            ? CrmRequestResource::getUrl('view', ['record' => $lead->crm_request_id])
                            : LeadResource::getUrl('index');
                    @endphp
                    <li
                        class="fi-attention-lead-card grid grid-cols-1 items-center gap-3 rounded-xl border border-zinc-200/90 bg-white px-3.5 py-3 shadow-sm transition-colors sm:grid-cols-[4rem_minmax(0,1fr)_auto] sm:gap-x-4 sm:px-4 sm:py-3.5 dark:border-white/[0.1] dark:bg-[#12141c] dark:shadow-[0_1px_2px_rgba(0,0,0,0.35)] dark:hover:border-white/[0.14] dark:hover:bg-[#161922]"
                    >
                        <div class="flex min-w-0 items-start gap-3 sm:contents sm:items-center">
                            <div class="shrink-0 sm:col-start-1 sm:row-start-1 sm:self-center">
                                <img
                                    src="{{ $thumbSrc }}"
                                    alt=""
                                    width="64"
                                    height="64"
                                    class="h-16 w-16 rounded-lg object-cover ring-1 ring-zinc-950/10 dark:ring-white/10"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                            <div class="min-w-0 sm:col-start-2 sm:row-start-1 sm:self-center sm:py-0.5">
                                <p class="flex min-w-0 items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-50">
                                    <span class="shrink-0 text-amber-600/80 dark:text-amber-500/70" aria-hidden="true">
                                        {!! svg('heroicon-o-truck', 'h-5 w-5', ['width' => '20', 'height' => '20'])->toHtml() !!}
                                    </span>
                                    <span class="truncate leading-snug">{{ $modelDisplay }}</span>
                                </p>
                                <p class="mt-0.5 truncate text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $lead->name }}
                                </p>
                                <p class="mt-1 text-xs leading-relaxed text-zinc-500 dark:text-zinc-500">
                                    <a
                                        href="tel:{{ $telDigits }}"
                                        class="font-medium text-amber-700 hover:text-amber-600 dark:text-amber-400/85 dark:hover:text-amber-300"
                                    >
                                        {{ $lead->phone }}
                                    </a>
                                    <span class="text-zinc-400 dark:text-zinc-600" aria-hidden="true"> · </span>
                                    <span>{{ $lead->created_at->diffForHumans() }}</span>
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex w-full flex-col gap-2 border-t border-zinc-200/80 pt-3 sm:col-start-3 sm:row-start-1 sm:w-auto sm:min-w-[9.25rem] sm:max-w-[11rem] sm:shrink-0 sm:border-t-0 sm:border-l sm:border-l-zinc-200/90 sm:pl-4 sm:pt-0 sm:items-end sm:justify-center sm:self-center sm:gap-2.5 dark:border-t-white/10 dark:sm:border-l-white/10"
                        >
                            <span
                                class="inline-flex w-fit items-center rounded-md px-2 py-0.5 text-xs font-semibold leading-tight {{ $badgeClasses }}"
                            >
                                {{ $statusLabel }}
                            </span>
                            <div class="flex w-full flex-col gap-1.5 sm:w-full sm:items-stretch">
                                <a
                                    href="tel:{{ $telDigits }}"
                                    class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-lg bg-amber-600/90 px-3 text-sm font-semibold text-zinc-950 shadow-sm transition hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500/50 dark:bg-amber-600/80 dark:hover:bg-amber-500/90 sm:w-auto sm:min-w-[7.5rem]"
                                >
                                    {!! svg('heroicon-o-phone', 'h-4 w-4 shrink-0', ['width' => '16', 'height' => '16', 'aria-hidden' => 'true'])->toHtml() !!}
                                    Позвонить
                                </a>
                                <a
                                    href="{{ $openUrl }}"
                                    wire:navigate
                                    class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-lg border border-zinc-300/90 bg-zinc-100/90 px-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-200/90 dark:border-white/[0.12] dark:bg-transparent dark:text-zinc-300 dark:hover:border-white/20 dark:hover:bg-white/[0.06] dark:hover:text-zinc-50 sm:w-auto sm:min-w-[7.5rem]"
                                >
                                    {!! svg('heroicon-o-arrow-top-right-on-square', 'h-4 w-4 shrink-0', ['width' => '16', 'height' => '16', 'aria-hidden' => 'true'])->toHtml() !!}
                                    Открыть
                                </a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament-widgets::widget>
