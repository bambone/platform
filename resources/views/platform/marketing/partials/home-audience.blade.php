<section id="dlya-kogo" class="pm-section-anchor border-b border-slate-200 bg-slate-50 py-12 sm:py-16 md:py-20" aria-labelledby="dlya-kogo-heading">
    <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <h2 id="dlya-kogo-heading" class="text-balance text-xl font-bold leading-tight text-slate-900 sm:text-2xl md:text-3xl">Для кого RentBase</h2>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base">Один продукт — разные модели сервиса. Ниже: ваш сегмент и результат, который получаете на платформе.</p>
        <div class="mt-8 grid gap-5 sm:mt-10 sm:grid-cols-2 sm:gap-6 lg:grid-cols-4">
            @foreach([
                ['Прокат техники', 'Принимайте бронирования онлайн и управляйте загрузкой парка без таблиц и переписки.'],
                ['Курсы и мастер-классы', 'Набирайте группы по расписанию и держите заявки и контакты в одном месте.'],
                ['Инструкторы и тренеры', 'Клиенты записываются сами; вы видите слоты, историю и статусы без ручного координации.'],
                ['Услуги по записи', 'Онлайн-запись вместо хаоса в мессенджерах: слоты, напоминания, база клиентов.'],
            ] as [$title, $outcome])
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h3 class="font-semibold text-slate-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-slate-600"><span class="font-medium text-slate-800" aria-hidden="true">→ </span>{{ $outcome }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
