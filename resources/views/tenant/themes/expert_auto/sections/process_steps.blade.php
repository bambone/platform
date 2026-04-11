@php
    use App\Tenant\Expert\ExpertBrandMediaUrl;
    $steps = is_array($data['steps'] ?? null) ? $data['steps'] : [];
    $steps = array_values(array_filter($steps, fn ($s) => is_array($s) && trim((string) ($s['title'] ?? '')) !== ''));
    if ($steps === []) {
        return;
    }
    $h = trim((string) ($data['section_heading'] ?? ''));
    $at = trim((string) ($data['aside_title'] ?? ''));
    $ab = trim((string) ($data['aside_body'] ?? ''));
    $asideImg = ExpertBrandMediaUrl::resolve(trim((string) ($data['aside_image_url'] ?? '')));
    $asideVideo = ExpertBrandMediaUrl::resolve(trim((string) ($data['aside_video_url'] ?? '')));
    $asidePoster = ExpertBrandMediaUrl::resolve(trim((string) ($data['aside_video_poster_url'] ?? '')));
    $showVideo = $asideVideo !== '';
@endphp
<section class="expert-process-mega mb-16 sm:mb-24">
    <div class="grid gap-12 lg:grid-cols-12 lg:gap-14">
        <div class="lg:col-span-7">
            @if($h !== '')
                <h2 class="mb-10 text-balance text-[clamp(1.5rem,3.5vw,2.25rem)] font-bold tracking-tight text-white">{{ $h }}</h2>
            @endif
            <ol class="space-y-0 border-l border-moto-amber/25 pl-6 sm:pl-8">
                @foreach($steps as $i => $step)
                    <li class="expert-process-mega__step relative pb-10 pl-2 last:pb-0">
                        <span class="absolute -left-[calc(1.5rem+5px)] top-1.5 flex h-9 w-9 -translate-x-1/2 items-center justify-center rounded-full border border-moto-amber/35 bg-[#0c0f18] text-sm font-bold text-moto-amber sm:-left-[calc(2rem+5px)]" aria-hidden="true">{{ $i + 1 }}</span>
                        <h3 class="text-lg font-semibold text-white">{{ $step['title'] ?? '' }}</h3>
                        @if(filled($step['body'] ?? ''))
                            <p class="mt-2 text-sm leading-relaxed text-silver sm:text-base">{{ $step['body'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
        <div class="flex flex-col gap-6 lg:col-span-5">
            @if($showVideo)
                <div class="overflow-hidden rounded-2xl border border-white/12 shadow-[0_20px_60px_-18px_rgba(0,0,0,0.55)]">
                    <video class="aspect-[4/3] w-full object-cover sm:aspect-[5/4]" controls playsinline muted loop preload="metadata" @if($asidePoster !== '') poster="{{ e($asidePoster) }}" @endif src="{{ e($asideVideo) }}"></video>
                </div>
            @elseif($asideImg !== '')
                <figure class="overflow-hidden rounded-2xl border border-white/12 shadow-[0_20px_60px_-18px_rgba(0,0,0,0.55)]">
                    <img src="{{ e($asideImg) }}" alt="Практика вожденья и работа с автомобилем" class="aspect-[4/3] w-full object-cover object-center sm:aspect-[5/4]" loading="lazy" decoding="async" width="800" height="640">
                </figure>
            @endif
            @if($at !== '' || $ab !== '')
                <aside class="expert-process-mega__aside rounded-2xl border border-moto-amber/25 bg-gradient-to-br from-moto-amber/[0.1] to-white/[0.03] p-6 sm:p-7 lg:sticky lg:top-24">
                    @if($at !== '')
                        <h3 class="text-lg font-semibold text-white">{{ $at }}</h3>
                    @endif
                    @if($ab !== '')
                        <p class="mt-3 text-sm leading-relaxed text-silver sm:text-base">{{ $ab }}</p>
                    @endif
                </aside>
            @endif
        </div>
    </div>
</section>
