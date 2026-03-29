@php
    $contactUrl = Route::has('platform.contact') ? route('platform.contact') : url('/contact');
    $p = $pm['pricing'] ?? [];
@endphp
<section id="tarify" class="pm-section-anchor border-b border-slate-200 bg-white py-12 sm:py-16 md:py-20" aria-labelledby="tarify-heading">
    <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <h2 id="tarify-heading" class="text-balance text-xl font-bold leading-tight text-slate-900 sm:text-2xl md:text-3xl">Тарифы</h2>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base">Прозрачный вход: разовый запуск и ежемесячная поддержка. Цифры на странице — ориентир; итоговый объём уточняем на коротком созвоне.</p>
        <div class="mt-8 grid gap-5 sm:mt-10 sm:gap-6 lg:grid-cols-3">
            <article class="flex flex-col rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-slate-900">{{ $p['basic']['name'] ?? 'Базовый' }}</h3>
                <p class="mt-2 break-words text-xl font-bold text-slate-900 sm:text-2xl">{{ number_format($p['basic']['launch'] ?? 0, 0, ',', ' ') }} ₽ <span class="text-base font-normal text-slate-600">запуск</span></p>
                <p class="mt-1 text-slate-700">{{ number_format($p['basic']['monthly'] ?? 0, 0, ',', ' ') }} ₽ / месяц</p>
                <ul class="mt-4 flex-1 list-inside list-disc space-y-1 text-sm text-slate-600">
                    @foreach($p['basic']['bullets'] ?? [] as $b)
                        <li>{{ $b }}</li>
                    @endforeach
                </ul>
                <a href="{{ $contactUrl }}" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 sm:w-auto">{{ $pm['cta']['primary'] }}</a>
            </article>
            <article class="flex flex-col rounded-2xl border-2 border-blue-600 bg-white p-5 shadow-md ring-2 ring-blue-100 sm:p-6">
                <h3 class="text-lg font-semibold text-slate-900">{{ $p['custom']['name'] ?? 'Кастомный' }}</h3>
                <p class="mt-2 break-words text-xl font-bold text-slate-900 sm:text-2xl">{{ number_format($p['custom']['launch'] ?? 0, 0, ',', ' ') }} ₽ <span class="text-base font-normal text-slate-600">запуск</span></p>
                <p class="mt-1 text-slate-700">{{ number_format($p['custom']['monthly'] ?? 0, 0, ',', ' ') }} ₽ / месяц</p>
                <ul class="mt-4 flex-1 list-inside list-disc space-y-1 text-sm text-slate-600">
                    @foreach($p['custom']['bullets'] ?? [] as $b)
                        <li>{{ $b }}</li>
                    @endforeach
                </ul>
                <a href="{{ $contactUrl }}" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 sm:w-auto">{{ $pm['cta']['primary'] }}</a>
            </article>
            <article class="flex flex-col rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-slate-900">{{ $p['individual']['name'] ?? 'Индивидуальный' }}</h3>
                <p class="mt-2 text-slate-600">Под интеграции, нестандартные процессы и масштаб.</p>
                <ul class="mt-4 flex-1 list-inside list-disc space-y-1 text-sm text-slate-600">
                    @foreach($p['individual']['bullets'] ?? [] as $b)
                        <li>{{ $b }}</li>
                    @endforeach
                </ul>
                <a href="{{ $contactUrl }}" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-slate-800 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto">{{ $pm['cta']['discuss'] }}</a>
            </article>
        </div>
    </div>
</section>
