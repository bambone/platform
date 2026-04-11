@php
    $cards = is_array($data['cards'] ?? null) ? $data['cards'] : [];
    $cards = array_values(array_filter($cards, fn ($c) => is_array($c) && trim((string) ($c['title'] ?? '')) !== ''));
    $legal = trim((string) ($data['legal_note'] ?? ''));
    $h = trim((string) ($data['section_heading'] ?? ''));
    if ($cards === [] && $legal === '') {
        return;
    }
@endphp
<section class="expert-conditions-mega mb-16 sm:mb-24">
    @if($h !== '')
        <h2 class="mb-10 text-balance text-[clamp(1.45rem,3.2vw,2.1rem)] font-bold tracking-tight text-white">{{ $h }}</h2>
    @endif
    @if($cards !== [])
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($cards as $card)
                <div class="expert-condition-card rounded-2xl border border-white/8 bg-white/[0.025] px-5 py-6 sm:px-6 sm:py-7">
                    <h3 class="text-base font-semibold text-white">{{ $card['title'] ?? '' }}</h3>
                    @if(filled($card['body'] ?? ''))
                        <p class="mt-3 text-sm leading-relaxed text-silver/95">{{ $card['body'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
    @if($legal !== '')
        <div class="mt-8 rounded-xl border border-white/6 bg-[#080a10]/60 px-5 py-4 sm:px-6">
            <p class="text-xs leading-relaxed text-silver/75">{{ $legal }}</p>
        </div>
    @endif
</section>
