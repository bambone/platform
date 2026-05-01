@extends('platform.layouts.marketing')

@section('title', 'FAQ')

@section('meta_description')
Вопросы и ответы о RentBase: кому подойдём, как отличается от сборки сайта на WordPress, срок выхода в онлайн, демо, что будет после формы связи и про смену тарифа потом.
@endsection

@php
    $pm = app(\App\Product\Settings\MarketingContentResolver::class)->resolved();
    $base = platform_marketing_canonical_origin() ?: request()->getSchemeAndHttpHost();
    $faqs = [
        ['Подойдёт ли RentBase моему делу?', 'Если вам важна запись на услугу или бронь «как в прокате», плюс свой сайт и нормальный список клиентов — большинству подходит. Расскажите, чем занимаетесь: если нас не хватит, скажем прямо.'],
        ['Чем это не WordPress?', 'WordPress — классно для блога или витрины, но запись и лиды часто прикручивают костылями из пяти плагинов. RentBase сразу заточен под «человек записался — вы это увидели»: слоты, статусы и контакты уже задуманы связкой.'],
        ['Как быстро выходим в онлайн?', 'Части хватает несколько дней, иногда до двух недель при сложном тексте или авторском дизайне. После короткого звонка назовём примерные даты — без «ну как получится».'],
        ['Обязательно ли нанимать программистов?', 'Нет для обычного кейса: мы подключаем готовую платформу под ваш бренд и домен. Отдельный код нужен только если хотите необычные связи с банками или чужими системами — это обсуждаем отдельно.'],
        ['Можно сначала дёшево и потом дороже оформление?', 'Да. Выходите на простом варианте и позже усиливаете сайт — база людей и записей остаётся та же, без переселения «на новый движок».'],
        ['Что будет после заявки?', 'Ответим деловой почтой или звонком в рабочее время, зададим пару вопросов и предложим посмотреть живой пример — без жёсткого «купи сейчас».'],
        ['Что за демо?', 'Оставляете контакт с пометкой «демо», созваниваемся коротко или пишем и показываем экран так, будто вы уже клиент. Разбираем тарифы простым языком.'],
        ['RentBase — это вообще что?', 'Это не «лэндинг из конструктора», а сервис для тех, кто торгует временем: сайт с записью, календарём и списком клиентов. Типовые ниши — прокат техники, студии, курсы.'],
        ['Сколько стоит подключение?', 'От '.number_format($pm['pricing']['basic']['launch'] ?? 5000, 0, ',', ' ').' ₽ за простой старт; авторский внешний вид от '.number_format($pm['pricing']['custom']['launch'] ?? 20000, 0, ',', ' ').' ₽. Остальное — на странице тарифов.'],
        ['Сколько выйдет именно мне?', 'От того, насколько вы хотите уникальную картинку и тексты. Дадим вилку после пары фраз о задаче.'],
        ['Как платформа растёт?', 'Идеи подкидывают клиенты — кто-то подтверждает «да, нам тоже надо», иногда договариваются о доле разработки; выкатываем и отдаём по заранее оговорённым правилам.'],
    ];
    $faqEntities = [];
    foreach ($faqs as $pair) {
        $faqEntities[] = [
            '@type' => 'Question',
            'name' => $pair[0],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $pair[1],
            ],
        ];
    }
    $graph = [
        [
            '@type' => 'FAQPage',
            'mainEntity' => $faqEntities,
        ],
    ];
@endphp

@push('jsonld')
    <x-platform.marketing.json-ld :graph="$graph" />
@endpush

@section('content')
<div class="mx-auto max-w-3xl px-3 py-10 sm:px-4 md:px-6 md:py-16">
    <h1 class="text-balance text-[clamp(1.5rem,4vw+0.75rem,2.25rem)] font-bold leading-tight text-slate-900 md:text-4xl">Частые вопросы</h1>
    <p class="mt-4 text-slate-600">Короткие ответы, чтобы снять сомнения до контакта. Нужны детали — <a href="{{ platform_marketing_contact_url() }}" class="font-medium text-blue-700 hover:text-blue-800">напишите нам</a>.</p>

    <div class="mt-10 space-y-3">
        @foreach($faqs as $i => [$q, $a])
            <details class="group rounded-xl border border-slate-200 bg-white shadow-sm open:shadow-md" data-pm-faq-index="{{ $i }}">
                <summary class="cursor-pointer list-none px-5 py-4 text-base font-semibold text-slate-900 after:float-right after:text-slate-400 after:content-['+'] open:after:content-['−'] marker:content-none [&::-webkit-details-marker]:hidden">
                    {{ $q }}
                </summary>
                <div class="border-t border-slate-100 px-5 pb-4 pt-2 text-sm leading-relaxed text-slate-600">
                    {{ $a }}
                </div>
            </details>
        @endforeach
    </div>
</div>
@endsection
