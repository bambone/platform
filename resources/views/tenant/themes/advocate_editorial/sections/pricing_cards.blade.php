@php
    $tenant = tenant();
    if ($tenant === null) {
        return;
    }
    $resolved = \App\Models\TenantServiceProgram::resolvePricingCardsSection((int) $tenant->id, $data);
    $programs = $resolved['programs'];
    $manual = $resolved['manual_cards'];
    $currency = $tenant->currency ?? 'RUB';
    if ($programs->isEmpty() && $manual === []) {
        return;
    }
    $h = trim((string) ($data['heading'] ?? ''));
    $sub = trim((string) ($data['subheading'] ?? ''));
    $note = trim((string) ($data['note'] ?? ''));
    $entrySlug = trim((string) ($data['entry_point_slug'] ?? 'single-session'));
@endphp
<section class="expert-pricing-mega relative mb-14 min-w-0 sm:mb-20 lg:mb-28" x-data="{ priceMore: false }">
    @if($h !== '')
        <h2 class="expert-section-title text-balance text-[clamp(1.65rem,4vw,3rem)] font-bold leading-[1.12] tracking-tight text-white/95 sm:leading-[1.1]">{{ $h }}</h2>
    @endif
    @if($sub !== '')
        <p class="mt-4 max-w-3xl text-[15px] font-normal leading-[1.65] text-silver/85 sm:mt-5 sm:text-lg">{{ $sub }}</p>
    @endif

    <div class="mt-6 grid min-w-0 gap-3 sm:mt-10 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 lg:gap-8">
        @foreach($programs as $pi => $program)
            @php
                $price = $program->formattedPriceLabel($currency);
                $isEntry = $entrySlug !== '' && $program->slug === $entrySlug;
            @endphp
            <div
                class="expert-pricing-card flex min-h-full min-w-0 flex-col overflow-hidden rounded-[1.35rem] border transition-all duration-300 sm:rounded-[1.5rem] sm:hover:-translate-y-1 {{ $isEntry ? 'expert-pricing-card--entry border-moto-amber/30 bg-gradient-to-br from-[#12141c] to-[#0a0c12] shadow-[0_22px_52px_-16px_rgba(0,0,0,0.68)] ring-1 ring-inset ring-white/[0.05]' : 'border-white/[0.05] bg-white/[0.02] hover:bg-white/[0.04]' }}"
                @if($pi >= 3)
                    x-bind:class="{ 'max-lg:hidden': !priceMore }"
                @endif
            >
                <div class="min-w-0 flex-1 p-4 sm:p-8">
                    @if($isEntry)
                        <div class="mb-4 flex items-center gap-2">
                            <span class="flex h-1.5 w-1.5 rounded-full bg-moto-amber ring-2 ring-moto-amber/40"></span>
                            <span class="text-[0.65rem] font-bold uppercase tracking-widest text-moto-amber/90">Старт | Одно занятие</span>
                        </div>
                    @endif
                    <h3 class="text-xl font-bold text-white/95 leading-tight sm:text-2xl">{{ $program->title }}</h3>
                    @if(filled($program->teaser))
                        <p class="mt-4 text-[14px] leading-relaxed text-silver/80">{{ $program->teaser }}</p>
                    @endif
                </div>
                
                <div class="mt-auto border-t border-white/[0.05] bg-black/20 p-4 sm:p-8">
                    @if($price !== null)
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-extrabold tracking-tight text-white/95">{{ $price }}</span>
                            @if(filled($program->price_prefix))
                                <span class="text-[11px] font-bold uppercase tracking-wider text-silver/70">/ {{ $program->price_prefix }}</span>
                            @endif
                        </div>
                    @else
                        <span class="text-lg font-bold tracking-tight text-white/90">По запросу</span>
                    @endif
                </div>
            </div>
        @endforeach
        @foreach($manual as $mi => $row)
            @php $priceIdx = $programs->count() + $mi; @endphp
            <div
                class="expert-pricing-card flex min-h-full min-w-0 flex-col rounded-[1.35rem] border border-white/[0.05] bg-white/[0.015] p-4 transition-colors hover:bg-white/[0.03] sm:rounded-[1.5rem] sm:p-8"
                @if($priceIdx >= 3)
                    x-bind:class="{ 'max-lg:hidden': !priceMore }"
                @endif
            >
                <h3 class="text-xl font-bold text-white/95 leading-tight sm:text-2xl">{{ $row['title'] }}</h3>
                @if(filled($row['body'] ?? ''))
                    <p class="mt-4 text-[14px] leading-relaxed text-silver/80">{{ $row['body'] }}</p>
                @endif
            </div>
        @endforeach
        @php $priceCardCount = $programs->count() + count($manual); @endphp
        @if($priceCardCount > 3)
            <div class="col-span-full flex justify-center pt-1 sm:col-span-2 lg:hidden">
                <button type="button" class="min-h-11 rounded-full border border-white/12 bg-white/[0.04] px-5 py-2 text-sm font-semibold text-white/90" @click="priceMore = !priceMore" x-text="priceMore ? 'Свернуть прайс' : 'Посмотреть весь прайс'"></button>
            </div>
        @endif
    </div>
    @if($note !== '')
        <div class="mt-8 flex items-start gap-4 rounded-xl border border-white/[0.03] bg-white/[0.015] p-5 sm:p-6 lg:mt-10">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <p class="text-[13px] leading-relaxed text-silver/70 sm:text-[14px] max-w-4xl">{{ $note }}</p>
        </div>
    @endif
</section>
