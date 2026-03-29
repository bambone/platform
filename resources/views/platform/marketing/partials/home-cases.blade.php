@php
    $casesContactUrl = Route::has('platform.contact') ? route('platform.contact') : url('/contact');
@endphp
<section id="primery" class="pm-section-anchor border-b border-slate-200 bg-slate-50 py-12 sm:py-16 md:py-20" aria-labelledby="primery-heading">
    <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <h2 id="primery-heading" class="text-balance text-xl font-bold leading-tight text-slate-900 sm:text-2xl md:text-3xl">Примеры проектов</h2>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base">Только реальные сайты или честные плейсхолдеры — без вымышленных брендов. <strong class="font-semibold text-slate-800">Ваш проект может быть здесь</strong> после запуска на платформе.</p>
        <div class="mt-8 grid gap-5 sm:mt-10 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3">
            @foreach($pm['cases'] ?? [] as $case)
                <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="aspect-video rounded-lg bg-slate-100 ring-1 ring-slate-200/80"></div>
                    <h3 class="mt-4 font-semibold text-slate-900">{{ $case['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $case['type'] }}</p>
                    @if(!empty($case['url']) && !empty($case['real']))
                        <a href="{{ $case['url'] }}" class="mt-4 text-sm font-medium text-blue-700 hover:text-blue-800" rel="noopener noreferrer" target="_blank">Открыть сайт</a>
                    @else
                        <span class="mt-4 text-sm text-slate-500">Скоро</span>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="mt-10 flex flex-col items-center gap-3 border-t border-slate-200 pt-10 text-center">
            <p class="max-w-xl text-sm text-slate-600">Готовы к такому же публичному сайту и контуру заявок — без фантазийных кейсов в портфолио, зато с рабочей системой.</p>
            <a href="{{ $casesContactUrl }}" class="inline-flex min-h-11 w-full max-w-sm items-center justify-center rounded-lg bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 sm:w-auto">Запустить свой проект</a>
        </div>
    </div>
</section>
