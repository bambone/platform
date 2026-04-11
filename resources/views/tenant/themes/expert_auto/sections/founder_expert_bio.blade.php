@php
    use App\Tenant\Expert\ExpertBrandMediaUrl;
    $h = trim((string) ($data['heading'] ?? ''));
    $lead = trim((string) ($data['lead'] ?? ''));
    $paragraphs = is_array($data['paragraphs'] ?? null) ? $data['paragraphs'] : [];
    $texts = [];
    foreach ($paragraphs as $p) {
        if (is_string($p)) {
            $t = trim($p);
        } elseif (is_array($p)) {
            $t = trim((string) ($p['text'] ?? ''));
        } else {
            $t = '';
        }
        if ($t !== '') {
            $texts[] = $t;
        }
    }
    $portraitUrl = trim((string) ($data['portrait_image_url'] ?? ''));
    if ($portraitUrl === '' && isset($data['photo_slot']) && is_array($data['photo_slot'])) {
        $portraitUrl = trim((string) ($data['photo_slot']['url'] ?? ''));
    }
    $portraitUrl = ExpertBrandMediaUrl::resolve($portraitUrl);
    $portraitAlt = trim((string) ($data['portrait_image_alt'] ?? ''));
    if ($portraitAlt === '') {
        $portraitAlt = $h !== '' ? $h : 'Портрет инструктора';
    }
    $trustPoints = [];
    $rawTrust = $data['trust_points'] ?? [];
    if (is_array($rawTrust)) {
        foreach ($rawTrust as $tp) {
            $line = is_array($tp) ? trim((string) ($tp['text'] ?? '')) : trim((string) $tp);
            if ($line !== '') {
                $trustPoints[] = $line;
            }
        }
    }
    if ($h === '' && $lead === '' && $texts === [] && $portraitUrl === '' && $trustPoints === []) {
        return;
    }
    $sectionId = trim((string) ($data['section_id'] ?? ''));
    $ctaLabel = trim((string) ($data['cta_label'] ?? ''));
    $ctaAnchor = trim((string) ($data['cta_anchor'] ?? ''));
@endphp
<section @if($sectionId !== '') id="{{ e($sectionId) }}" @endif class="expert-bio-mega relative mb-14 min-w-0 scroll-mt-24 sm:mb-20 sm:scroll-mt-28 lg:mb-28">
    <div class="relative overflow-hidden rounded-[1.5rem] border border-white/[0.06] bg-gradient-to-br from-[#0c0f17] to-[#050608] shadow-2xl sm:rounded-[2rem]">
        <div class="pointer-events-none absolute -left-40 top-0 h-[40rem] w-[40rem] rounded-full bg-moto-amber/5 blur-[120px]" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-20 bottom-0 h-80 w-80 rounded-full bg-white/[0.02] blur-[80px]" aria-hidden="true"></div>
        
        <div class="relative z-10 grid gap-8 p-6 sm:gap-12 sm:p-10 lg:grid-cols-12 lg:items-center lg:gap-16 lg:p-16 xl:p-20">
            @if($portraitUrl !== '')
            <div class="relative min-w-0 lg:col-span-5">
                    <div class="pointer-events-none absolute -inset-3 rounded-[1.5rem] bg-gradient-to-br from-moto-amber/10 to-transparent blur-2xl sm:-inset-4 sm:rounded-[2rem]"></div>
                    <figure class="relative mx-auto max-w-md overflow-hidden rounded-[1.35rem] border border-white/10 shadow-[0_32px_80px_-24px_rgba(0,0,0,0.8)] ring-1 ring-inset ring-white/[0.03] sm:rounded-[1.5rem] lg:mx-0 lg:max-w-none">
                        <img src="{{ e($portraitUrl) }}" alt="{{ e($portraitAlt) }}" class="aspect-[4/5] w-full object-cover object-[center_15%] sm:aspect-[3/4] transition-transform duration-[2s] hover:scale-105" loading="lazy" decoding="async" width="640" height="800">
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#050608]/40 via-transparent to-transparent"></div>
                    </figure>
            </div>
            @endif
            <div class="min-w-0 {{ $portraitUrl !== '' ? 'lg:col-span-7' : 'lg:col-span-12 lg:max-w-4xl lg:mx-auto' }}">
                @if($h !== '')
                    <h2 class="expert-section-title text-balance text-[clamp(1.75rem,4vw,3.25rem)] font-extrabold tracking-tight text-white/95 leading-[1.1]">{{ $h }}</h2>
                @endif
                @if($lead !== '')
                    <p class="mt-6 text-[17px] font-semibold leading-relaxed text-moto-amber/90 sm:text-[19px] max-w-2xl">{{ $lead }}</p>
                @endif
                
                <div class="mt-8 space-y-6">
                    @foreach($texts as $t)
                        <p class="text-[15px] leading-[1.7] text-silver/85 sm:text-[17px] font-medium max-w-3xl">{{ $t }}</p>
                    @endforeach
                </div>

                @if(count($trustPoints) > 0)
                    <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:mt-12">
                        @foreach($trustPoints as $line)
                            <li class="flex items-start gap-3">
                                <span class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-moto-amber/20 ring-1 ring-moto-amber/40">
                                    <svg class="h-3 w-3 text-moto-amber" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                </span>
                                <span class="text-[14px] leading-snug text-white/90 font-medium sm:text-[15px]">{{ $line }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if($ctaLabel !== '' && $ctaAnchor !== '')
                    <div class="mt-12 flex flex-wrap gap-4">
                        <a href="{{ $ctaAnchor }}" class="tenant-btn-primary group relative inline-flex min-h-14 w-full items-center justify-center gap-3 overflow-hidden rounded-xl px-8 text-[15px] font-bold shadow-xl transition-all hover:scale-[1.02] hover:shadow-moto-amber/20 sm:w-auto sm:px-10">
                            <span class="relative z-10">{{ $ctaLabel }}</span>
                            <span class="relative z-10 flex h-6 w-6 items-center justify-center rounded-full bg-black/10 transition-transform group-hover:translate-x-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </span>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
