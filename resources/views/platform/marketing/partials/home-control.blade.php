<section id="control" class="pm-section-anchor border-b border-slate-200 bg-slate-900 py-12 text-white sm:py-16 md:py-20" aria-labelledby="control-heading">
    <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <h2 id="control-heading" class="text-balance text-xl font-bold leading-tight sm:text-2xl md:text-3xl">Вы контролируете весь бизнес в одном месте</h2>
        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-300 sm:text-base">Операционная система для сервисного бизнеса: не разрозненные таблицы и чаты, а единый контур заявок, бронирований и клиентов.</p>
        <p class="mt-3 max-w-2xl text-sm font-medium text-white">Все ключевые процессы бизнеса — в одном интерфейсе.</p>
        <ul class="mt-8 grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
            @foreach(['Заявки', 'Клиенты', 'Загрузка и занятость', 'Статусы сделок', 'История взаимодействий'] as $item)
                <li class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-700 bg-slate-800/50 px-4 py-3 text-sm font-medium">{{ $item }}</li>
            @endforeach
        </ul>
    </div>
</section>
