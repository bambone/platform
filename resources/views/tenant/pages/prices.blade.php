@extends('tenant.layouts.app')

@section('title', 'Цены')

@section('content')
    <section class="pt-24 pb-8 sm:pt-28 sm:pb-10">
        <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-8">
            <h1 class="text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">{{ ($resolvedSeo ?? null)?->h1 ?? 'Цены на мотоциклы' }}</h1>
        </div>
    </section>

    {{-- Таблица/карточки цен по категориям и срокам --}}
    <section class="pb-12 sm:pb-16">
        <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-8">
            <p class="text-sm leading-relaxed text-silver sm:text-base">Слот для ценовой таблицы</p>
        </div>
    </section>
@endsection
