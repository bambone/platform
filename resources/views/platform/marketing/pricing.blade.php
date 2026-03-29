@extends('platform.layouts.marketing')

@section('title', 'Тарифы')

@section('meta_description')
Тарифы RentBase: запуск от 5 000 ₽ (базовый) и от 20 000 ₽ (кастомный дизайн), 2 000 ₽/мес. Индивидуальный — по задаче. Без бесплатного тарифа — продукт для серьёзного бизнеса.
@endsection

@php
    $pm = config('platform_marketing');
    $base = request()->getSchemeAndHttpHost();
    $p = $pm['pricing'] ?? [];
    $graph = [
        [
            '@type' => 'SoftwareApplication',
            'name' => ($pm['brand_name'] ?? 'RentBase').' — тарифы',
            'url' => $base.'/pricing',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'offers' => [
                [
                    '@type' => 'Offer',
                    'name' => $p['basic']['name'] ?? 'Базовый',
                    'price' => (string) ($p['basic']['monthly'] ?? 2000),
                    'priceCurrency' => 'RUB',
                    'description' => 'Запуск '.($p['basic']['launch'] ?? 5000).' ₽, затем ежемесячно',
                ],
                [
                    '@type' => 'Offer',
                    'name' => $p['custom']['name'] ?? 'Кастомный',
                    'price' => (string) ($p['custom']['monthly'] ?? 2000),
                    'priceCurrency' => 'RUB',
                    'description' => 'Запуск '.($p['custom']['launch'] ?? 20000).' ₽, затем ежемесячно',
                ],
            ],
        ],
    ];
@endphp

@push('jsonld')
    <x-platform.marketing.json-ld :graph="$graph" />
@endpush

@section('content')
<div class="mx-auto max-w-6xl px-3 py-10 sm:px-4 md:px-6 md:py-16">
    <h1 class="text-balance text-[clamp(1.5rem,4vw+0.75rem,2.25rem)] font-bold leading-tight text-slate-900 md:text-4xl">Тарифы</h1>
    <p class="mt-4 max-w-3xl text-lg text-slate-600">Прозрачные цены запуска и подписки. Подробности условий фиксируются в договоре при подключении.</p>

    <div class="mt-10 grid gap-5 sm:grid-cols-2 sm:gap-6">
        <x-platform.marketing.answer-block question="Сколько стоит запуск?">
            <p>Базовый тариф — {{ number_format($p['basic']['launch'] ?? 5000, 0, ',', ' ') }} ₽ разово, кастомный дизайн — {{ number_format($p['custom']['launch'] ?? 20000, 0, ',', ' ') }} ₽. Индивидуальный — по смете.</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Что входит в ежемесячный платёж?">
            <p>{{ number_format($p['basic']['monthly'] ?? 2000, 0, ',', ' ') }} ₽/мес за работу платформы, инфраструктуру и обновления в рамках продукта. Точный перечень — в оферте/договоре.</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Почему нет бесплатного плана?">
            <p>Мы держим качество среды и поддержки: платформа ориентирована на реальные компании, а не на массовые экспериментальные аккаунты.</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Что такое кастомный дизайн?">
            <p>Уникальная визуальная подстройка публичного сайта под ваш бренд вместо стартового шаблона, при том же функционале платформы.</p>
        </x-platform.marketing.answer-block>
    </div>

    <div class="mt-14 rounded-2xl border border-slate-200 bg-slate-50 p-6">
        <p class="text-sm text-slate-600">Полная сетка тарифов на главной в секции «Тарифы» или <a href="{{ url('/#tarify') }}" class="font-medium text-blue-700 hover:text-blue-800">перейти к блоку на главной</a>.</p>
    </div>
</div>
@endsection
