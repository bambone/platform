@php
    use App\Support\Typography\RussianTypography;
@endphp
<section id="vozmozhnosti" class="pm-section-anchor pm-section-y border-b border-slate-200 bg-slate-50" aria-labelledby="vozmozhnosti-heading">
    <div class="relative z-10 mx-auto max-w-6xl px-4 md:px-6">
        <h2 id="vozmozhnosti-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">
            {{ RussianTypography::tiePrepositionsToNextWord('Не список галочек из рекламы — а то, чем вы правда пользуетесь каждый день') }}
        </h2>
        <p class="fade-reveal pm-section-lead max-w-2xl text-pretty text-base leading-relaxed text-slate-700 sm:text-lg [transition-delay:100ms]">
            {{ RussianTypography::tiePrepositionsToNextWord('Мы не отправляем «соберите сами из кубиков»: заявка, запись и ваши люди сразу понимают друг друга.') }}
        </p>

        <div class="fade-reveal mt-6 grid grid-cols-1 gap-4 md:mt-8 md:grid-cols-2 md:gap-5 lg:grid-cols-3 [transition-delay:150ms]">

            <article class="pm-benefit-card group flex h-full flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-emerald-200/80 hover:bg-emerald-50/25 hover:shadow-xl active:scale-[0.99] sm:p-8">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 shadow-sm ring-1 ring-indigo-100/80 transition-all duration-300 group-hover:scale-110 group-hover:bg-emerald-100 group-hover:text-emerald-600 group-hover:ring-emerald-200/60 motion-reduce:transition-none motion-reduce:group-hover:scale-100">
                    <svg class="h-8 w-8 transition-transform duration-500 group-hover:-rotate-6 motion-reduce:transition-none motion-reduce:group-hover:rotate-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                </div>
                <h3 class="text-lg font-bold leading-snug text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Не перекидывайте между окнами') }}</h3>
                <p class="mt-3 text-pretty text-xs font-semibold uppercase tracking-wide text-slate-500">Запись → человек в базе → статус → SMS или почта</p>
                <p class="mt-3 text-pretty text-sm leading-relaxed text-slate-700 sm:text-base">{{ RussianTypography::tiePrepositionsToNextWord('Клиент нажал кнопку — дальше информация сама доезжает до тех, кому надо.') }}</p>
            </article>

            <article class="pm-benefit-card group flex h-full flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-emerald-200/80 hover:bg-emerald-50/25 hover:shadow-xl active:scale-[0.99] sm:p-8">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 shadow-sm ring-1 ring-indigo-100/80 transition-all duration-300 group-hover:scale-110 group-hover:bg-emerald-100 group-hover:text-emerald-600 group-hover:ring-emerald-200/60 motion-reduce:transition-none motion-reduce:group-hover:scale-100">
                    <svg class="h-8 w-8 transition-transform duration-500 group-hover:-rotate-6 motion-reduce:transition-none motion-reduce:group-hover:rotate-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <h3 class="text-lg font-bold leading-snug text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Слот занят — второй не продадите') }}</h3>
                <p class="mt-3 text-pretty text-xs font-semibold uppercase tracking-wide text-slate-500">Пересечений не будет, если не решите сами</p>
                <p class="mt-3 text-pretty text-sm leading-relaxed text-slate-700 sm:text-base">{{ RussianTypography::tiePrepositionsToNextWord('Система знает, когда мастер или машина свободны, и не даёт «случайно два раза на один час».') }}</p>
            </article>

            <article class="pm-benefit-card group flex h-full flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-emerald-200/80 hover:bg-emerald-50/25 hover:shadow-xl active:scale-[0.99] sm:p-8">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 shadow-sm ring-1 ring-indigo-100/80 transition-all duration-300 group-hover:scale-110 group-hover:bg-emerald-100 group-hover:text-emerald-600 group-hover:ring-emerald-200/60 motion-reduce:transition-none motion-reduce:group-hover:scale-100">
                    <svg class="h-8 w-8 transition-transform duration-500 group-hover:-rotate-6 motion-reduce:transition-none motion-reduce:group-hover:rotate-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 005.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <h3 class="text-lg font-bold leading-snug text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Кто записался и на что — на одном столе') }}</h3>
                <p class="mt-3 text-pretty text-xs font-semibold uppercase tracking-wide text-slate-500">История звонков и заявок рядом с именем</p>
                <p class="mt-3 text-pretty text-sm leading-relaxed text-slate-700 sm:text-base">{{ RussianTypography::tiePrepositionsToNextWord('Админка нужна не «для отчёта акционерам», а чтобы администратор и руководитель видели одно и то же.') }}</p>
            </article>

            <article class="pm-benefit-card group flex h-full flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-emerald-200/80 hover:bg-emerald-50/25 hover:shadow-xl active:scale-[0.99] sm:p-8">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 shadow-sm ring-1 ring-indigo-100/80 transition-all duration-300 group-hover:scale-110 group-hover:bg-emerald-100 group-hover:text-emerald-600 group-hover:ring-emerald-200/60 motion-reduce:transition-none motion-reduce:group-hover:scale-100">
                    <svg class="h-8 w-8 transition-transform duration-500 group-hover:-rotate-6 motion-reduce:transition-none motion-reduce:group-hover:rotate-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                </div>
                <h3 class="text-lg font-bold leading-snug text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Не «сайт на нас», а ваш адрес и логотип') }}</h3>
                <p class="mt-3 text-pretty text-xs font-semibold uppercase tracking-wide text-slate-500">Страницы находят в поиске как обычный сайт</p>
                <p class="mt-3 text-pretty text-sm leading-relaxed text-slate-700 sm:text-base">{{ RussianTypography::tiePrepositionsToNextWord('Клиент по ссылке понимает, что попал к вам — не к абстрактному сервису.') }}</p>
            </article>

            <article class="pm-benefit-card group flex h-full flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-emerald-200/80 hover:bg-emerald-50/25 hover:shadow-xl active:scale-[0.99] sm:p-8">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 shadow-sm ring-1 ring-indigo-100/80 transition-all duration-300 group-hover:scale-110 group-hover:bg-emerald-100 group-hover:text-emerald-600 group-hover:ring-emerald-200/60 motion-reduce:transition-none motion-reduce:group-hover:scale-100">
                    <svg class="h-8 w-8 transition-transform duration-500 group-hover:-rotate-6 motion-reduce:transition-none motion-reduce:group-hover:rotate-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <h3 class="text-lg font-bold leading-snug text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Сезон или реклама — не повод всё «лечь»') }}</h3>
                <p class="mt-3 text-pretty text-xs font-semibold uppercase tracking-wide text-slate-500">Под нагрузкой как в обычный понедельник</p>
                <p class="mt-3 text-pretty text-sm leading-relaxed text-slate-700 sm:text-base">{{ RussianTypography::tiePrepositionsToNextWord('Когда одновременно открывают двадцать человек — тяжёлое уходит в очередь на сервере, интерфейс отзывчивый.') }}</p>
            </article>

            <article class="group flex h-full flex-col items-center justify-center rounded-3xl border-2 border-dashed border-slate-300 bg-white/80 p-7 transition-all duration-300 hover:-translate-y-0.5 hover:border-indigo-400 hover:bg-indigo-50/25 hover:shadow-md active:scale-[0.99] sm:p-8">
                <p class="text-center text-pretty text-sm font-semibold text-slate-600 sm:text-base">{{ RussianTypography::tiePrepositionsToNextWord('Плюс ещё приличный хвост полезного — лучше один раз показать на экране') }}</p>
                <a href="{{ platform_marketing_demo_url() }}" class="mt-4 text-sm font-extrabold text-indigo-600 transition-colors hover:text-indigo-800">Посмотреть демо →</a>
            </article>
        </div>
    </div>
</section>
