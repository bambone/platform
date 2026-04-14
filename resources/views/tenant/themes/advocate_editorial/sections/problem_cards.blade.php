@php
    use App\Support\Typography\RussianTypography;
    use App\Tenant\Expert\ExpertBrandMediaUrl;
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $items = array_values(array_filter($items, fn ($i) => is_array($i) && trim((string) ($i['title'] ?? '')) !== ''));
    if ($items === []) {
        return;
    }
    $h = trim((string) ($data['section_heading'] ?? ''));
    $lead = trim((string) ($data['section_lead'] ?? ''));
    $fn = trim((string) ($data['footnote'] ?? ''));
    $accent = ExpertBrandMediaUrl::resolve(trim((string) ($data['accent_image_url'] ?? '')));
    $fullWidth = (bool) ($data['full_width_cards'] ?? false);
    $defaultLeadFallback = tenant()?->theme_key === 'advocate_editorial'
        ? 'Тематику и срочность фиксируем на старте — без шаблонных обещаний и навязанных решений.'
        : 'Каждый запрос разбираем на практике — без шаблонных сценариев и лишнего давления.';
@endphp
@if($fullWidth)
<section class="expert-problems-full relative mb-14 min-w-0 sm:mb-20 lg:mb-28" data-page-section-type="{{ $section->section_type }}">
    <div class="relative z-10 mx-auto max-w-[98rem] px-0">
        @if($h !== '')
            <h2 class="expert-section-title max-w-3xl text-balance text-[clamp(1.75rem,4vw,2.75rem)] font-bold leading-[1.15] tracking-tight text-white sm:text-4xl">{{ RussianTypography::tiePrepositionsToNextWord($h) }}</h2>
        @endif
        @if($lead !== '')
            <p class="mt-5 max-w-3xl text-pretty text-[15px] font-normal leading-relaxed text-silver/85 sm:mt-6 sm:text-lg">{{ RussianTypography::tiePrepositionsToNextWord($lead) }}</p>
        @endif
        @if($accent !== '')
            <div class="relative mt-8 w-full overflow-hidden rounded-2xl sm:mt-10" aria-hidden="true">
                <div class="aspect-[21/9] w-full max-w-4xl">
                    <img src="{{ e($accent) }}" alt="" class="h-full w-full object-cover opacity-60 mix-blend-luminosity">
                </div>
                <div class="absolute inset-0 rounded-2xl ring-1 ring-inset ring-white/10"></div>
            </div>
        @endif
        @php $hasHeader = $h !== '' || $lead !== '' || $accent !== ''; @endphp
        <div class="{{ $hasHeader ? 'mt-8' : '' }} grid min-w-0 gap-4 sm:grid-cols-2 sm:gap-5 xl:grid-cols-3 xl:gap-6">
            @foreach($items as $item)
                @php
                    $featured = (bool) ($item['is_featured'] ?? false);
                    $linkUrl = trim((string) ($item['link_url'] ?? ''));
                    $linkLabel = trim((string) ($item['link_label'] ?? ''));
                    if ($linkLabel === '') {
                        $linkLabel = 'Подробнее';
                    }
                @endphp
                <article class="expert-problem-card relative flex min-h-0 min-w-0 flex-col rounded-[1.35rem] border p-5 transition-all duration-300 hover:-translate-y-0.5 sm:rounded-[1.5rem] sm:p-7 sm:hover:-translate-y-1 {{ $featured ? 'border-moto-amber/25 bg-gradient-to-br from-moto-amber/[0.05] to-transparent shadow-[0_18px_44px_-14px_rgba(0,0,0,0.55)] ring-1 ring-inset ring-white/[0.04]' : 'border-white/[0.04] bg-white/[0.015] hover:bg-white/[0.03]' }}">
                    @if($featured)
                        <div class="mb-4 flex items-center gap-2">
                            <span class="flex h-1.5 w-1.5 items-center justify-center rounded-full bg-moto-amber ring-2 ring-moto-amber/30"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-moto-amber/90">Частый запрос</span>
                        </div>
                    @else
                        <div class="mb-4 flex items-center gap-2">
                            <span class="block h-[1px] w-4 bg-white/10"></span>
                        </div>
                    @endif
                    <h3 class="text-[1.05rem] font-bold leading-snug text-white/95 sm:text-lg md:text-xl">{{ RussianTypography::tiePrepositionsToNextWord((string) ($item['title'] ?? '')) }}</h3>
                    @if(filled($item['description'] ?? ''))
                        <p class="mt-3 text-pretty text-[14px] leading-[1.65] text-silver/85">{{ RussianTypography::tiePrepositionsToNextWord((string) $item['description']) }}</p>
                    @endif
                    @if(filled($item['solution'] ?? ''))
                        <p class="mt-3 border-t border-white/[0.08] pt-3 text-pretty text-[13px] font-medium leading-relaxed text-silver/90 sm:text-sm">{{ RussianTypography::tiePrepositionsToNextWord((string) $item['solution']) }}</p>
                    @endif
                    @if($linkUrl !== '')
                        <a href="{{ e($linkUrl) }}" class="mt-auto pt-5 text-sm font-semibold text-moto-amber/95 underline decoration-moto-amber/35 underline-offset-4 transition hover:text-moto-amber hover:decoration-moto-amber/70 sm:pt-6">{{ $linkLabel }}</a>
                    @endif
                </article>
            @endforeach
        </div>
        @if($fn !== '')
            <p class="mt-8 max-w-3xl text-pretty text-sm font-medium leading-relaxed text-moto-amber/80">{{ RussianTypography::tiePrepositionsToNextWord($fn) }}</p>
        @endif
    </div>
