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
<section class="expert-pricing-mega mb-16 sm:mb-24">
    @if($h !== '')
        <h2 class="text-balance text-[clamp(1.5rem,3.5vw,2.35rem)] font-bold tracking-tight text-white">{{ $h }}</h2>
    @endif
    @if($sub !== '')
        <p class="mt-4 max-w-3xl text-base leading-relaxed text-silver sm:text-lg">{{ $sub }}</p>
    @endif

    <div class="mt-9 grid gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
        @foreach($programs as $program)
            @php
                $price = $program->formattedPriceLabel($currency);
                $isEntry = $entrySlug !== '' && $program->slug === $entrySlug;
            @endphp
            <div class="expert-pricing-card flex flex-col rounded-2xl border p-6 {{ $isEntry ? 'expert-pricing-card--entry border-moto-amber/40 bg-gradient-to-br from-moto-amber/[0.14] to-white/[0.03] ring-1 ring-moto-amber/25' : 'border-white/10 bg-[#0b0d14]/85 backdrop-blur-sm' }}">
                @if($isEntry)
                    <span class="mb-3 inline-flex w-fit rounded-full bg-moto-amber/25 px-3 py-1 text-[0.65rem] font-bold uppercase tracking-wider text-moto-amber">Старт с одного занятия</span>
                @endif
                <h3 class="text-lg font-semibold text-white">{{ $program->title }}</h3>
                @if(filled($program->teaser))
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-silver">{{ $program->teaser }}</p>
                @endif
                <div class="mt-5 border-t border-white/10 pt-5 text-xl font-bold text-white">
                    @if($price !== null)
                        @if(filled($program->price_prefix))
                            <span class="block text-xs font-normal uppercase tracking-wide text-silver">{{ $program->price_prefix }}</span>
                        @endif
                        {{ $price }}
                    @else
                        <span class="text-base font-medium text-silver">По запросу</span>
                    @endif
                </div>
            </div>
        @endforeach
        @foreach($manual as $row)
            <div class="expert-pricing-card rounded-2xl border border-white/10 bg-[#0b0d14]/85 p-6 backdrop-blur-sm">
                <h3 class="text-lg font-semibold text-white">{{ $row['title'] }}</h3>
                @if(filled($row['body'] ?? ''))
                    <p class="mt-2 text-sm leading-relaxed text-silver">{{ $row['body'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
    @if($note !== '')
        <p class="mt-7 max-w-3xl text-sm leading-relaxed text-silver/80">{{ $note }}</p>
    @endif
</section>
