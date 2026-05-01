@extends('platform.layouts.marketing')

@section('title', 'Возможности')

@section('meta_description')
RentBase: свой сайт и домен, календарь записи или брони, каталог услуг или техники, заявки от клиентов и простая админка для команды — без связки десяти подписок.
@endsection

@php
    $pm = app(\App\Product\Settings\MarketingContentResolver::class)->resolved();
    $base = platform_marketing_canonical_origin() ?: request()->getSchemeAndHttpHost();
    $graph = [
        [
            '@type' => 'WebPage',
            'name' => 'Возможности — '.($pm['brand_name'] ?? 'RentBase'),
            'url' => $base.'/features',
            'description' => 'Подробности по возможностям: сайт, запись и бронь, клиенты, отзывы, домен и поисковые штуки.',
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
@php
    use App\Support\Typography\RussianTypography;
@endphp
<div class="pm-marketing-features mx-auto max-w-6xl px-3 pb-12 sm:px-4 sm:pb-16 md:px-6 md:pb-20">
    <header class="max-w-3xl">
        <h1 class="scroll-mt-28 text-balance text-[clamp(1.5rem,4vw+0.75rem,2.25rem)] font-bold leading-tight text-slate-900 md:text-4xl">Возможности платформы</h1>
        <p class="mt-4 text-pretty text-lg leading-relaxed text-slate-600">{{ RussianTypography::tiePrepositionsToNextWord((string) ($pm['entity_core'] ?? '')) }}</p>
        <p class="mt-4 text-pretty text-base leading-relaxed text-slate-600">
            {{ RussianTypography::tiePrepositionsToNextWord('Ниже — по полочкам и без рекламного тумана: что умеет сайт, как устроена запись, где сидят заявки и как не потеряться в настройках.') }}
        </p>
    </header>

    <div class="mt-10 grid gap-5 sm:mt-12 sm:grid-cols-2 sm:gap-6">
        <x-platform.marketing.answer-block :question="RussianTypography::tiePrepositionsToNextWord('Что такое RentBase в одном предложении?')">
            <p class="text-pretty">{{ RussianTypography::tiePrepositionsToNextWord('Это цельный сервис: наружу — сайт под ваш бренд, внутри — запись или бронирование, список клиентов и понятная панель для сотрудников, плюс письма/SMS, где это настроите.') }}</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block :question="RussianTypography::tiePrepositionsToNextWord('Для каких ниш подходит продукт?')">
            <p class="text-pretty">{{ RussianTypography::tiePrepositionsToNextWord('Моторентал, каршеринг или прокат авто, детейлинг и простой автосервис по записи, школы вождения и любые курсы, студии и мастерские — везде, где нужно «кто на когда записан» и чтобы это не жило в личке.') }}</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block :question="RussianTypography::tiePrepositionsToNextWord('Чем это отличается от «просто сайта»?')">
            <p class="text-pretty">{{ RussianTypography::tiePrepositionsToNextWord('Страница не «висячая картинка»: расписание знает, что можно продать, контакты попадают к вам в список — без ручного копипаста из форм в Excel.') }}</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block :question="RussianTypography::tiePrepositionsToNextWord('Есть ли отдельный кабинет для бизнеса?')">
            <p class="text-pretty">{{ RussianTypography::tiePrepositionsToNextWord('Да. Свой аккаратный «кабинет», где ваши же люди редактируют страницы, фирменный стиль, каталог или парк техники, расписание, заявки, отзывы и мелочи типа текстов для поисковиков — без необходимости лезть в код.') }}</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block :question="RussianTypography::tiePrepositionsToNextWord('Можно ли свой домен и бренд?')">
            <p class="text-pretty">{{ RussianTypography::tiePrepositionsToNextWord('Да — свой домен вида вашсайт.рф, свой логотип и палитра, картинки на первый экран. Клиенту не видно общую «галерею», он видит вас.') }}</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block :question="RussianTypography::tiePrepositionsToNextWord('Как устроена запись и бронирование?')">
            <p class="text-pretty">{{ RussianTypography::tiePrepositionsToNextWord('Ставится длина приёма, шаг между слотами, отдых между клиентами, «на какой период вперёд открыть календарь». Можно сразу подтверждать автоматически или оставить жёсткий контроль сотруднику. Есть режим каталога мото или авто, есть чистые услуги без техники.') }}</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block :question="RussianTypography::tiePrepositionsToNextWord('Что с заявками и клиентами?')">
            <p class="text-pretty">{{ RussianTypography::tiePrepositionsToNextWord('Каждая форма с сайта — отдельная строка: откуда пришёл человек, что написал, как дозвониться. Всё рядом, не надо собирать по папкам почты и чатов.') }}</p>
        </x-platform.marketing.answer-block>
        <x-platform.marketing.answer-block :question="RussianTypography::tiePrepositionsToNextWord('Есть ли SEO и сопровождение трафика?')">
            <p class="text-pretty">{{ RussianTypography::tiePrepositionsToNextWord('Заголовки и описания для Яндекса/Google, карта сайта, аккуратные адреса страниц, разметка для поиска. Есть и наши поясняющие страницы по видам бизнеса — прокат, авто, запись к мастеру.') }}</p>
        </x-platform.marketing.answer-block>
    </div>

    <div class="mx-auto mt-14 max-w-4xl space-y-6 sm:mt-16 sm:space-y-8">
        <section aria-labelledby="feat-site" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 id="feat-site" class="text-pretty text-lg font-bold leading-snug text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Публичный сайт и контент') }}</h2>
            <ul class="mt-4 list-disc space-y-3 pl-5 text-sm leading-relaxed text-slate-600 marker:text-slate-400 sm:text-base">
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Конструктор страниц — секции, порядок, предпросмотр; темы оформления под разные ниши (в т.ч. витрины с «техническим» и экспертным контентом).'), 'Конструктор страниц') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Медиа и бренд — логотип, первый экран, галереи и файлы в вашем каталоге в облаке; при желании — ярлык «добавить на главный экран телефона» и фирменные акценты.'), 'Медиа и бренд') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Типовые блоки — герой, ленты, формы заявок, отзывы, FAQ, подвал: гибрид готовых секций и вашего текста.'), 'Типовые блоки') !!}</li>
            </ul>
        </section>

        <section aria-labelledby="feat-catalog" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 id="feat-catalog" class="text-pretty text-lg font-bold leading-snug text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Каталог, программы и предложения') }}</h2>
            <ul class="mt-4 list-disc space-y-3 pl-5 text-sm leading-relaxed text-slate-600 marker:text-slate-400 sm:text-base">
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Каталог услуг и/или единиц техники (зависит от темы: прокат, сервис, обучение).'), 'услуг и/или единиц техники') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Программы и пакеты — описание, обложки, CTA, связка с публичными формами.'), 'Программы и пакеты') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Связанные сущности — например, услуга записи, привязанная к объекту флота или к направлению детейлинга.'), 'Связанные сущности') !!}</li>
            </ul>
        </section>

        <section aria-labelledby="feat-sched" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 id="feat-sched" class="text-pretty text-lg font-bold leading-snug text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Расписание, слоты и бронирование') }}</h2>
            <ul class="mt-4 list-disc space-y-3 pl-5 text-sm leading-relaxed text-slate-600 marker:text-slate-400 sm:text-base">
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Настройка длительности, шага сетки, буферов до/после, уведомлений о брони и горизонта планирования.'), 'длительности, шага сетки, буферов') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Расчёт доступности слотов с учётом нагрузки и правил; сценарии «сразу подтверждено» и «ждёт оператора».'), 'доступности слотов') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Интеграция с календарями и целями расписания (цели смен, ресурсы) — для сложного сервиса, а не только «красивая сетка».'), 'календарями и целями') !!}</li>
            </ul>
        </section>

        <section aria-labelledby="feat-crm" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 id="feat-crm" class="text-pretty text-lg font-bold leading-snug text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Заявки, CRM и коммуникации') }}</h2>
            <ul class="mt-4 list-disc space-y-3 pl-5 text-sm leading-relaxed text-slate-600 marker:text-slate-400 sm:text-base">
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Публичные формы (заявка, консультация, запись) с валидацией, UTM, контекстом страницы и согласиями — где это требуется.'), 'Публичные формы') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Входящие запросы из кабинета — с типом услуги, статусом «новая / в работе» и полями под вашу нишу (детейлинг, обучение и т. д.).'), 'Входящие запросы') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Каналы — единообразное использование телефона, мессенджеров и почты в подвале и формах.'), 'Каналы') !!}</li>
            </ul>
        </section>

        <section aria-labelledby="feat-trust" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 id="feat-trust" class="text-pretty text-lg font-bold leading-snug text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Доверие: отзывы, FAQ, юридические сценарии') }}</h2>
            <ul class="mt-4 list-disc space-y-3 pl-5 text-sm leading-relaxed text-slate-600 marker:text-slate-400 sm:text-base">
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Модерация отзывов, публичные и скрытые статусы, витрина на сайте.'), 'отзывов') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('FAQ с привязкой к странице или общий пул вопросов.'), 'FAQ') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Согласия на обработку данных при брони и в формах (включая жёсткий сценарий «сначала ознакомьтесь с офертой») — встроено, без чужих всплывашек.'), 'Согласия') !!}</li>
            </ul>
        </section>

        <section aria-labelledby="feat-seo" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 id="feat-seo" class="text-pretty text-lg font-bold leading-snug text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Маркетинг, поиск и надёжность') }}</h2>
            <ul class="mt-4 list-disc space-y-3 pl-5 text-sm leading-relaxed text-slate-600 marker:text-slate-400 sm:text-base">
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('SEO — мета по страницам, карты сайта, редиректы, аналитические настройки.'), 'SEO') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Подвал сайта — либо автоматический минимальный (контакты и ссылки), либо настраиваемые секции в админке.'), 'Подвал сайта') !!}</li>
                <li class="text-pretty">{!! RussianTypography::wrapPhrase(RussianTypography::tiePrepositionsToNextWord('Надёжность — достаточно места под фото, данные разных клиентов RentBase не смешиваются, письма и напоминания уходят по тем правилам, которые вы включили.'), 'Надёжность') !!}</li>
            </ul>
        </section>

        <section aria-labelledby="feat-summary" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-slate-50/90 p-5 sm:p-6">
            <h2 id="feat-summary" class="text-pretty text-lg font-bold text-slate-900 sm:text-xl">{{ RussianTypography::tiePrepositionsToNextWord('Коротко: что получает владелец бизнеса') }}</h2>
            <ul class="mt-4 list-disc space-y-3 pl-5 text-sm leading-relaxed text-slate-600 marker:text-slate-400 sm:text-base">
                <li class="text-pretty">{{ RussianTypography::tiePrepositionsToNextWord('Одна цепочка: сайт → запись или бронь → заявка у вас на столе — без зоопарка отдельных сервисов.') }}</li>
                <li class="text-pretty">{{ RussianTypography::tiePrepositionsToNextWord('Гибкость ниш: от проката с календарём техники до детейлинга и школ с формами и списками клиентов.') }}</li>
                <li class="text-pretty">{{ RussianTypography::tiePrepositionsToNextWord('Прозрачность для сотрудников: заявки, статусы, напоминания, история в одной панели.') }}</li>
            </ul>
        </section>
    </div>

    <p class="mt-12 text-pretty text-sm text-slate-500">
        {{ RussianTypography::tiePrepositionsToNextWord('Актуальный перечень опций в тарифах — на странице') }}
        <a href="{{ url('/pricing') }}" class="font-medium text-blue-700 hover:text-blue-800">«Тарифы»</a>.
        {{ RussianTypography::tiePrepositionsToNextWord('Подобрать сценарий под вашу нишу — в блоках') }}
        <a href="{{ url('/for-moto-rental') }}" class="font-medium text-blue-700 hover:text-blue-800">прокат мото</a>,
        <a href="{{ url('/for-car-rental') }}" class="font-medium text-blue-700 hover:text-blue-800">прокат авто</a>,
        <a href="{{ url('/for-services') }}" class="font-medium text-blue-700 hover:text-blue-800">сервисы по записи</a>.
    </p>

    <p class="mt-6">
        <a href="{{ url('/pricing') }}" class="font-medium text-blue-700 hover:text-blue-800">Тарифы</a>
        <span class="mx-2 text-slate-300">·</span>
        <a href="{{ platform_marketing_contact_url($pm['intent']['launch'] ?? 'launch') }}" class="font-medium text-blue-700 hover:text-blue-800">Запустить проект</a>
    </p>
</div>
@endsection