</section>
@else
<section class="expert-problems-mega relative mb-14 min-w-0 sm:mb-20 lg:mb-28" x-data="{ problemsMore: false }" data-page-section-type="{{ $section->section_type }}">
    <div class="relative px-0 sm:px-0">
        <div class="relative z-10 mx-auto grid max-w-[98rem] gap-8 sm:gap-10 lg:grid-cols-[1fr_1.5fr] lg:gap-16 xl:grid-cols-[1fr_1.8fr] xl:gap-24">
            {{-- Секция с заголовком и фоном (левая колонка на desktop) --}}
            <div class="flex min-w-0 flex-col lg:sticky lg:top-28 lg:max-h-[calc(100vh-8rem)] lg:pt-4">
                @if($h !== '')
                    <h2 class="expert-section-title max-w-2xl text-balance text-[clamp(1.75rem,4vw,2.75rem)] font-bold leading-[1.15] tracking-tight text-white sm:text-4xl">{{ RussianTypography::tiePrepositionsToNextWord($h) }}</h2>
                    @if($lead !== '')
                        <p class="mt-5 max-w-xl text-pretty text-[15px] font-normal leading-relaxed text-silver/85 sm:mt-6 sm:text-lg">{{ RussianTypography::tiePrepositionsToNextWord($lead) }}</p>
                    @else
                        <p class="mt-5 max-w-xl text-pretty text-[15px] font-normal leading-relaxed text-silver/85 sm:mt-6 sm:text-lg">{{ RussianTypography::tiePrepositionsToNextWord($defaultLeadFallback) }}</p>
                    @endif
                @endif

                @if($accent !== '')
                    <div class="relative mt-8 hidden w-full overflow-hidden rounded-2xl lg:mt-10 lg:block xl:mt-14" aria-hidden="true">
                        <div class="aspect-[4/3] w-full">
                            <img src="{{ e($accent) }}" alt="" class="h-full w-full object-cover opacity-60 mix-blend-luminosity transition-opacity hover:opacity-80">
                        </div>
                        <div class="absolute inset-0 rounded-2xl ring-1 ring-inset ring-white/10"></div>
                    </div>
                @endif

                @if($fn !== '')
                    <p class="mt-8 max-w-lg text-pretty text-sm font-medium leading-relaxed text-moto-amber/80 lg:mt-auto">{{ RussianTypography::tiePrepositionsToNextWord($fn) }}</p>
                @endif
            </div>

            {{-- Карточки: на мобиле первые 3 + «Показать ещё», desktop — всё сразу --}}
            <div class="grid min-w-0 gap-4 sm:grid-cols-2 sm:gap-5 lg:gap-6">
                @foreach($items as $index => $item)
                    @php
                        $featured = (bool) ($item['is_featured'] ?? false);
                        $isOdd = $index % 2 !== 0;
                        $collapseOnMobile = $index >= 3;
                        $linkUrl = trim((string) ($item['link_url'] ?? ''));
                        $linkLabel = trim((string) ($item['link_label'] ?? ''));
                        if ($linkLabel === '') {
                            $linkLabel = 'Подробнее';
                        }
                    @endphp
                    <article
                        class="expert-problem-card relative flex min-h-0 min-w-0 flex-col rounded-[1.35rem] border p-5 transition-all duration-300 hover:-translate-y-0.5 sm:rounded-[1.5rem] sm:p-7 sm:hover:-translate-y-1 {{ $featured ? 'border-moto-amber/25 bg-gradient-to-br from-moto-amber/[0.05] to-transparent shadow-[0_18px_44px_-14px_rgba(0,0,0,0.55)] ring-1 ring-inset ring-white/[0.04]' : 'border-white/[0.04] bg-white/[0.015] hover:bg-white/[0.03]' }} {{ $isOdd && count($items) > 1 ? 'sm:mt-8 lg:mt-12' : '' }}"
                        @if($collapseOnMobile)
                            x-bind:class="{ 'max-lg:hidden': !problemsMore }"
                        @endif
                    >
                        @if($featured)
                            <div class="mb-4 flex items-center gap-2">
                                <span class="flex h-1.5 w-1.5 items-center justify-center rounded-full bg-moto-amber ring-2 ring-moto-amber/30"></span>
                                <span class="text-xs font-bold uppercase tracking-wider text-moto-amber/90">Частый запрос</span>
                            </div>
                        @else
                            <div class="mb-4 flex items-center gap-2">
                                <span class="block h-[1px] w-4 bg-white/10"></span>
                            </div>
                        @endif
                        <h3 class="text-[1.05rem] font-bold leading-snug text-white/95 sm:text-lg md:text-xl">{{ RussianTypography::tiePrepositionsToNextWord((string) ($item['title'] ?? '')) }}</h3>

                        @if(filled($item['description'] ?? ''))
                            <p class="mt-3 text-pretty text-[14px] leading-[1.65] text-silver/85">{{ RussianTypography::tiePrepositionsToNextWord((string) $item['description']) }}</p>
                        @endif

                        @if(filled($item['solution'] ?? ''))
                            <div class="mt-auto pt-5 sm:pt-6">
                                <p class="border-t border-white/[0.08] pt-3 text-pretty text-[13px] font-semibold leading-relaxed text-moto-amber/95 sm:pt-4 sm:text-sm">{{ RussianTypography::tiePrepositionsToNextWord((string) $item['solution']) }}</p>
                            </div>
                        @endif
                        @if($linkUrl !== '')
                            <a href="{{ e($linkUrl) }}" class="mt-auto pt-4 text-sm font-semibold text-moto-amber/95 underline decoration-moto-amber/35 underline-offset-4 transition hover:text-moto-amber hover:decoration-moto-amber/70 sm:pt-5">{{ $linkLabel }}</a>
                        @endif
                    </article>
                @endforeach
                @if(count($items) > 3)
                    <div class="col-span-full flex justify-center pt-1 lg:hidden">
                        <button type="button" class="min-h-11 rounded-full border border-white/12 bg-white/[0.04] px-5 py-2 text-sm font-semibold text-white/90 transition hover:border-moto-amber/30 hover:bg-white/[0.07]" @click="problemsMore = !problemsMore" x-text="problemsMore ? 'Свернуть' : 'Показать ещё запросы'"></button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endif
