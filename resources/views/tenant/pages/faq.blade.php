@extends('tenant.layouts.app')

@section('title', 'FAQ')

@section('content')
    <section class="pt-24 pb-8 sm:pt-28 sm:pb-10">
        <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-8">
            <h1 class="text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">{{ ($resolvedSeo ?? null)?->h1 ?? 'Часто задаваемые вопросы' }}</h1>
            <p class="mt-4 max-w-2xl text-sm leading-relaxed text-silver sm:text-base">
                Краткие ответы по документам, залогу, страховке, пробегу и поломкам — те же формулировки, что и в блоке на главной.
                Подробные правила — на странице <a href="{{ route('terms') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">условия аренды</a>.
            </p>
        </div>
    </section>

    <section class="pb-12 sm:pb-16">
        <div class="mx-auto max-w-3xl px-3 sm:px-4 md:px-8">
            @if(($faqs ?? collect())->isEmpty())
                <p class="text-sm text-silver sm:text-base">Вопросы скоро появятся. Напишите нам в <a href="{{ route('contacts') }}" class="text-moto-amber underline-offset-2 hover:underline">контакты</a>.</p>
            @else
                <div class="space-y-3 sm:space-y-4">
                    @foreach ($faqs as $faq)
                        <details class="group rounded-2xl border border-white/10 bg-carbon/90 px-4 py-3 sm:px-5 sm:py-4 open:border-moto-amber/25 open:bg-carbon">
                            <summary class="cursor-pointer list-none text-base font-semibold leading-snug text-white marker:content-none sm:text-lg [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-3">
                                    <span>{{ $faq->question }}</span>
                                    <span class="mt-0.5 shrink-0 text-moto-amber transition-transform group-open:rotate-180" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </span>
                                </span>
                            </summary>
                            <div class="mt-3 border-t border-white/10 pt-3 text-sm leading-relaxed text-silver sm:text-base">
                                {{ $faq->answer }}
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
