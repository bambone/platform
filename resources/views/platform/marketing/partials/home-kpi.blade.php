<section id="statistika" class="pm-section-anchor border-b border-slate-200 bg-white py-12 sm:py-16 md:py-20" aria-labelledby="statistika-heading">
    <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <h2 id="statistika-heading" class="text-balance text-xl font-bold leading-tight text-slate-900 sm:text-2xl md:text-3xl">В цифрах</h2>
        <p class="mt-3 text-sm leading-relaxed text-slate-600 sm:text-base">Каждая метрика — про пользу для вашего бизнеса, а не «красивую статистику ради строки».</p>
        <div class="mt-8 grid gap-6 sm:mt-10 md:grid-cols-3 md:gap-8">
            @foreach($pm['kpi'] ?? [] as $row)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                    <div class="text-3xl font-bold text-blue-700">{{ $row['value'] }}</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ $row['label'] }}</div>
                    <p class="mt-3 text-sm text-slate-600">{{ $row['why'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
