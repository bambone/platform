@extends('tenant.layouts.app')

@section('title', 'Вопросы и ответы')

@section('content')
    @php
        $__tkFaq = tenant()?->themeKey();
        $__faqShell = in_array($__tkFaq, ['expert_auto', 'advocate_editorial'], true)
            ? 'max-w-[min(72rem,calc(100vw-1.5rem))] md:px-10'
            : 'max-w-6xl';
    @endphp
    <section class="tenant-page-faq pt-24 pb-8 sm:pt-28 sm:pb-12">
        <div class="mx-auto px-3 sm:px-4 {{ $__faqShell }}">
            <h1 class="text-balance text-2xl font-bold leading-tight tracking-tight text-white sm:text-3xl md:text-4xl">{{ ($resolvedSeo ?? null)?->h1 ?? 'Часто задаваемые вопросы' }}</h1>
            <p class="tenant-page-faq__lead mt-4 max-w-2xl text-sm leading-relaxed text-silver sm:text-base">
                Краткие ответы на типовые вопросы до консультации. Формулировки обобщённые: итог по делу всегда зависит от обстоятельств и доказательств.
                Уточнить ситуацию можно на <a href="{{ route('contacts') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">странице контактов</a>.
            </p>
        </div>
    </section>

    <section class="tenant-page-faq pb-20 sm:pb-24">
        <div class="mx-auto max-w-3xl px-3 sm:px-4 md:px-8">
            @if(($faqs ?? collect())->isEmpty())
                <p class="text-sm text-silver sm:text-base">Пока нет опубликованных вопросов. Напишите в <a href="{{ route('contacts') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">контакты</a> — ответим по существу.</p>
            @else
                <div class="space-y-3 sm:space-y-4">
                    @foreach ($faqs as $faq)
                        <details class="tenant-page-faq__item group rounded-2xl border border-white/10 bg-carbon/90 px-4 py-3 shadow-sm sm:px-5 sm:py-4 open:border-moto-amber/25 open:bg-carbon">
                            <summary class="cursor-pointer list-none text-base font-semibold leading-snug text-white marker:content-none sm:text-lg [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-3">
                                    <span class="min-w-0">{{ $faq->question }}</span>
                                    <span class="mt-0.5 shrink-0 text-moto-amber transition-transform group-open:rotate-180" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </span>
                                </span>
                            </summary>
                            <div class="tenant-page-faq__answer mt-3 border-t border-white/10 pt-3 text-sm leading-relaxed text-silver sm:text-base">
                                {!! nl2br(e($faq->answer)) !!}
                            </div>
                        </details>
                    @endforeach
                </div>
                <p class="tenant-page-faq__foot mt-10 max-w-2xl text-sm leading-relaxed text-silver sm:text-base">
                    Не нашли ответ — <a href="{{ route('contacts') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">свяжитесь</a>, кратко опишите ситуацию.
                </p>
            @endif
        </div>
    </section>
@endsection
