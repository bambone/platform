@php
    $tenant = tenant();
    if ($tenant === null) {
        return;
    }
    $programs = \App\Models\TenantServiceProgram::forServiceProgramCards((int) $tenant->id, $data);
    if ($programs->isEmpty()) {
        return;
    }
    $h = trim((string) ($data['section_heading'] ?? ''));
    $sectionLead = array_key_exists('section_lead', $data)
        ? trim((string) $data['section_lead'])
        : 'Модули обучения под конкретную задачу: от городского комфорта до зимней безопасности и спорта.';
    $sid = trim((string) ($data['section_id'] ?? ''));
    $currency = $tenant->currency ?? 'RUB';

    $linesFromJson = static function ($json): array {
        if (! is_array($json)) {
            return [];
        }
        $out = [];
        foreach ($json as $x) {
            if (is_string($x) && trim($x) !== '') {
                $out[] = trim($x);
            } elseif (is_array($x) && filled($x['text'] ?? '')) {
                $out[] = trim((string) $x['text']);
            }
        }

        return $out;
    };
@endphp
<section @if($sid !== '') id="{{ e($sid) }}" @endif class="expert-programs-mega mb-14 min-w-0 scroll-mt-24 sm:mb-20 sm:scroll-mt-28 lg:mb-28" x-data="{ programsMore: false }">
    <div class="relative overflow-hidden rounded-[1.5rem] border border-white/[0.06] bg-gradient-to-b from-[#0a0d14] to-[#050608] px-3 py-10 sm:rounded-[2rem] sm:px-8 sm:py-14 lg:px-12 lg:py-20 lg:rounded-[2.5rem]">
        <div class="pointer-events-none absolute -right-20 -top-20 h-96 w-96 rounded-full bg-moto-amber/[0.08] blur-[100px]" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-32 h-96 w-96 rounded-full bg-sky-600/[0.05] blur-[100px]" aria-hidden="true"></div>
        
        @if($h !== '')
            <div class="relative z-10 mb-8 flex min-w-0 flex-col justify-between gap-4 sm:mb-12 lg:mb-14 lg:flex-row lg:items-end">
                <div class="max-w-3xl min-w-0">
                    <h2 class="expert-section-title text-balance text-[clamp(1.65rem,4vw,3rem)] font-bold tracking-tight text-white/95 leading-[1.12] sm:leading-[1.1]">{{ $h }}</h2>
                    @if($sectionLead !== '')
                        <p class="mt-4 text-[15px] font-normal leading-[1.65] text-silver/85 sm:mt-5 sm:text-lg">{{ $sectionLead }}</p>
                    @endif
                </div>
            </div>
        @endif
        
        <div class="relative z-10 grid min-w-0 gap-4 sm:gap-6 md:grid-cols-2 lg:gap-8">
            @foreach($programs as $pi => $program)
                @php
                    $audience = $linesFromJson($program->audience_json);
                    $outcomes = $linesFromJson($program->outcomes_json);
                    $formatParts = array_filter([$program->format_label, $program->duration_label]);
                    $formatLine = implode(' · ', $formatParts);
                    $price = $program->formattedPriceLabel($currency);
                    $desktopUrl = $program->coverDesktopPublicUrl($tenant);
                    $mobileUrl = $program->coverMobilePublicUrl($tenant);
                    $coverAlt = $program->coverImageAlt();
                    $hasPaneMedia = filled($desktopUrl);
                    $mobileRefSet = trim((string) ($program->cover_mobile_ref ?? '')) !== '';
                    $useMobileSource = $hasPaneMedia && $mobileRefSet && $mobileUrl !== $desktopUrl;
                    $objPos = trim((string) ($program->cover_object_position ?? ''));
                @endphp
                <article
                    class="expert-program-card group/card relative flex min-w-0 flex-col overflow-hidden rounded-[1.35rem] border transition-transform duration-300 sm:rounded-[1.5rem] sm:hover:-translate-y-1 lg:flex-row lg:items-stretch {{ $program->is_featured ? 'expert-program-card--featured border-moto-amber/35 shadow-[0_24px_64px_-24px_rgba(201,168,124,0.35)] md:col-span-2' : 'border-white/[0.07] hover:border-white/[0.12]' }}"
                    @if($pi >= 3)
                        x-bind:class="{ 'max-lg:hidden': !programsMore }"
                    @endif
                >
                    <div class="expert-program-card__bg pointer-events-none" aria-hidden="true"></div>

                    @if($hasPaneMedia)
                        <div class="expert-program-card__media order-1 lg:order-2">
                            <picture>
                                @if($useMobileSource)
                                    <source media="(max-width: 1023px)" srcset="{{ e($mobileUrl) }}" />
                                @endif
                                <img
                                    src="{{ e($desktopUrl) }}"
                                    alt="{{ e($coverAlt) }}"
                                    class="expert-program-card__media-img h-full w-full object-cover"
                                    @if($objPos !== '') style="object-position: {{ e($objPos) }};" @endif
                                    loading="lazy"
                                    decoding="async"
                                />
                            </picture>
                            <div class="expert-program-card__media-scrim" aria-hidden="true"></div>
                        </div>
                    @endif

                    {{-- Основной контент карточки --}}
                    <div class="relative z-[1] flex min-w-0 flex-1 flex-col p-4 sm:p-8 lg:p-8 lg:pl-8 lg:pr-6 xl:p-10 {{ $hasPaneMedia ? 'order-2 lg:order-1' : 'order-1' }}">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                @if($program->is_featured)
                                    <div class="mb-3 flex items-center gap-2">
                                        <span class="flex h-1.5 w-1.5 rounded-full bg-moto-amber ring-2 ring-moto-amber/40"></span>
                                        <span class="text-[0.65rem] font-bold uppercase tracking-widest text-moto-amber/90">Флагманский модуль</span>
                                    </div>
                                @endif
                                <h3 class="{{ $program->is_featured ? 'text-2xl sm:text-3xl lg:text-3xl' : 'text-xl sm:text-2xl' }} font-bold text-white/95 leading-tight">{{ $program->title }}</h3>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-4 sm:mt-8 sm:gap-6 {{ $program->is_featured ? 'lg:flex-row lg:gap-12' : '' }}">
                            @if($audience !== [] || filled($program->teaser))
                                <div class="flex-1">
                                    <p class="text-[11px] font-bold uppercase tracking-widest text-white/50">Кому подходит</p>
                                    @if($audience !== [])
                                        <ul class="mt-3 flex flex-col gap-2.5 text-[15px] leading-relaxed text-silver/90">
                                            @foreach(array_slice($audience, 0, 4) as $line)
                                                <li class="flex items-start gap-3">
                                                    <span class="mt-1.5 flex h-1.5 w-1.5 shrink-0 rounded-full bg-moto-amber/60" aria-hidden="true"></span>
                                                    <span>{{ $line }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mt-3 text-[15px] leading-relaxed text-silver/90">{{ $program->teaser }}</p>
                                    @endif
                                </div>
                            @endif

                            @if($outcomes !== [] || filled($program->description))
                                <div class="flex-1">
                                    <p class="text-[11px] font-bold uppercase tracking-widest text-moto-amber/70">Результат</p>
                                    @if($outcomes !== [])
                                        <ul class="mt-3 flex flex-col gap-2.5 text-[15px] font-medium leading-relaxed text-white/90">
                                            @foreach(array_slice($outcomes, 0, 4) as $line)
                                                <li class="flex items-start gap-3">
                                                    <svg class="mt-[3px] h-4 w-4 shrink-0 text-moto-amber" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                    <span>{{ $line }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mt-3 text-[15px] font-medium leading-relaxed text-white/90">{{ $program->description }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                        
                        {{-- Формат внизу текстового блока --}}
                        <div class="mt-auto pt-8">
                            @if($formatLine !== '')
                                <p class="text-[10px] font-bold uppercase tracking-widest text-white/42">Формат / Длительность</p>
                                <p class="mt-1 text-[14px] font-medium text-white/70">{{ $formatLine }}</p>
                            @elseif(filled($program->duration_label))
                                <p class="text-[10px] font-bold uppercase tracking-widest text-white/42">Формат</p>
                                <p class="mt-1 text-[14px] font-medium text-white/70">{{ $program->duration_label }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Прайс и CTA: справа на lg, снизу на мобиле --}}
                    <div class="relative z-[1] order-3 flex min-w-0 shrink-0 flex-col justify-end border-t border-white/[0.08] bg-black/35 p-4 backdrop-blur-[2px] sm:p-8 lg:w-[min(100%,20rem)] lg:border-l lg:border-t-0 lg:border-white/[0.08] lg:bg-black/40 lg:p-8 xl:p-10 {{ $program->is_featured ? 'lg:bg-black/45' : '' }}">
                        <div class="flex flex-col gap-1">
                            @if($price !== null)
                                @if(filled($program->price_prefix))
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-moto-amber/80">{{ $program->price_prefix }}</span>
                                @endif
                                <span class="text-3xl font-extrabold tracking-tight text-white/95">{{ $price }}</span>
                            @else
                                <span class="text-xl font-bold tracking-tight text-white/90">По запросу</span>
                            @endif
                        </div>
                        <a href="#expert-inquiry" class="tenant-btn-primary mt-6 inline-flex min-h-[3.25rem] w-full items-center justify-center rounded-xl px-6 text-sm font-bold uppercase tracking-wide transition-transform hover:-translate-y-0.5">Записаться</a>
                    </div>
                </article>
            @endforeach
            @if($programs->count() > 3)
                <div class="col-span-full flex justify-center pt-1 md:col-span-2 lg:hidden">
                    <button type="button" class="min-h-11 w-full max-w-sm rounded-full border border-moto-amber/25 bg-moto-amber/[0.08] px-5 py-2 text-sm font-bold text-moto-amber transition hover:bg-moto-amber/15" @click="programsMore = !programsMore" x-text="programsMore ? 'Свернуть список' : 'Смотреть все программы'"></button>
                </div>
            @endif
        </div>
    </div>
</section>
