@extends('platform.layouts.marketing')

@section('title', 'Контакты')

@section('meta_description')
Свяжитесь с RentBase: запуск проекта, демо платформы или обсуждение кастомного внедрения. Короткая форма, ответ в течение рабочего дня.
@endsection

@php
    use App\ContactChannels\PlatformMarketingContactChannelsStore;

    $pm = app(\App\Product\Settings\MarketingContentResolver::class)->resolved();
    $cp = $pm['contact_page'] ?? [];
    $intentConfig = is_array($cp['intents'] ?? null) ? $cp['intents'] : [];
    $intentKeys = array_keys($intentConfig);
    $launch = (string) ($pm['intent']['launch'] ?? 'launch');
    $rawIntent = request('intent');
    $activeIntent = is_string($rawIntent) && in_array($rawIntent, $intentKeys, true)
        ? $rawIntent
        : null;
    $meta = ($activeIntent !== null && isset($intentConfig[$activeIntent])) ? $intentConfig[$activeIntent] : [];
    $pageTitle = (string) ($meta['title'] ?? ($cp['default_title'] ?? 'Контакты'));
    $pageLead = (string) ($meta['lead'] ?? ($cp['default_lead'] ?? ''));
    $base = request()->getSchemeAndHttpHost();
    $graph = [
        [
            '@type' => 'ContactPage',
            'name' => $pageTitle.' — '.($pm['brand_name'] ?? 'RentBase'),
            'url' => $base.'/contact',
            'description' => strip_tags($pageLead),
        ],
        [
            '@type' => 'Organization',
            'name' => $pm['organization']['name'] ?? 'RentBase',
            'url' => $base,
        ],
    ];
    $email = config('mail.from.address', 'hello@rentbase.su');
    $sent = session('platform_contact_sent');
    $formAction = Route::has('platform.contact.store') ? route('platform.contact.store') : url('/contact');
    $pmPreferredOptions = app(\App\ContactChannels\PlatformMarketingContactChannelsStore::class)->publicFormPreferredOptions();
    $pmPreferredDefault = old('preferred_contact_channel', PlatformMarketingContactChannelsStore::PREFERRED_EMAIL);
    $pmChannelMeta = array_map(static fn (array $o): array => [
        'id' => $o['id'],
        'needs_value' => $o['needs_value'],
        'needs_phone' => $o['needs_phone'],
        'value_hint' => (string) ($o['value_hint'] ?? ''),
        'value_placeholder' => (string) ($o['value_placeholder'] ?? ''),
    ], $pmPreferredOptions);
@endphp

@push('jsonld')
    <x-platform.marketing.json-ld :graph="$graph" />
@endpush

