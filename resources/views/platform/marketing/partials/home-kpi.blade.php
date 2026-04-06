<section id="statistika" class="pm-section-anchor pm-section-y relative overflow-hidden border-b border-slate-200 bg-slate-50" aria-labelledby="statistika-heading">
    <!-- Telemetry dots background -->
    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(#94a3b8 1px, transparent 1px); background-size: 24px 24px;"></div>

    <div class="relative z-10 mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <h2 id="statistika-heading" class="fade-reveal text-balance text-xl font-bold leading-tight text-slate-900 sm:text-2xl md:text-3xl">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $pm['kpi_section_title'] ?? 'Система уже работает в реальном бизнесе') !!}</h2>
        <p class="fade-reveal pm-section-lead max-w-2xl text-pretty text-base leading-relaxed text-slate-600 sm:text-lg [transition-delay:100ms]">{!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $pm['kpi_section_intro'] ?? 'Не демо и не теория — реальные процессы, клиенты и нагрузка') !!}</p>

        <div class="fade-reveal mt-6 grid grid-cols-1 gap-4 sm:mt-8 sm:grid-cols-2 sm:gap-5 lg:grid-cols-4 lg:gap-6 [transition-delay:200ms]">
            @foreach($pm['kpi'] ?? [] as $i => $row)
                @php
                    $cardTitle = $row['title'] ?? $row['eyebrow'] ?? '';
                @endphp
                <div class="fade-reveal pm-reveal-kpi-{{ min($i, 3) }} flex flex-col rounded-2xl bg-gradient-to-b from-white/80 to-slate-100/40 p-5 transition-[transform,box-shadow] duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-900/5 sm:p-6 sm:ring-1 sm:ring-slate-200/60">
                    @if($cardTitle !== '')
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 sm:text-[11px]">{{ $cardTitle }}</p>
                    @endif
                    <div class="mt-1 text-[2rem] font-black leading-[0.95] tracking-tight text-pm-accent sm:mt-2 sm:text-5xl md:text-[2.75rem]">{{ $row['value'] }}</div>
                    @if(!empty($row['label']))
                        <p class="mt-2 max-w-[14rem] text-pretty text-[11px] font-semibold leading-snug text-slate-500 sm:text-xs">{{ $row['label'] }}</p>
                    @endif
                    @if(!empty($row['why']))
                        <p class="mt-3 text-pretty text-xs leading-relaxed text-slate-400">{!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $row['why']) !!}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="fade-reveal mt-6 text-center text-xs text-slate-500 sm:mt-8 sm:text-sm [transition-delay:500ms]">{!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $pm['kpi_section_footer'] ?? 'Это не слайды для инвесторов — это реальная работа системы и клиентов') !!}</p>
    </div>
</section>
