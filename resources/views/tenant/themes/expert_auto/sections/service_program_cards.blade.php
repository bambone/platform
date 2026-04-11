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
<section @if($sid !== '') id="{{ e($sid) }}" @endif class="expert-programs-mega mb-16 scroll-mt-24 sm:mb-24 sm:scroll-mt-28">
    <div class="relative overflow-hidden rounded-[1.35rem] border border-white/8 bg-gradient-to-b from-white/[0.04] to-transparent px-4 py-10 sm:px-7 sm:py-12 lg:px-10 lg:py-14">
        <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-moto-amber/10 blur-3xl" aria-hidden="true"></div>
        @if($h !== '')
            <div class="relative z-10 mb-10 max-w-3xl lg:mb-12">
                <h2 class="expert-section-title text-balance text-[clamp(1.55rem,3.5vw,2.35rem)] font-bold tracking-tight text-white">{{ $h }}</h2>
                <p class="mt-4 text-base leading-relaxed text-silver sm:text-lg">Модули обучения под конкретную задачу: от городского комфорта до зимней безопасности и спорта.</p>
            </div>
        @endif
        <div class="relative z-10 grid gap-5 md:grid-cols-2">
            @foreach($programs as $program)
                @php
                    $audience = $linesFromJson($program->audience_json);
                    $outcomes = $linesFromJson($program->outcomes_json);
                    $formatParts = array_filter([$program->format_label, $program->duration_label]);
                    $formatLine = implode(' · ', $formatParts);
                    $price = $program->formattedPriceLabel($currency);
                @endphp
                <article class="expert-program-card flex flex-col rounded-2xl border p-6 sm:p-7 {{ $program->is_featured ? 'expert-program-card--featured border-moto-amber/35 bg-gradient-to-br from-moto-amber/[0.12] via-[#0c0f18] to-[#090b11] shadow-[0_24px_60px_-28px_rgba(201,168,124,0.45)] md:flex-row md:gap-8 md:p-8' : 'border-white/10 bg-[#0b0d14]/90 backdrop-blur-sm' }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h3 class="text-xl font-bold text-white">{{ $program->title }}</h3>
                            @if($program->is_featured)
                                <span class="shrink-0 rounded-full bg-moto-amber/20 px-3 py-1 text-[0.65rem] font-bold uppercase tracking-wider text-moto-amber">Ключевой модуль</span>
                            @endif
                        </div>

                        @if($audience !== [])
                            <p class="mt-3 text-xs font-semibold uppercase tracking-wider text-white/45">Кому подходит</p>
                            <ul class="mt-2 space-y-1 text-sm leading-relaxed text-silver">
                                @foreach(array_slice($audience, 0, 4) as $line)
                                    <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-moto-amber/70" aria-hidden="true"></span><span>{{ $line }}</span></li>
                                @endforeach
                            </ul>
                        @elseif(filled($program->teaser))
                            <p class="mt-3 text-sm leading-relaxed text-silver">{{ $program->teaser }}</p>
                        @endif

                        @if($outcomes !== [])
                            <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-white/45">Результат</p>
                            <ul class="mt-2 space-y-1 text-sm leading-relaxed text-white/88">
                                @foreach(array_slice($outcomes, 0, 4) as $line)
                                    <li class="flex gap-2"><span class="text-moto-amber" aria-hidden="true">→</span><span>{{ $line }}</span></li>
                                @endforeach
                            </ul>
                        @elseif(filled($program->description))
                            <p class="mt-4 text-sm leading-relaxed text-white/85">{{ $program->description }}</p>
                        @endif

                        @if($formatLine !== '')
                            <p class="mt-5 text-xs uppercase tracking-wide text-white/40">Формат · длительность</p>
                            <p class="mt-1 text-sm font-medium text-white/80">{{ $formatLine }}</p>
                        @elseif(filled($program->duration_label))
                            <p class="mt-5 text-xs uppercase tracking-wide text-white/40">Формат</p>
                            <p class="mt-1 text-sm font-medium text-white/80">{{ $program->duration_label }}</p>
                        @endif
                    </div>

                    <div class="mt-6 flex shrink-0 flex-col justify-end border-t border-white/10 pt-6 md:mt-0 md:w-52 md:border-l md:border-t-0 md:pl-8 md:pt-0">
                        <div class="text-2xl font-bold text-white">
                            @if($price !== null)
                                @if(filled($program->price_prefix))
                                    <span class="block text-xs font-normal uppercase tracking-wide text-silver">{{ $program->price_prefix }}</span>
                                @endif
                                {{ $price }}
                            @else
                                <span class="text-lg font-semibold text-silver">По запросу</span>
                            @endif
                        </div>
                        <a href="#expert-inquiry" class="tenant-btn-primary mt-4 inline-flex min-h-11 justify-center px-5 text-sm font-semibold">Записаться</a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
