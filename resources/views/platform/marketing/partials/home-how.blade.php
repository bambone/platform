@php
    use App\Support\Typography\RussianTypography;
@endphp
<section id="kak-rabotaet" class="pm-section-anchor pm-section-y border-b border-slate-200 bg-white" aria-labelledby="kak-rabotaet-heading">
    <div class="mx-auto max-w-6xl px-4 md:px-6">
        <h2 id="kak-rabotaet-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">{{ RussianTypography::tiePrepositionsToNextWord('Как это работает') }}</h2>
        <p class="fade-reveal pm-section-lead max-w-2xl text-pretty text-base leading-relaxed text-slate-700 [transition-delay:100ms] sm:text-lg">{{ RussianTypography::tiePrepositionsToNextWord('От «зашёл на страницу» до «понятно, кто записался» — без магии, просто порядок шагов.') }}</p>

        <ol class="pm-how-flow relative mt-6 grid grid-cols-1 gap-4 md:mt-8 md:grid-cols-2 md:gap-5 lg:grid-cols-4">
            @foreach([
                [
                    'label' => 'Человек находит вас в поиске или по прямой ссылке',
                    'sub' => 'Видит нормальный сайт, ваш бренд, понятное предложение.',
                ],
                [
                    'label' => 'Выбирает услугу и время сам',
                    'sub' => 'Без двадцати уточняющих звонков, если базовые поля уже хватает.',
                ],
                [
                    'label' => 'Вы получаете готовую карточку',
                    'sub' => 'Слот становится занятым, нужным людям улетают напоминания.',
                ],
                [
                    'label' => 'Работаете как привыкли — только без бардака',
                    'sub' => 'Звоните, когда нужно ваше слово — а не когда надо просто записать имя.',
                ],
            ] as $i => $step)
                <li @class([
                    'fade-reveal relative flex h-full flex-col rounded-2xl border border-slate-200 bg-slate-50 p-6 pt-8 transition-[transform,box-shadow] duration-300 hover:-translate-y-0.5 hover:border-emerald-200/70 hover:shadow-lg sm:p-7 sm:pt-9',
                    'pm-reveal-how-' . min($i, 3),
                ])>
                    <span class="absolute -top-4 left-6 flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500 text-lg font-black text-white shadow-lg ring-2 ring-emerald-200/90">{{ $i + 1 }}</span>
                    <p class="mt-3 text-[10px] font-bold uppercase tracking-wider text-pm-accent">Шаг {{ $i + 1 }} из 4</p>
                    <h3 class="mt-1 text-base font-extrabold leading-snug text-slate-900">{{ RussianTypography::tiePrepositionsToNextWord($step['label']) }}</h3>
                    <p class="mt-2 text-pretty text-sm leading-relaxed text-slate-600 sm:text-base">{{ RussianTypography::tiePrepositionsToNextWord($step['sub']) }}</p>
                    @if($i < 3)
                        <div class="mt-4 flex items-center gap-2 text-pm-accent lg:hidden" aria-hidden="true">
                            <span class="h-px flex-1 bg-gradient-to-r from-pm-accent/50 to-transparent"></span>
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
</section>