@section('content')
<div class="mx-auto max-w-3xl px-3 py-10 sm:px-4 md:px-6 md:py-16">
    @if($sent)
        <div class="rounded-2xl border border-green-200 bg-green-50 p-6 sm:p-8" role="status" data-pm-contact-success="1" data-pm-contact-intent="{{ e(session('platform_contact_intent', '')) }}">
            <h1 class="text-balance text-2xl font-bold text-slate-900 md:text-3xl">{{ $cp['success_title'] ?? 'Заявка отправлена' }}</h1>
            <p class="mt-3 text-base text-slate-700">{{ $cp['success_lead'] ?? '' }}</p>
            <p class="mt-2 text-sm text-slate-600">{{ $cp['success_next'] ?? '' }}</p>
            <p class="mt-6 text-sm text-slate-600">
                Email: <a href="mailto:{{ $email }}" class="font-medium text-blue-700 hover:text-blue-800">{{ $email }}</a>
            </p>
            <p class="mt-6 text-sm text-slate-500">{{ $pm['cta']['pricing_reassurance'] ?? 'Ответим в течение дня' }}</p>
            <a href="{{ url('/') }}" class="mt-8 inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">На главную</a>
        </div>
    @else
        <h1 class="text-balance text-[clamp(1.5rem,4vw+0.75rem,2.25rem)] font-bold leading-tight text-slate-900 md:text-4xl">{{ $pageTitle }}</h1>
        <p class="mt-4 text-lg text-slate-600">{{ $pageLead }}</p>

        @if($activeIntent === (string) ($pm['intent']['demo'] ?? 'demo'))
            <div class="mb-6 mt-4 rounded-xl border border-slate-200 bg-white p-4">
                <p class="mb-2 text-sm font-medium text-slate-900">{{ $cp['demo_outline_title'] ?? 'Что вы увидите на демо:' }}</p>
                <ul class="space-y-1 text-sm text-slate-600">
                    @foreach($cp['demo_outline'] ?? [] as $pt)
                        <li>• {{ $pt }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(!empty($pm['cta']['demo_expectation']) && $activeIntent === (string) ($pm['intent']['demo'] ?? 'demo'))
            <p class="mt-3 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700">{{ $pm['cta']['demo_expectation'] }}</p>
        @endif

        <ul class="mt-6 flex flex-col gap-2 text-sm text-slate-600 sm:flex-row sm:flex-wrap">
            @foreach($cp['expectation_bullets'] ?? [] as $bullet)
                <li class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-pm-accent" aria-hidden="true"></span>
                    {{ $bullet }}
                </li>
            @endforeach
        </ul>

        @php
            $trustContact = $pm['trust_micro']['contact'] ?? [];
        @endphp
        @if(!empty($trustContact) && is_array($trustContact))
            <ul class="mt-4 space-y-1 text-xs text-slate-500">
                @foreach(array_slice($trustContact, 0, 3) as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endif

        <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="mb-2 text-sm font-medium text-slate-900">{{ $cp['after_apply_title'] ?? 'Что будет после заявки:' }}</p>
            <ul class="space-y-1 text-sm text-slate-600">
                @foreach($cp['after_apply_steps'] ?? [] as $step)
                    <li>• {{ $step }}</li>
                @endforeach
            </ul>
        </div>

        <form method="post"
              action="{{ $formAction }}"
              class="mt-10 space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
              data-pm-contact-form="1"
              novalidate>
            @csrf
            <div data-rb-public-field="company_site" class="absolute -left-[9999px] h-px w-px overflow-hidden opacity-0" aria-hidden="true">
                <input type="hidden" name="company_site" value="" autocomplete="off" tabindex="-1">
                @error('company_site')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @foreach(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $utmKey)
                @if(request()->filled($utmKey))
                    <input type="hidden" name="{{ $utmKey }}" value="{{ request($utmKey) }}">
                @endif
            @endforeach

            <div data-rb-public-field="intent" class="rb-public-field-group">
                <label for="pm-contact-intent" class="block text-sm font-medium text-slate-800">Тема обращения</label>
                <x-public.field-hint for="pm-contact-intent">Выберите, с чем пришли: запуск, демо или обсуждение внедрения.</x-public.field-hint>
                <select id="pm-contact-intent" name="intent" aria-describedby="pm-contact-intent-hint"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-pm-accent focus:outline-none focus:ring-1 focus:ring-pm-accent @error('intent') border-red-400 @enderror">
                    @foreach($intentConfig as $key => $row)
                        <option value="{{ $key }}" @selected(old('intent', $activeIntent ?? $launch) === $key)>{{ is_array($row) ? ($row['title'] ?? $key) : $key }}</option>
                    @endforeach
                </select>
                @error('intent')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div data-rb-public-field="name" class="rb-public-field-group">
                <label for="pm-contact-name" class="block text-sm font-medium text-slate-800">Имя <span class="text-red-600">*</span></label>
                <x-public.field-hint for="pm-contact-name">Как к вам обращаться в переписке.</x-public.field-hint>
                <input id="pm-contact-name" name="name" type="text" required maxlength="255" autocomplete="name" value="{{ old('name') }}"
                       aria-describedby="pm-contact-name-hint"
                       class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-pm-accent focus:outline-none focus:ring-1 focus:ring-pm-accent @error('name') border-red-400 @enderror">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div data-rb-public-field="email" class="rb-public-field-group">
                <label for="pm-contact-email" class="block text-sm font-medium text-slate-800">Email <span class="text-red-600">*</span></label>
                <x-public.field-hint for="pm-contact-email">Рабочий адрес: сюда отправим ответ и уточняющие вопросы.</x-public.field-hint>
                <input id="pm-contact-email" name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email') }}"
                       aria-describedby="pm-contact-email-hint"
                       class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-pm-accent focus:outline-none focus:ring-1 focus:ring-pm-accent @error('email') border-red-400 @enderror">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            @if(count($pmPreferredOptions) === 1)
                <div data-rb-public-field="preferred_contact_channel" class="rb-public-field-group">
                    <input type="hidden" name="preferred_contact_channel" value="{{ PlatformMarketingContactChannelsStore::PREFERRED_EMAIL }}">
                    @error('preferred_contact_channel')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @else
                <fieldset data-rb-public-field="preferred_contact_channel" class="rounded-xl border border-slate-200 bg-slate-50/80 rb-public-field-group rb-public-field-group--fieldset">
                    <legend class="text-sm font-semibold text-slate-900">Как удобнее связаться</legend>
                    <p class="mb-4 text-xs text-slate-600">Email обязателен выше. Выберите дополнительный предпочитаемый канал — при необходимости откроются поля ниже.</p>
                    <div class="flex flex-col gap-2">
                        @foreach($pmPreferredOptions as $opt)
                            @php $pid = $opt['id']; @endphp
                            <label for="pm-contact-pref-{{ $pid }}" class="group flex min-h-[3rem] cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 has-[:checked]:border-pm-accent has-[:checked]:ring-1 has-[:checked]:ring-pm-accent sm:px-4">
                                <input type="radio" name="preferred_contact_channel" id="pm-contact-pref-{{ $pid }}" value="{{ $pid }}"
                                       class="mt-1 h-4 w-4 shrink-0 border-slate-300 text-pm-accent focus:ring-pm-accent"
                                       data-pm-pref-radio="1"
                                       @checked($pmPreferredDefault === $pid)>
                                <span class="min-w-0 text-sm leading-snug text-slate-700 group-has-[:checked]:font-medium group-has-[:checked]:text-slate-900">{{ $opt['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('preferred_contact_channel')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </fieldset>
            @endif

            <div id="pm-contact-phone-block" class="rb-public-field-group hidden" data-rb-public-field="phone">
                <label for="pm-contact-phone" class="block text-sm font-medium text-slate-800">Телефон <span class="text-red-600 pm-contact-phone-required hidden">*</span></label>
                <x-public.field-hint for="pm-contact-phone">Международный формат, для РФ можно с +7 или 8.</x-public.field-hint>
                <input id="pm-contact-phone" name="phone" type="tel" maxlength="40" autocomplete="tel" value="{{ old('phone') }}"
                       aria-describedby="pm-contact-phone-hint"
                       class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-pm-accent focus:outline-none focus:ring-1 focus:ring-pm-accent @error('phone') border-red-400 @enderror">
                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div id="pm-contact-pref-value-block" class="rb-public-field-group hidden space-y-1" data-rb-public-field="preferred_contact_value">
                <label for="pm-contact-pref-value" class="block text-sm font-medium text-slate-800">Контакт в выбранном канале <span class="text-red-600">*</span></label>
                <input id="pm-contact-pref-value" name="preferred_contact_value" type="text" maxlength="500" autocomplete="off" value="{{ old('preferred_contact_value') }}"
                       aria-describedby="pm-contact-pref-value-dynamic-hint"
                       class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-pm-accent focus:outline-none focus:ring-1 focus:ring-pm-accent @error('preferred_contact_value') border-red-400 @enderror"
                       placeholder="">
                <p id="pm-contact-pref-value-dynamic-hint" class="rb-public-field-hint text-slate-600" role="note"></p>
                @error('preferred_contact_value')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <p class="text-xs text-slate-500">{{ $cp['form_help'] ?? '' }}</p>
            <script type="application/json" id="pm-contact-channel-meta">@json($pmChannelMeta)</script>

            <div data-rb-public-field="message" class="rb-public-field-group">
                <label for="pm-contact-message" class="block text-sm font-medium text-slate-800">Ниша и задача <span class="text-red-600">*</span></label>
                <x-public.field-hint for="pm-contact-message">Опишите проект шире: ниша (услуги, аренда, продажи, запись, B2B), география или полностью онлайн, как сейчас принимаете заявки и что хотите от платформы — витрина, каталог, слоты и бронирования, CRM, учёт, интеграции, сроки запуска. Не менее 15 символов.</x-public.field-hint>
                <textarea id="pm-contact-message" name="message" required rows="5" minlength="15" maxlength="2000"
                          aria-describedby="pm-contact-message-hint"
                          class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-pm-accent focus:outline-none focus:ring-1 focus:ring-pm-accent @error('message') border-red-400 @enderror"
                          placeholder="Например: сеть фотостудий — онлайн-запись и абонементы; аренда авто по городу — каталог, слоты и заявки в CRM">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div id="pm-contact-submit-overlay" class="pm-contact-submit-overlay" aria-hidden="true" role="status" aria-live="polite">
                <div class="pm-contact-submit-overlay__card">
                    <p class="pm-contact-build__title">Собираем вашу заявку</p>
                    <p class="pm-contact-build__lead">Представьте, что поднимаем страницу вашего сервиса этаж за этажом — так мы передаём её команде RentBase.</p>
                    <div class="pm-contact-build__stage">
                        <div class="pm-contact-build__browser-bar">
                            <span class="pm-contact-build__dot" aria-hidden="true"></span>
                            <span class="pm-contact-build__dot" aria-hidden="true"></span>
                            <span class="pm-contact-build__dot" aria-hidden="true"></span>
                            <span class="pm-contact-build__url" aria-hidden="true"></span>
                        </div>
                        <div class="pm-contact-build__canvas">
                            <div class="pm-contact-build__crane" aria-hidden="true">
                                {{-- Читаемый силуэт башенного крана (не абстрактная «буква F») --}}
                                <svg class="pm-contact-build__crane-svg" viewBox="0 0 72 52" xmlns="http://www.w3.org/2000/svg" focusable="false">
                                    <line x1="52" y1="10" x2="52" y2="48" stroke="#475569" stroke-width="3" stroke-linecap="round"/>
                                    <line x1="52" y1="12" x2="8" y2="12" stroke="#475569" stroke-width="2.5" stroke-linecap="round"/>
                                    <line x1="52" y1="15" x2="69" y2="15" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="52" y1="18" x2="24" y2="12" stroke="#94a3b8" stroke-width="1.3" stroke-linecap="round" opacity="0.9"/>
                                    <rect x="44" y="38" width="14" height="11" rx="2" fill="#cbd5e1"/>
                                    <rect x="46" y="40" width="10" height="6" rx="1" fill="#e2e8f0"/>
                                    <g class="pm-contact-build__crane-load">
                                        <line x1="22" y1="12" x2="22" y2="26" stroke="#64748b" stroke-width="1.6" stroke-linecap="round"/>
                                        <path d="M18.8 26.4a3.2 3.2 0 0 0 6.4 0" fill="none" stroke="#334155" stroke-width="1.7" stroke-linecap="round"/>
                                    </g>
                                </svg>
                            </div>
                            <div class="pm-contact-build__floors" aria-hidden="true">
                                <div class="pm-contact-build__floor"></div>
                                <div class="pm-contact-build__floor"></div>
                                <div class="pm-contact-build__floor"></div>
                                <div class="pm-contact-build__floor"></div>
                            </div>
                            <span class="pm-contact-build__spark" aria-hidden="true"></span>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit"
                    id="pm-contact-submit-btn"
                    class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-pm-accent px-6 py-3 text-base font-bold text-white shadow-premium transition-colors hover:bg-pm-accent-hover sm:w-auto"
                    data-pm-event="contact_submit"
                    data-pm-location="contact_page">
                Отправить
            </button>
            <p class="text-center text-xs text-slate-500 sm:text-left">{{ $pm['cta']['pricing_reassurance'] ?? 'Ответим в течение дня' }}</p>

            @if($errors->any())
                <script type="application/json" id="pm-contact-validation-error-keys">@json($errors->keys())</script>
            @endif
        </form>

        <div class="mt-10 rounded-2xl border border-slate-200 bg-slate-50 p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Напрямую по email</h2>
            <a href="mailto:{{ $email }}" class="mt-2 inline-block text-lg font-medium text-blue-700 hover:text-blue-800">{{ $email }}</a>
            <p class="mt-4 text-sm text-slate-600">Если удобнее сразу описать задачу письмом — укажите нишу, город и желаемые сроки.</p>
        </div>
    @endif
</div>
@endsection
