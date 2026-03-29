@extends('platform.layouts.marketing')

@section('title', 'Прокат мотоциклов')

@section('meta_description')
RentBase для проката мотоциклов: сайт, каталог техники, онлайн-бронирование слотов, заявки и клиенты в одной платформе.
@endsection

@php
    $pm = config('platform_marketing');
    $base = request()->getSchemeAndHttpHost();
    $graph = [
        [
            '@type' => 'Service',
            'name' => 'RentBase для проката мотоциклов',
            'description' => 'Платформа для моторентала: каталог, бронирование, заявки и управление клиентами.',
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
    <h1 class="text-balance text-[clamp(1.5rem,4vw+0.75rem,2.25rem)] font-bold leading-tight text-slate-900 md:text-4xl">Платформа для проката мотоциклов</h1>
    <p class="mt-4 max-w-3xl text-lg text-slate-600">Каталог мотоциклов, календарь доступности, онлайн-заявки и единый кабинет для команды проката.</p>

    <div class="mt-10 grid gap-6 md:grid-cols-2">
        <x-platform.marketing.answer-block question="Подходит ли RentBase моторенталу?">
            <p>Да: публичный сайт с каталогом, слоты и бронирование, учёт заявок и клиентов — типовой контур моторентала.</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Что видит клиент на сайте?">
            <p>Актуальный каталог, условия, выбор дат и отправку заявки без звонков вручную на каждый шаг.</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Где посмотреть живой пример?">
            <p>Один из проектов на платформе — <a href="{{ $pm['cases'][0]['url'] ?? 'https://motolevins.rentbase.su' }}" class="font-medium text-blue-700 hover:text-blue-800" target="_blank" rel="noopener noreferrer">Moto Levins</a> (если ссылка актуальна).</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Как подключить свой прокат?">
            <p><a href="{{ Route::has('platform.contact') ? route('platform.contact') : url('/contact') }}" class="font-medium text-blue-700 hover:text-blue-800">Напишите нам</a> — обсудим запуск и тариф.</p>
        </x-platform.marketing.answer-block>
    </div>
</div>
@endsection
