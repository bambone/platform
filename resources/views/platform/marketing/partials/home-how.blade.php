<section id="kak-rabotaet" class="pm-section-anchor border-b border-slate-200 bg-white py-12 sm:py-16 md:py-20" aria-labelledby="kak-rabotaet-heading">
    <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <h2 id="kak-rabotaet-heading" class="text-balance text-xl font-bold leading-tight text-slate-900 sm:text-2xl md:text-3xl">Как это работает</h2>
        <ol class="mt-8 grid grid-cols-1 gap-5 sm:mt-10 sm:grid-cols-2 sm:gap-6 lg:grid-cols-4">
            @foreach([
                'Создаёте проект под свой бренд и домен.',
                'Настраиваете услуги, технику и расписание.',
                'Открываете онлайн-запись и слоты для клиентов.',
                'Получаете заявки и управляете клиентами в админке.',
            ] as $i => $step)
                <li class="relative rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                    <span class="absolute -top-3 left-4 flex h-8 w-8 items-center justify-center rounded-full bg-blue-700 text-sm font-bold text-white">{{ $i + 1 }}</span>
                    <p class="mt-4 text-sm font-medium text-slate-800">{{ $step }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
