@extends('platform.layouts.marketing')

@section('title', 'FAQ')

@section('meta_description')
Ответы на частые вопросы о RentBase: что это за платформа, для кого она, как работает запись и тарифы, чем отличается от WordPress и самописного сайта.
@endsection

@php
    $pm = config('platform_marketing');
    $base = request()->getSchemeAndHttpHost();
    $faqs = [
        ['Что такое RentBase?', 'Платформа для бизнеса с онлайн-записью и бронированиями: сайт клиента, слоты, заявки, клиенты и админка в одной системе.'],
        ['Для какого бизнеса подходит RentBase?', 'Прокат, курсы, инструкторы, сервисы по записи — везде, где нужны расписание, заявки и учёт клиентов.'],
        ['Чем RentBase отличается от WordPress?', 'Это готовый продукт под сервисные сценарии, а не конструктор сайта: меньше ручной сборки плагинов для записи и CRM-логики.'],
        ['Как работают слоты и онлайн-запись?', 'Вы настраиваете услуги и доступность; клиент выбирает время на сайте, заявка попадает в кабинет со статусами.'],
        ['Сколько стоит запуск?', 'От '.number_format($pm['pricing']['basic']['launch'] ?? 5000, 0, ',', ' ').' ₽ для базового запуска; кастомный дизайн — от '.number_format($pm['pricing']['custom']['launch'] ?? 20000, 0, ',', ' ').' ₽. Подробности на странице тарифов.'],
        ['Как развивается платформа?', 'Через модель идей: предложения клиентов, сбор интереса и взносов, затем реализация функций для участников.'],
    ];
    $faqEntities = [];
    foreach ($faqs as $pair) {
        $faqEntities[] = [
            '@type' => 'Question',
            'name' => $pair[0],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $pair[1],
            ],
        ];
    }
    $graph = [
        [
            '@type' => 'FAQPage',
            'mainEntity' => $faqEntities,
        ],
    ];
@endphp

@push('jsonld')
    <x-platform.marketing.json-ld :graph="$graph" />
@endpush

@section('content')
<div class="mx-auto max-w-3xl px-3 py-10 sm:px-4 md:px-6 md:py-16">
    <h1 class="text-balance text-[clamp(1.5rem,4vw+0.75rem,2.25rem)] font-bold leading-tight text-slate-900 md:text-4xl">Частые вопросы</h1>
    <p class="mt-4 text-slate-600">Короткие ответы для быстрого понимания продукта. За деталями — <a href="{{ Route::has('platform.contact') ? route('platform.contact') : url('/contact') }}" class="font-medium text-blue-700 hover:text-blue-800">контакты</a>.</p>

    <div class="mt-10 space-y-4">
        @foreach($faqs as [$q, $a])
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">{{ $q }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ $a }}</p>
            </article>
        @endforeach
    </div>
</div>
@endsection
