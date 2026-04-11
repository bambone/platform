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
<section class="expert-process-mega relative mb-14 min-w-0 sm:mb-20 lg:mb-28">
    <div class="relative z-10 grid gap-8 sm:gap-12 lg:grid-cols-12 lg:gap-16 xl:gap-24">
        {{-- На мобиле сначала видео/фото, затем шаги --}}
        <div class="order-2 min-w-0 lg:order-none lg:col-span-7">
            @if($h !== '')
                <h2 class="expert-section-title mb-6 text-balance text-[clamp(1.65rem,4vw,3rem)] font-bold leading-[1.12] tracking-tight text-white/95 sm:mb-10 sm:leading-[1.1] lg:mb-12">{{ $h }}</h2>
            @endif
            <div class="relative min-w-0">
                {{-- Вертикальная линия --}}
                <div class="absolute bottom-6 left-[1.1rem] top-2 z-0 w-px bg-gradient-to-b from-moto-amber/40 via-moto-amber/10 to-transparent sm:left-[1.35rem]" aria-hidden="true"></div>
                
                <ol class="relative z-10 list-none space-y-0 p-0">
                    @foreach($steps as $i => $step)
                        <li class="expert-process-mega__step relative flex pb-7 last:pb-0 sm:pb-12">
                            <div class="flex flex-col items-center">
                                <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#0a0d14] ring-2 ring-moto-amber/30 sm:h-11 sm:w-11" aria-hidden="true">
                                    <span class="absolute inset-0 rounded-full bg-moto-amber/10 blur-[4px]"></span>
                                    <span class="relative text-[15px] font-bold text-moto-amber sm:text-[17px]">{{ $i + 1 }}</span>
                                </span>
                            </div>
                            <div class="min-w-0 flex-1 pl-5 sm:pl-8">
                                <h3 class="text-[1.05rem] font-bold leading-snug text-white/95 sm:text-xl md:text-2xl">{{ $step['title'] ?? '' }}</h3>
                                @if(filled($step['body'] ?? ''))
                                    <p class="mt-2 text-[14px] leading-relaxed text-silver/80 sm:mt-3 sm:text-[16px]">{{ $step['body'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
        
        {{-- Медиа и примечание --}}
        <div class="order-1 flex min-w-0 flex-col gap-5 sm:gap-8 lg:order-none lg:col-span-5 lg:sticky lg:top-24 lg:max-h-[calc(100vh-6rem)]">
            @if($showVideo)
                <div class="group relative overflow-hidden rounded-[1.35rem] border border-white/10 bg-[#050608] shadow-[0_32px_80px_-24px_rgba(0,0,0,0.85)] ring-1 ring-inset ring-white/[0.05] sm:rounded-[2rem]">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0a0f18]/80 via-transparent to-transparent z-10 pointer-events-none"></div>
                    <video class="relative z-0 aspect-[4/5] w-full object-cover transition-transform duration-[2s] group-hover:scale-105 sm:aspect-[3/4]" controls playsinline preload="metadata" @if($asidePoster !== '') poster="{{ e($asidePoster) }}" @endif src="{{ e($asideVideo) }}"></video>
                </div>
            @elseif($asideImg !== '')
                <figure class="group relative overflow-hidden rounded-[1.35rem] border border-white/10 bg-[#050608] shadow-[0_32px_80px_-24px_rgba(0,0,0,0.85)] ring-1 ring-inset ring-white/[0.05] sm:rounded-[2rem]">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0a0f18]/80 via-transparent to-transparent z-10 pointer-events-none"></div>
                    <img src="{{ e($asideImg) }}" alt="Практика вожденья и работа с автомобилем" class="relative z-0 aspect-[4/5] w-full object-cover object-[center_35%] transition-transform duration-[2s] group-hover:scale-105 sm:aspect-[3/4]" loading="lazy" decoding="async" width="800" height="1000">
                </figure>
            @endif
            
            @if($at !== '' || $ab !== '')
                <aside class="expert-process-mega__aside relative overflow-hidden rounded-[1.25rem] border border-moto-amber/30 bg-[#0c101a] p-4 shadow-[0_16px_40px_-16px_rgba(201,168,124,0.15)] ring-1 ring-inset ring-white/[0.03] sm:rounded-[1.75rem] sm:p-8">
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-moto-amber/10 via-transparent to-white/[0.02]"></div>
                    <div class="relative z-10">
                        @if($at !== '')
                            <h3 class="text-lg font-bold leading-tight text-white/95 sm:text-xl">{{ $at }}</h3>
                        @endif
                        @if($ab !== '')
                            <p class="mt-3 text-[13px] leading-relaxed text-silver/85 sm:mt-4 sm:text-base">{{ $ab }}</p>
                        @endif
                    </div>
                </aside>
            @endif
        </div>
    </div>
</section>
