@extends('tenant.layouts.app')

@section('title', optional($motorcycle)->name ?? 'Мотоцикл')

@section('content')
    <section class="pb-12 pt-24 sm:pb-16 sm:pt-28">
        <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-8">
            {{-- Галерея, название, цена, описание, кнопка заказа --}}
            <p class="text-sm leading-relaxed text-silver sm:text-base">Страница карточки мотоцикла</p>
        </div>
    </section>
@endsection
