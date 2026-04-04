@extends('tenant.layouts.app')

@section('title', 'Мотоциклы')

@section('content')
    <section class="pt-24 pb-8 sm:pt-28 sm:pb-10">
        <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-8">
            <h1 class="text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">{{ ($resolvedSeo ?? null)?->h1 ?? 'Каталог мотоциклов' }}</h1>
            <p class="mt-4 max-w-3xl text-sm leading-relaxed text-silver sm:text-base">
                Здесь собраны модели Honda, доступные в прокате на побережье Чёрного моря (Геленджик, Анапа, Новороссийск).
                Цены за сутки указаны на карточках; полные условия — в разделе
                <a href="{{ route('terms') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">условия аренды</a>.
                Забронировать даты можно через
                <a href="{{ route('booking.index') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">онлайн-бронирование</a>
                или с главной страницы — там же фильтры по датам и локации.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-3 pb-10 sm:px-4 md:px-8">
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-white/10 bg-carbon/80 p-5 sm:p-6">
                <h2 class="mb-2 text-lg font-bold text-white sm:text-xl">Как работает бронирование</h2>
                <p class="text-sm leading-relaxed text-silver sm:text-base">
                    Выберите модель и перейдёте в поток бронирования: укажите даты, при необходимости дополнения — и отправьте заявку.
                    Менеджер связывается для подтверждения и согласования выдачи (точка выдачи уточняется при брони).
                </p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-carbon/80 p-5 sm:p-6">
                <h2 class="mb-2 text-lg font-bold text-white sm:text-xl">Что обычно входит</h2>
                <p class="text-sm leading-relaxed text-silver sm:text-base">
                    На главной и в условиях указано: базовая экипировка (шлемы и необходимое для выдачи), ОСАГО на технике,
                    прозрачные тарифы за сутки. КАСКО без франшизы — опция при оформлении, не скрытая доплата «по факту».
                </p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-carbon/80 p-5 sm:p-6">
                <h2 class="mb-2 text-lg font-bold text-white sm:text-xl">Кому подойдут модели</h2>
                <p class="text-sm leading-relaxed text-silver sm:text-base">
                    У каждой карточки — краткий сценарий: город, побережье, тур или пассажир.
                    Сверяйте объём двигателя и тип трансмиссии с вашим опытом; требования к возрасту и стажу — в условиях аренды.
                </p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-carbon/80 p-5 sm:p-6">
                <h2 class="mb-2 text-lg font-bold text-white sm:text-xl">Перед бронированием проверьте</h2>
                <p class="text-sm leading-relaxed text-silver sm:text-base">
                    Паспорт и права категории «А» (оригиналы), возраст от 21 года и стаж от 2 лет — как в FAQ и на главной.
                    Уточните залог по классу техники и при необходимости лимит пробега/выезд в другой регион — ответы в
                    <a href="{{ route('faq') }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">разделе вопросов</a>.
                </p>
            </div>
        </div>
    </section>

    <section class="border-t border-white/[0.04] bg-[#0c0c0e] py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-3 sm:px-4 md:px-8">
            <h2 class="mb-8 text-balance text-xl font-bold text-white sm:text-2xl">Модели в каталоге</h2>
            @if($bikes->isEmpty())
                <p class="text-sm text-silver sm:text-base">Сейчас нет моделей в каталоге — загляните позже или напишите в <a href="{{ route('contacts') }}" class="text-moto-amber underline-offset-2 hover:underline">контакты</a>.</p>
            @else
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 md:grid-cols-2 xl:grid-cols-3 xl:gap-8">
                    @foreach ($bikes as $index => $bike)
                        <x-bike-card :bike="$bike" :badge="$badges[$index] ?? null" :use-booking-context="false" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <x-booking-modal />
@endsection
