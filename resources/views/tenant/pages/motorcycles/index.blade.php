@extends('tenant.layouts.app')

@section('title', 'Мотоциклы')

@section('content')
    <section class="pt-24 pb-8 sm:pt-28 sm:pb-10">
        <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-8">
            <h1 class="text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">Каталог мотоциклов</h1>
        </div>
    </section>

    {{-- Фильтры: категория (Спорт, Круизер, Эндуро) --}}
    <section class="mx-auto max-w-6xl px-3 sm:px-4 md:px-8">
    </section>

    {{-- Сетка карточек мотоциклов --}}
    <section class="pb-12 sm:pb-16">
        <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-8">
            {{-- @each('components.motorcycle-card', $motorcycles, 'motorcycle') --}}
            <p class="text-sm leading-relaxed text-silver sm:text-base">Слот для карточек мотоциклов</p>
        </div>
    </section>
@endsection
