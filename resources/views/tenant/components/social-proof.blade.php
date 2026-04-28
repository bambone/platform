@props(['section' => null, 'reviews' => []])
@php
    use App\Support\Typography\RussianTypography;

    $sectionData = is_array($section ?? null) ? $section : [];
    $heading = ($sectionData['heading'] ?? null) ?: 'Отзывы клиентов';
    $subheading = ($sectionData['subheading'] ?? null) ?: 'Публичные отзывы: имена, города, даты — по мере публикации в кабинете.';
    $headingTied = RussianTypography::tiePrepositionsToNextWord($heading);
    $subheadingTied = RussianTypography::tiePrepositionsToNextWord($subheading);
    $reviewList = collect($reviews ?? []);
    $useReviews = $reviewList->isNotEmpty();
@endphp
<section class="relative z-10 border-t border-white/[0.02] bg-carbon py-16 sm:py-20 lg:py-28">
    <div class="mx-auto max-w-7xl px-3 sm:px-4 md:px-8">
        <div class="mb-10 max-w-2xl sm:mb-12">
            <h2 class="mb-3 text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">{{ $headingTied }}</h2>
            <p class="text-sm leading-relaxed text-zinc-300 sm:text-base md:text-lg">{{ $subheadingTied }}</p>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:gap-6 md:grid-cols-3 md:gap-6 lg:gap-8">
            @if($useReviews)
                @foreach($reviewList as $index => $review)
                    <div class="flex flex-col justify-between rounded-2xl border border-white/5 bg-obsidian/40 p-5 shadow-inner transition-colors hover:border-white/10 sm:p-6 md:p-8 {{ $index === 1 ? 'hidden md:flex' : '' }}">
                        <div>
                            <div class="flex items-center gap-1 mb-6 text-moto-amber opacity-90">
                                @for($i = 0; $i < min(5, (int)($review->rating ?? 5)); $i++)
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                                @endfor
                            </div>
                            <div class="mb-8">
                                @include('tenant.components.review-quote-and-expand', [
                                    'review' => $review,
                                    'scopeId' => 0,
                                    'quoteClass' => 'text-white/95 text-[15px] sm:text-base leading-relaxed font-medium',
                                    'openMark' => '«',
                                    'closeMark' => '»',
                                    'readMoreClass' => 'text-sm font-semibold text-moto-amber underline-offset-4 hover:text-moto-amber/90 hover:underline',
                                ])
                            </div>
                        </div>
                        <div class="border-t border-white/5 pt-5 flex items-center gap-4">
                            @php $avatarUrl = $review->publicAvatarUrl(); @endphp
                            <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-white/10 shrink-0 shadow-lg flex items-center justify-center bg-moto-amber/20">
                                @if($avatarUrl)
                                    <img src="{{ $avatarUrl }}" alt="{{ $review->name }}" class="w-full h-full object-cover" loading="lazy" decoding="async" fetchpriority="low" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                                @endif
                                @php $initials = strtoupper(collect(explode(' ', $review->name ?? '?'))->take(2)->map(fn($s) => mb_substr($s, 0, 1))->implode('')); @endphp
                                <span class="text-moto-amber font-bold text-sm {{ $avatarUrl ? 'hidden' : '' }}">{{ $initials ?: '?' }}</span>
                            </div>
                            <div>
                                <span class="block text-white font-bold text-sm">{{ $review->name }}</span>
                                @php
                                    $dateStr = $review->publicReviewDateFormatted();
                                    $metaParts = array_values(array_filter([
                                        trim((string) ($review->city ?? '')),
                                        $dateStr,
                                    ]));
                                @endphp
                                @if($metaParts !== [])
                                <span class="block text-zinc-300 text-xs mt-0.5">{{ implode(' · ', $metaParts) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
            <!-- Review 1 (fallback) -->
            <div class="flex flex-col justify-between rounded-2xl border border-white/5 bg-obsidian/40 p-5 shadow-inner transition-colors hover:border-white/10 sm:p-6 md:p-8">
                <div>
                    <div class="flex items-center gap-1 mb-6 text-moto-amber opacity-90">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                    </div>
                    <p class="text-white/95 text-[15px] sm:text-base leading-relaxed mb-8 font-medium">«Всё чётко по срокам, без сюрпризов по цене. Сервис спокойный, рекомендую.»</p>
                </div>
                <div class="border-t border-white/5 pt-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-white/10 shrink-0 shadow-lg flex items-center justify-center bg-moto-amber/20">
                        <img src="{{ theme_platform_asset_url('avatars/avatar-1.png') }}" alt="" class="w-full h-full object-cover" loading="lazy" decoding="async" fetchpriority="low" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                        <span class="text-moto-amber font-bold text-sm hidden">А</span>
                    </div>
                    <div>
                        <span class="block text-white font-bold text-sm">Алексей М.</span>
                        <span class="block text-zinc-300 text-xs mt-0.5">Москва · 12.09.2024</span>
                    </div>
                </div>
            </div>

            <!-- Review 2 -->
            <div class="hidden flex-col justify-between rounded-2xl border border-white/5 bg-obsidian/40 p-5 shadow-inner transition-colors hover:border-white/10 sm:p-6 md:flex md:flex-col md:p-8">
                <div>
                    <div class="flex items-center gap-1 mb-6 text-moto-amber opacity-90">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                    </div>
                    <p class="text-white/95 text-[15px] sm:text-base leading-relaxed mb-8 font-medium">«Понравилось, что договорились заранее и без “доп. работ”. Вернусь.»</p>
                </div>
                <div class="border-t border-white/5 pt-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-white/10 shrink-0 shadow-lg flex items-center justify-center bg-moto-amber/20">
                        <img src="{{ theme_platform_asset_url('avatars/avatar-2.png') }}" alt="" class="w-full h-full object-cover" loading="lazy" decoding="async" fetchpriority="low" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                        <span class="text-moto-amber font-bold text-sm hidden">И</span>
                    </div>
                    <div>
                        <span class="block text-white font-bold text-sm">Ирина С.</span>
                        <span class="block text-zinc-300 text-xs mt-0.5">Санкт-Петербург · 04.10.2024</span>
                    </div>
                </div>
            </div>

            <!-- Review 3 -->
            <div class="flex flex-col justify-between rounded-2xl border border-white/5 bg-obsidian/40 p-5 shadow-inner transition-colors hover:border-white/10 sm:p-6 md:p-8">
                <div>
                    <div class="flex items-center gap-1 mb-6 text-moto-amber opacity-90">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                    </div>
                    <p class="text-white/95 text-[15px] sm:text-base leading-relaxed mb-8 font-medium">«Удобно, что всё в одном месте на сайте: условия, контакты, ответы в FAQ.»</p>
                </div>
                <div class="border-t border-white/5 pt-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-white/10 shrink-0 shadow-lg flex items-center justify-center bg-moto-amber/20">
                        <img src="{{ theme_platform_asset_url('avatars/avatar-3.png') }}" alt="" class="w-full h-full object-cover" loading="lazy" decoding="async" fetchpriority="low" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                        <span class="text-moto-amber font-bold text-sm hidden">А</span>
                    </div>
                    <div>
                        <span class="block text-white font-bold text-sm">Анна В.</span>
                        <span class="block text-zinc-300 text-xs mt-0.5">Нижний Новгород · 18.11.2024</span>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @if($useReviews)
        @include('tenant.partials.expert-video-dialog-script')
    @endif
</section>
