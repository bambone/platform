@extends('tenant.layouts.app')

@section('title', 'Отзывы')

@section('content')
    <section class="pt-24 pb-8 sm:pt-28 sm:pb-10">
        <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-8">
            <h1 class="text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">{{ ($resolvedSeo ?? null)?->h1 ?? 'Отзывы клиентов' }}</h1>
        </div>
    </section>

    <section class="pb-12 sm:pb-16">
        <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-8">
            @isset($reviews)
                @if ($reviews->isEmpty())
                    <p class="text-sm leading-relaxed text-silver sm:text-base">Пока нет опубликованных отзывов.</p>
                @else
                    <ul class="grid gap-6 sm:grid-cols-2 lg:gap-8" role="list">
                        @foreach ($reviews as $review)
                            <li class="flex h-full min-h-0 flex-col rounded-xl border border-white/10 bg-white/5 p-5 text-silver shadow-sm backdrop-blur-sm sm:p-6">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="font-semibold text-white">{{ $review->name }}</p>
                                        @if ($review->city)
                                            <p class="mt-0.5 text-xs text-silver/80">{{ $review->city }}</p>
                                        @endif
                                    </div>
                                    @if ($review->rating)
                                        <p class="text-sm text-amber-300" aria-label="{{ __('Оценка :n из 5', ['n' => $review->rating]) }}">
                                            {{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', max(0, 5 - (int) $review->rating)) }}
                                        </p>
                                    @endif
                                </div>
                                @if ($review->motorcycle)
                                    <p class="mt-2 text-xs text-silver/70">{{ $review->motorcycle->name }}</p>
                                @endif

                                @include('tenant.components.review-quote-and-expand', [
                                    'review' => $review,
                                    'scopeId' => 0,
                                    'quoteClass' => 'mt-3 whitespace-pre-line text-sm leading-relaxed sm:text-base',
                                    'openMark' => '«',
                                    'closeMark' => '»',
                                    'readMoreClass' => 'text-[13px] font-semibold text-amber-300/95 underline-offset-4 hover:text-amber-200 hover:underline',
                                ])

                                <div class="mt-auto flex flex-wrap gap-x-4 gap-y-1 pt-4 text-xs text-silver/60">
                                    @if ($review->date)
                                        <time datetime="{{ $review->date->toDateString() }}">{{ $review->date->format('d.m.Y') }}</time>
                                    @endif
                                    @if ($sourceLabel = $review->publicSourceLabel())
                                        <span>{{ $sourceLabel }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @else
                <p class="text-sm leading-relaxed text-silver sm:text-base">Пока нет опубликованных отзывов.</p>
            @endisset

            @include('tenant.components.review-submit-block', [
                'pageUrl' => request()->getRequestUri(),
                'sectionSuffix' => 'page-reviews',
                'blockId' => 'rb-review-page-reviews',
            ])

            {{-- JSON для виджетов: GET /api/tenant/reviews?limit=20 --}}
        </div>
    </section>
    @include('tenant.partials.expert-video-dialog-script')
@endsection
