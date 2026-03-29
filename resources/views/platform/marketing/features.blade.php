@extends('platform.layouts.marketing')

@section('title', 'Возможности')

@section('meta_description')
Возможности RentBase: сайт на домене клиента, онлайн-запись и слоты, заявки, клиенты и админка. Платформа для проката, курсов, инструкторов и сервисов по записи.
@endsection

@php
    $pm = config('platform_marketing');
    $base = request()->getSchemeAndHttpHost();
    $graph = [
        [
            '@type' => 'WebPage',
            'name' => 'Возможности — '.($pm['brand_name'] ?? 'RentBase'),
            'url' => $base.'/features',
            'description' => 'Описание возможностей платформы RentBase.',
        ],
        [
            '@type' => 'Organization',
            'name' => $pm['organization']['name'] ?? 'RentBase',
            'url' => $base,
        ],
    ];
@endphp

@push('jsonld')
    <x-platform.marketing.json-ld :graph="$graph" />
@endpush

@section('content')
<div class="mx-auto max-w-6xl px-3 py-10 sm:px-4 md:px-6 md:py-16">
    <h1 class="text-balance text-[clamp(1.5rem,4vw+0.75rem,2.25rem)] font-bold leading-tight text-slate-900 md:text-4xl">Возможности платформы</h1>
    <p class="mt-4 max-w-3xl text-lg text-slate-600">{{ $pm['entity_core'] }}</p>

    <div class="mt-10 grid gap-5 sm:mt-12 sm:grid-cols-2 sm:gap-6">
        <x-platform.marketing.answer-block question="Что такое RentBase в одном предложении?">
            <p>Это платформа для сервисного бизнеса: публичный сайт, запись и бронирования, заявки и клиенты, плюс админка для команды.</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Для каких ниш подходит продукт?">
            <p>Прокат техники, курсы и мастер-классы, инструкторы, любые услуги, где важны слоты и заявки без ручного хаоса.</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Чем это отличается от «просто сайта»?">
            <p>Сайт связан с расписанием, заявками и базой клиентов: посетитель записывается, команда видит статусы и историю в одном месте.</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Есть ли отдельный кабинет для бизнеса?">
            <p>Да — админка для управления каталогом, заявками, клиентами и настройками под ваш тенант.</p>
        </x-platform.marketing.answer-block>
    </div>

    <div class="mt-14">
        <h2 class="text-xl font-bold text-slate-900">Коротко по модулям</h2>
        <ul class="mt-4 list-inside list-disc space-y-2 text-slate-600">
            <li>Публичные страницы и CMS под ваш контент</li>
            <li>Каталог услуг или техники (по теме бизнеса)</li>
            <li>Онлайн-запись и расчёт доступности</li>
            <li>Заявки, статусы, клиенты</li>
        </ul>
    </div>

    <p class="mt-10">
        <a href="{{ url('/pricing') }}" class="font-medium text-blue-700 hover:text-blue-800">Тарифы</a>
        <span class="mx-2 text-slate-300">·</span>
        <a href="{{ Route::has('platform.contact') ? route('platform.contact') : url('/contact') }}" class="font-medium text-blue-700 hover:text-blue-800">Запустить проект</a>
    </p>
</div>
@endsection
