<section id="kak-rabotaet" class="pm-section-anchor pm-section-y border-b border-slate-200 bg-white" aria-labelledby="kak-rabotaet-heading">
    <div class="mx-auto max-w-6xl px-4 md:px-6">
        <h2 id="kak-rabotaet-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">Как это работает</h2>
        <p class="fade-reveal mt-5 max-w-2xl text-base leading-relaxed text-slate-700 [transition-delay:100ms] sm:mt-6 sm:text-lg">Всего 4 шага от первого захода клиента до готового процесса в вашей админке.</p>
        
        <ol class="mt-10 grid grid-cols-1 gap-5 sm:mt-12 sm:grid-cols-2 sm:gap-6 lg:grid-cols-4">
            @foreach([
                [
                    'label' => 'Клиент заходит на ваш сайт',
                    'sub' => 'На вашем домене, с вашим брендом и SEO.',
                ],
                [
                    'label' => 'Оставляет заявку или выбирает слот',
                    'sub' => 'Интуитивный интерфейс бронирования без лишних полей.',
                ],
                [
                    'label' => 'Система автоматизирует всё',
                    'sub' => 'Проверяет доступность, создает запись, связывает с CRM и уведомляет.',
                ],
                [
                    'label' => 'Вы управляете бизнесом',
                    'sub' => 'Контролируете статусы и клиентов в едином интерфейсе.',
                ],
            ] as $i => $step)
                <li @class([
                    'fade-reveal relative flex h-full flex-col rounded-2xl border border-slate-200 bg-slate-50 p-6 pt-8 transition-shadow hover:shadow-lg sm:p-8 sm:pt-10',
                    'pm-reveal-how-' . min($i, 3),
                ])>
                    <span class="absolute -top-4 left-6 flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500 text-lg font-black text-white shadow-lg">{{ $i + 1 }}</span>
                    <h3 class="mt-4 text-base font-extrabold text-slate-900">{{ $step['label'] }}</h3>
                    <p class="mt-2 text-base leading-relaxed text-slate-700">{{ $step['sub'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
