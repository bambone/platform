@extends('platform.layouts.marketing')

@section('title', 'Контакты')

@section('meta_description')
Свяжитесь с командой RentBase: запуск проекта, демо платформы, вопросы по тарифам и кастомному внедрению.
@endsection

@php
    $pm = config('platform_marketing');
    $base = request()->getSchemeAndHttpHost();
    $graph = [
        [
            '@type' => 'ContactPage',
            'name' => 'Контакты — '.($pm['brand_name'] ?? 'RentBase'),
            'url' => $base.'/contact',
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
<div class="mx-auto max-w-3xl px-3 py-10 sm:px-4 md:px-6 md:py-16">
    <h1 class="text-balance text-[clamp(1.5rem,4vw+0.75rem,2.25rem)] font-bold leading-tight text-slate-900 md:text-4xl">Контакты</h1>
    <p class="mt-4 text-lg text-slate-600">Запуск проекта, демо и вопросы по платформе — напишите нам. Мы ответим и предложим следующий шаг.</p>

    <div class="mt-10 grid gap-6">
        <x-platform.marketing.answer-block question="Как заказать демо?">
            <p>Нажмите «Посмотреть демо» в шапке сайта или опишите задачу в письме — покажем сценарии под ваш тип бизнеса.</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block question="Как начать запуск проекта?">
            <p>Кнопка «Запустить проект» ведёт на эту страницу: оставьте контакт и кратко опишите нишу — мы свяжемся для уточнения.</p>
        </x-platform.marketing.answer-block>
    </div>

    @php
        $email = config('mail.from.address', 'hello@rentbase.su');
    @endphp
    <div class="mt-10 rounded-2xl border border-slate-200 bg-slate-50 p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Email</h2>
        <a href="mailto:{{ $email }}" class="mt-2 inline-block text-lg font-medium text-blue-700 hover:text-blue-800">{{ $email }}</a>
        <p class="mt-4 text-sm text-slate-600">В письме укажите сайт или нишу бизнеса и удобный способ связи — ответим в рабочее время.</p>
    </div>
</div>
@endsection
