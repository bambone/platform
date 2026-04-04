@extends('tenant.layouts.app')

@section('title', 'О нас')

@section('content')
    <section class="pt-24 pb-8 sm:pt-28 sm:pb-10">
        <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-8">
            <h1 class="text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">{{ ($resolvedSeo ?? null)?->h1 ?? 'О прокате' }}</h1>
        </div>
    </section>

    <section class="pb-12 sm:pb-16">
        <div class="mx-auto max-w-3xl space-y-6 px-3 text-sm leading-relaxed text-silver sm:space-y-8 sm:px-4 sm:text-base md:px-8">
            <p class="text-white/90">
                <strong class="text-white">Moto Levins</strong> — прокат мотоциклов Honda на побережье Чёрного моря: Геленджик, Анапа и Новороссийск.
                Мы работаем с 2024 года и делаем упор на понятные цены в объявленных тарифах, выдачу подготовленной техники и сопровождение клиента по дороге.
            </p>
            <p>
                На сайте можно посмотреть <a href="{{ route('motorcycles.index') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">каталог моделей</a>,
                оформить <a href="{{ route('booking.index') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">онлайн-бронирование</a>
                и прочитать <a href="{{ route('terms') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">условия аренды</a>
                (возраст 21+, стаж от 2 лет по категории «А», залог, страхование).
                Точка выдачи согласуется при бронировании; режим и контакты — на странице <a href="{{ route('contacts') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">контакты</a>.
            </p>
            <p>
                Экипировка (шлемы и базовый комплект для выдачи) и ОСАГО на мотоциклах указаны в материалах на главной и в условиях; КАСКО без франшизы доступна как опция при бронировании.
                Если остались вопросы — загляните в <a href="{{ route('faq') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">FAQ</a>.
            </p>
        </div>
    </section>
@endsection
