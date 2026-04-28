{{-- Выдержка в карточке + необязательный диалог полного текста (не участвует в высоте сетки). --}}
@props([
    'review',
    /** Уникальный суффикс (напр. id секции page_sections) для id диалогов */
    'scopeId' => 0,
    'quoteClass' => '',
    'readMoreClass' => 'text-[13px] font-semibold text-moto-amber underline-offset-4 hover:text-moto-amber/90 hover:underline',
    'openMark' => '"',
    'closeMark' => '"',
])
@php
    /** @var \App\Models\Review $review */
    $txtDlgId = 'expert-review-txt-'.$review->id.'-'.$scopeId;
@endphp
<div class="review-quote-expand">
    <p class="{{ $quoteClass }}">{{ $openMark }}{{ $review->publicCardExcerpt() }}{{ $closeMark }}</p>
    @if ($review->publicWantsReadMore())
        <p class="mt-3 shrink-0">
            <button
                type="button"
                class="{{ $readMoreClass }}"
                data-expert-review-text-open="{{ e($txtDlgId) }}"
                aria-expanded="false"
            >Читать полностью</button>
        </p>
        @php
            $dialogPlain = trim((string) strip_tags((string) $review->publicFullTextRaw()));
        @endphp
        <dialog id="{{ e($txtDlgId) }}" class="expert-video-dialog expert-video-dialog--text" aria-labelledby="{{ e('heading-'.$txtDlgId) }}">
            <div class="expert-video-dialog__panel max-h-[min(92vh,36rem)]">
                <div class="expert-video-dialog__head shrink-0">
                    <p id="{{ e('heading-'.$txtDlgId) }}" class="truncate text-sm font-semibold text-white">Отзыв: {{ $review->name }}</p>
                    <form method="dialog">
                        <button type="submit" class="expert-video-dialog__close rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10">
                            Закрыть
                        </button>
                    </form>
                </div>
                <div class="expert-video-dialog__body max-h-[min(70vh,28rem)] overflow-y-auto text-sm leading-relaxed text-white/95 md:text-[15px] md:leading-relaxed">
                    @if ($dialogPlain !== '')
                        <div class="whitespace-pre-wrap break-words">{{ e($dialogPlain) }}</div>
                    @endif
                </div>
            </div>
        </dialog>
    @endif
</div>
