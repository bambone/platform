@props(['section' => null, 'reviews' => []])
@php
    $sectionData = is_array($section ?? null) ? $section : [];
    $heading = ($sectionData['heading'] ?? null) ?: 'Отзывы райдеров';
    $subheading = ($sectionData['subheading'] ?? null) ?: 'Реальные эмоции с южных трасс. Фото, имена, города.';
    $reviewList = collect($reviews ?? []);
    $useReviews = $reviewList->isNotEmpty();
@endphp
<section class="py-20 lg:py-28 relative z-10 bg-carbon border-t border-white/[0.02]">
    <div class="max-w-7xl mx-auto px-4 md:px-8">
        <div class="mb-12 md:max-w-2xl">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-3">{{ $heading }}</h2>
            <p class="text-silver/80 text-lg">{{ $subheading }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
            @if($useReviews)
                @foreach($reviewList as $index => $review)
                    <div class="bg-obsidian/40 rounded-2xl p-6 md:p-8 border border-white/5 flex flex-col justify-between shadow-inner hover:border-white/10 transition-colors {{ $index === 1 ? 'hidden md:flex' : '' }}">
                        <div>
                            <div class="flex items-center gap-1 mb-6 text-moto-amber opacity-90">
                                @for($i = 0; $i < min(5, (int)($review->rating ?? 5)); $i++)
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                                @endfor
                            </div>
                            <p class="text-white/95 text-[15px] sm:text-base leading-relaxed mb-8 font-medium">«{{ $review->text }}»</p>
                        </div>
                        <div class="border-t border-white/5 pt-5 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-white/10 shrink-0 shadow-lg flex items-center justify-center bg-moto-amber/20">
                                @if($review->avatar_url)
                                    <img src="{{ $review->avatar_url }}" alt="{{ $review->name }}" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                                @endif
                                @php $initials = strtoupper(collect(explode(' ', $review->name ?? '?'))->take(2)->map(fn($s) => mb_substr($s, 0, 1))->implode('')); @endphp
                                <span class="text-moto-amber font-bold text-sm {{ $review->avatar_url ? 'hidden' : '' }}">{{ $initials ?: '?' }}</span>
                            </div>
                            <div>
                                <span class="block text-white font-bold text-sm">{{ $review->name }}</span>
                                <span class="block text-silver text-xs mt-0.5">{{ $review->city ?? '' }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
            <!-- Review 1 (fallback) -->
            <div class="bg-obsidian/40 rounded-2xl p-6 md:p-8 border border-white/5 flex flex-col justify-between shadow-inner hover:border-white/10 transition-colors">
                <div>
                    <div class="flex items-center gap-1 mb-6 text-moto-amber opacity-90">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                    </div>
                    <p class="text-white/95 text-[15px] sm:text-base leading-relaxed mb-8 font-medium">«Огонь! Выдали за 10 минут, шлемы новые. Закат на побережье — разрыв.»</p>
                </div>
                <div class="border-t border-white/5 pt-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-white/10 shrink-0 shadow-lg flex items-center justify-center bg-moto-amber/20">
                        <img src="{{ asset('images/avatar-1.png') }}" alt="Алексей М." class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                        <span class="text-moto-amber font-bold text-sm hidden">АМ</span>
                    </div>
                    <div>
                        <span class="block text-white font-bold text-sm">Алексей М.</span>
                        <span class="block text-silver text-xs mt-0.5">Геленджик</span>
                    </div>
                </div>
            </div>

            <!-- Review 2 -->
            <div class="bg-obsidian/40 rounded-2xl p-6 md:p-8 border border-white/5 flex flex-col justify-between shadow-inner hover:border-white/10 transition-colors hidden md:flex">
                <div>
                    <div class="flex items-center gap-1 mb-6 text-moto-amber opacity-90">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                    </div>
                    <p class="text-white/95 text-[15px] sm:text-base leading-relaxed mb-8 font-medium">«Никаких доплат по факту. Мот бодрый, тормоза цепкие. Следующий раз — на неделю.»</p>
                </div>
                <div class="border-t border-white/5 pt-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-white/10 shrink-0 shadow-lg flex items-center justify-center bg-moto-amber/20">
                        <img src="{{ asset('images/avatar-2.png') }}" alt="Игорь С." class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                        <span class="text-moto-amber font-bold text-sm hidden">ИС</span>
                    </div>
                    <div>
                        <span class="block text-white font-bold text-sm">Игорь С.</span>
                        <span class="block text-silver text-xs mt-0.5">Анапа</span>
                    </div>
                </div>
            </div>

            <!-- Review 3 -->
            <div class="bg-obsidian/40 rounded-2xl p-6 md:p-8 border border-white/5 flex flex-col justify-between shadow-inner hover:border-white/10 transition-colors">
                <div>
                    <div class="flex items-center gap-1 mb-6 text-moto-amber opacity-90">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                    </div>
                    <p class="text-white/95 text-[15px] sm:text-base leading-relaxed mb-8 font-medium">«Пригнали к отелю, сдали там же. Мотик ухоженный. Абрау-Дюрсо на закате — нечто.»</p>
                </div>
                <div class="border-t border-white/5 pt-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-white/10 shrink-0 shadow-lg flex items-center justify-center bg-moto-amber/20">
                        <img src="{{ asset('images/avatar-3.png') }}" alt="Анна В." class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                        <span class="text-moto-amber font-bold text-sm hidden">АВ</span>
                    </div>
                    <div>
                        <span class="block text-white font-bold text-sm">Анна В.</span>
                        <span class="block text-silver text-xs mt-0.5">Новороссийск</span>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</section>
