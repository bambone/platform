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
<section @if($sectionId !== '') id="{{ e($sectionId) }}" @endif class="expert-bio-mega relative mb-16 scroll-mt-24 sm:mb-24 sm:scroll-mt-28">
    <div class="relative overflow-hidden rounded-[1.5rem] border border-white/[0.09] bg-gradient-to-br from-[#12182a] via-[#0b0f18] to-[#070910]">
        <div class="pointer-events-none absolute -left-32 top-0 h-96 w-96 rounded-full bg-moto-amber/12 blur-3xl" aria-hidden="true"></div>
        <div class="relative z-10 grid gap-10 p-6 sm:gap-12 sm:p-10 lg:grid-cols-12 lg:items-center lg:p-12">
            <div class="lg:col-span-5">
                @if($portraitUrl !== '')
                    <figure class="overflow-hidden rounded-2xl border border-white/10 shadow-[0_32px_90px_-28px_rgba(0,0,0,0.72)] ring-1 ring-white/[0.04]">
                        <img src="{{ e($portraitUrl) }}" alt="{{ e($portraitAlt) }}" class="aspect-[4/5] w-full object-cover object-[center_15%] sm:aspect-[3/4]" loading="lazy" decoding="async" width="640" height="800">
                    </figure>
                @endif
            </div>
            <div class="lg:col-span-7">
                @if($h !== '')
                    <h2 class="expert-section-title text-balance text-[clamp(1.6rem,3.7vw,2.55rem)] font-bold tracking-tight text-white">{{ $h }}</h2>
                @endif
                @if($lead !== '')
                    <p class="mt-5 text-lg font-semibold leading-snug text-moto-amber sm:text-xl">{{ $lead }}</p>
                @endif
                @foreach($texts as $t)
                    <p class="mt-5 text-sm leading-relaxed text-silver sm:text-base">{{ $t }}</p>
                @endforeach
                @if(count($trustPoints) > 0)
                    <ul class="mt-10 space-y-3">
                        @foreach($trustPoints as $line)
                            <li class="flex gap-3 text-sm text-white sm:text-base">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-moto-amber/20 text-xs font-bold text-moto-amber">✓</span>
                                <span>{{ $line }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if($ctaLabel !== '' && $ctaAnchor !== '')
                    <div class="mt-10">
                        <a href="{{ $ctaAnchor }}" class="tenant-btn-primary inline-flex min-h-12 justify-center px-8 text-base font-semibold">{{ $ctaLabel }}</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
