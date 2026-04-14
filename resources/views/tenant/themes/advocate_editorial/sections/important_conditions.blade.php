@php
    $cards = is_array($data['cards'] ?? null) ? $data['cards'] : [];
    $cards = array_values(array_filter($cards, fn ($c) => is_array($c) && trim((string) ($c['title'] ?? '')) !== ''));
    $legal = trim((string) ($data['legal_note'] ?? ''));
    $h = trim((string) ($data['section_heading'] ?? ''));
    if ($cards === [] && $legal === '') {
        return;
    }
@endphp
<section class="expert-conditions-mega relative mb-14 min-w-0 sm:mb-20 lg:mb-28">
    <div class="relative overflow-hidden rounded-[1.5rem] border border-white/[0.08] bg-gradient-to-br from-[#0c0f17] to-[#050608] px-4 py-8 shadow-[0_28px_64px_-20px_rgba(0,0,0,0.72)] ring-1 ring-inset ring-white/[0.04] sm:rounded-[2rem] sm:px-8 sm:py-11 lg:p-14">
        @if($h !== '')
            <div class="mb-8 flex flex-wrap items-start gap-3 sm:mb-10 sm:items-center sm:gap-4 lg:mb-12">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/[0.04] text-moto-amber ring-1 ring-inset ring-white/[0.08]" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </span>
                <h2 class="expert-section-title min-w-0 flex-1 text-balance text-[clamp(1.45rem,3.2vw,2.25rem)] font-bold leading-tight tracking-tight text-white/95">{{ $h }}</h2>
            </div>
        @endif
        
        @if($cards !== [])
            <div class="grid min-w-0 gap-3 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 lg:gap-8">
                @foreach($cards as $card)
                    <div class="expert-condition-card group flex min-h-full min-w-0 flex-col rounded-xl border border-white/[0.04] bg-white/[0.015] p-4 transition-colors hover:bg-white/[0.03] sm:rounded-2xl sm:p-6">
                        <h3 class="text-[1rem] font-bold leading-snug text-white/90 sm:text-[17px]">{{ $card['title'] ?? '' }}</h3>
                        @if(filled($card['body'] ?? ''))
                            <p class="mt-3 text-[14px] leading-relaxed text-silver/85">{{ $card['body'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        
        @if($legal !== '')
            <div class="mt-10 lg:mt-12 flex items-start gap-4 rounded-xl border border-white/[0.03] bg-black/20 p-5 sm:p-6">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <p class="text-[13px] leading-relaxed text-silver/60 sm:text-[14px] max-w-4xl">{{ $legal }}</p>
            </div>
        @endif
    </div>
</section>
