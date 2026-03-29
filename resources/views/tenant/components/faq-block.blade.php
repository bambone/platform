@props(['section' => null, 'faqs' => []])
@php
    $sectionData = is_array($section ?? null) ? $section : [];
    $heading = ($sectionData['heading'] ?? null) ?: 'Частые вопросы';
    $subheading = ($sectionData['subheading'] ?? null) ?: 'Всё, что нужно знать перед тем, как завести мотор.';
    $faqList = collect($faqs ?? []);
    $useFaqs = $faqList->isNotEmpty();
@endphp
<section class="relative z-10 border-y border-white/[0.02] bg-[#0c0c0e] py-16 sm:py-20 lg:py-28">
    <div class="mx-auto max-w-3xl px-3 sm:px-4 md:px-8">
        <div class="mb-10 text-center sm:mb-12">
            <h2 class="mb-3 text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">{{ $heading }}</h2>
            <p class="text-sm leading-relaxed text-silver/80 sm:text-base md:text-lg">{{ $subheading }}</p>
        </div>

        <div x-data="{ active: null }" class="space-y-3 sm:space-y-4">
            @if($useFaqs)
                @foreach($faqList as $index => $faq)
                    <div class="bg-carbon border border-white/5 rounded-2xl overflow-hidden transition-colors hover:border-white/10">
                        <button type="button" @click="active !== {{ $index }} ? active = {{ $index }} : active = null" class="flex min-h-12 w-full items-center justify-between gap-3 px-4 py-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-moto-amber/50 sm:min-h-14 sm:px-6 sm:py-5">
                            <span class="min-w-0 flex-1 font-bold text-base text-white sm:text-lg">{{ $faq->question }}</span>
                            <svg class="w-5 h-5 text-moto-amber transition-transform duration-300 shrink-0 ml-4" :class="{ 'rotate-180': active === {{ $index }} }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div x-show="active === {{ $index }}" x-collapse x-cloak>
                            <div class="px-6 pb-6 pt-2 text-silver text-base leading-relaxed border-t border-white/5 mt-2">
                                {!! nl2br(e($faq->answer)) !!}
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                {{-- Fallback: hardcoded FAQ --}}
                <div class="bg-carbon border border-white/5 rounded-2xl overflow-hidden transition-colors hover:border-white/10">
                    <button type="button" @click="active !== 0 ? active = 0 : active = null" class="flex min-h-12 w-full items-center justify-between gap-3 px-4 py-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-moto-amber/50 sm:min-h-14 sm:px-6 sm:py-5">
                        <span class="min-w-0 flex-1 font-bold text-base text-white sm:text-lg">Можно ли уехать в другой город?</span>
                        <svg class="w-5 h-5 text-moto-amber transition-transform duration-300 shrink-0 ml-4" :class="{ 'rotate-180': active === 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="active === 0" x-collapse x-cloak>
                        <div class="px-6 pb-6 pt-2 text-silver text-base leading-relaxed border-t border-white/5 mt-2">
                            Да. Краснодарский край и Крым — без ограничений. Выезд в другие регионы согласовывается индивидуально. Суточный лимит — 300 км, перепробег оплачивается отдельно.
                        </div>
                    </div>
                </div>
                <div class="bg-carbon border border-white/5 rounded-2xl overflow-hidden transition-colors hover:border-white/10">
                    <button type="button" @click="active !== 1 ? active = 1 : active = null" class="flex min-h-12 w-full items-center justify-between gap-3 px-4 py-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-moto-amber/50 sm:min-h-14 sm:px-6 sm:py-5">
                        <span class="min-w-0 flex-1 font-bold text-base text-white sm:text-lg">Какие документы нужны для аренды?</span>
                        <svg class="w-5 h-5 text-moto-amber transition-transform duration-300 shrink-0 ml-4" :class="{ 'rotate-180': active === 1 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="active === 1" x-collapse x-cloak>
                        <div class="px-6 pb-6 pt-2 text-silver text-base leading-relaxed border-t border-white/5 mt-2">
                            Паспорт (возраст от 21 года) и права категории «А» (стаж от 2 лет). Только оригиналы документов.
                        </div>
                    </div>
                </div>
                <div class="bg-carbon border border-white/5 rounded-2xl overflow-hidden transition-colors hover:border-white/10">
                    <button type="button" @click="active !== 2 ? active = 2 : active = null" class="flex min-h-12 w-full items-center justify-between gap-3 px-4 py-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-moto-amber/50 sm:min-h-14 sm:px-6 sm:py-5">
                        <span class="min-w-0 flex-1 font-bold text-base text-white sm:text-lg">Есть ли залог?</span>
                        <svg class="w-5 h-5 text-moto-amber transition-transform duration-300 shrink-0 ml-4" :class="{ 'rotate-180': active === 2 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="active === 2" x-collapse x-cloak>
                        <div class="px-6 pb-6 pt-2 text-silver text-base leading-relaxed border-t border-white/5 mt-2">
                            Да, предусмотрен возвратный депозит, размер которого зависит от класса мотоцикла (от 30 000 до 80 000 рублей). Он блокируется на карте или вносится наличными и возвращается сразу после сдачи техники без повреждений.
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
