@extends('platform.layouts.marketing')

@section('title', 'Прокат автомобилей')

@section('meta_description')
RentBase для каршеринга и проката авто: сайт, каталог, бронирование, заявки и управление клиентами на одной платформе.
@endsection

@php
    $pm = config('platform_marketing');
    $base = request()->getSchemeAndHttpHost();
    $graph = [
        [
            '@type' => 'Service',
            'name' => 'RentBase для проката автомобилей',
            'description' => 'Платформа для аренды авто: каталог, слоты, заявки и клиентский учёт.',
            'provider' => [
                '@type' => 'Organization',
                'name' => $pm['brand_name'] ?? 'RentBase',
                'url' => $base,
            ],
            'areaServed' => 'RU',
        ],
    ];
@endphp

@push('jsonld')
    <x-platform.marketing.json-ld :graph="$graph" />
@endpush

@section('content')
<div class="mx-auto max-w-6xl px-3 py-10 sm:px-4 md:px-6 md:py-16">
    <h1 class="text-balance text-[clamp(1.5rem,4vw+0.75rem,2.25rem)] font-bold leading-tight text-slate-900 md:text-4xl">Платформа для проката автомобилей</h1>
    <p class="mt-4 max-w-3xl text-lg text-slate-600">Тот же продуктовый контур, что и для моторентала: каталог, расписание, заявки и админка под команду.</p>

    <div class="mt-10 grid gap-6 md:grid-cols-2">
        <x-platform.marketing.answer-block question="Можно ли использовать для каршеринга или классического проката?">
            <p>Да, платформа рассчитана на модель с техникой/услугами, слотами и онлайн-заявками — уточните процесс при <a href="{{ Route::has('platform.contact') ? route('platform.contact') : url('/contact') }}" class="font-medium text-blue-700 hover:text-blue-800">контакте</a>.</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Что входит в запуск?">
            <p>Сайт на вашем домене, настройка услуг и расписания, обучение команды работе в кабинете — детали зависят от тарифа.</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Есть ли интеграции?">
            <p>Базовый контур — внутри платформы; индивидуальные интеграции обсуждаются в рамках тарифа «Индивидуальный».</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Где посмотреть общие возможности?">
            <p>Страница <a href="{{ url('/features') }}" class="font-medium text-blue-700 hover:text-blue-800">«Возможности»</a> и <a href="{{ url('/pricing') }}" class="font-medium text-blue-700 hover:text-blue-800">«Тарифы»</a>.</p>
        </x-platform.marketing.answer-block>
    </div>
</div>
@endsection
