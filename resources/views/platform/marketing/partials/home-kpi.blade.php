<section id="statistika" class="pm-section-anchor pm-section-y relative overflow-hidden border-b border-slate-200 bg-slate-50" aria-labelledby="statistika-heading">
    <!-- Telemetry dots background -->
    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(#94a3b8 1px, transparent 1px); background-size: 24px 24px;"></div>

    <div class="relative z-10 mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <h2 id="statistika-heading" class="fade-reveal text-balance text-xl font-bold leading-tight text-slate-900 sm:text-2xl md:text-3xl">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $pm['kpi_section_title'] ?? 'Система уже работает в реальном бизнесе') !!}</h2>
        <p class="fade-reveal mt-3 text-pretty text-base leading-relaxed text-slate-600 sm:text-lg" style="transition-delay: 100ms;">{!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $pm['kpi_section_intro'] ?? 'Не демо и не теория — реальные процессы, клиенты и нагрузка') !!}</p>

        <div class="fade-reveal mt-8 grid grid-cols-1 gap-4 sm:mt-10 sm:grid-cols-2 sm:gap-5 lg:grid-cols-4 lg:gap-6" style="transition-delay: 200ms;">
            @foreach($pm['kpi'] ?? [] as $i => $row)
                @php
                    $cardTitle = $row['title'] ?? $row['eyebrow'] ?? '';
                @endphp
                <div class="fade-reveal pm-reveal-kpi-{{ min($i, 3) }} flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-[transform,box-shadow] duration-300 hover:scale-[1.01] hover:shadow-md sm:p-6">
                    @if($cardTitle !== '')
                        <p class="text-sm font-medium text-slate-900">{{ $cardTitle }}</p>
                    @endif
                    <div class="mt-3 text-3xl font-semibold leading-none tracking-tight text-pm-accent sm:text-4xl">{{ $row['value'] }}</div>
                    @if(!empty($row['label']))
                        <p class="mt-2 text-pretty text-sm leading-snug text-slate-500">{{ $row['label'] }}</p>
                    @endif
                    <p class="mt-3 text-pretty text-sm leading-relaxed text-slate-500">{!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $row['why'] ?? '') !!}</p>
                </div>
            @endforeach
        </div>

        <p class="fade-reveal mt-8 text-center text-sm text-slate-500 sm:mt-10 sm:text-base" style="transition-delay: 500ms;">{!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $pm['kpi_section_footer'] ?? 'Это не слайды для инвесторов — это реальная работа системы и клиентов') !!}</p>
    </div>
</section>
