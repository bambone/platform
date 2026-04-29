@php
    $tenant = tenant();
    if ($tenant === null) {
        return;
    }
    $formKey = trim((string) ($data['form_key'] ?? 'expert_lead'));
    $config = \App\Models\FormConfig::findEnabledForTenant((int) $tenant->id, $formKey);

    $heading = trim((string) ($data['heading'] ?? ''));
    $sub = trim((string) ($data['subheading'] ?? ''));
    $headingDisplay = $heading !== '' ? \App\Support\Typography\RussianTypography::tiePrepositionsToNextWord($heading) : '';
    $subDisplay = $sub !== '' ? \App\Support\Typography\RussianTypography::tiePrepositionsToNextWord($sub) : '';
    $trustChips = [];
    $rawChips = $data['trust_chips'] ?? [];
    if (is_array($rawChips)) {
        foreach ($rawChips as $c) {
            $line = is_array($c) ? trim((string) ($c['text'] ?? '')) : trim((string) $c);
            if ($line !== '') {
                $trustChips[] = $line;
            }
        }
    }
    $sectionId = trim((string) ($data['section_id'] ?? 'expert-inquiry')) ?: 'expert-inquiry';
    $stickyLabel = trim((string) ($data['sticky_cta_label'] ?? '')) ?: 'Записаться';
    $programs = \App\Models\TenantServiceProgram::query()
        ->where('tenant_id', (int) $tenant->id)
        ->where('is_visible', true)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get(['id', 'slug', 'title']);
    $successMessage = $config?->success_message ?? 'Спасибо! Заявка отправлена.';
    $endpoint = route('api.tenant.expert-inquiry.store');
    $contactChannelOptions = app(\App\ContactChannels\TenantContactChannelsStore::class)->publicFormPreferredOptions((int) $tenant->id);
    $contactChannelCount = count($contactChannelOptions);
    $themeKey = $tenant->themeKey();
    $fieldKeys = ($config !== null && is_array($config->fields_json)) ? array_keys($config->fields_json) : [];
    // Поля «автошколы» показываем только в expert_auto или если они явно заданы в fields_json тенанта.
    $showDrivingFields = $themeKey !== 'advocate_editorial'
        || in_array('has_own_car', $fieldKeys, true)
        || in_array('transmission', $fieldKeys, true)
        || in_array('has_license', $fieldKeys, true);
    $goalLabel = 'Что хотите улучшить';
    if ($config !== null && is_array($config->fields_json['goal_text'] ?? null)) {
        $goalLabel = trim((string) ($config->fields_json['goal_text']['label'] ?? $goalLabel)) ?: $goalLabel;
    }
    $scheduleHint = $themeKey === 'advocate_editorial'
        ? 'Интервал, когда вам удобно ответить на звонок или сообщение (необязательно).'
        : 'Интервал, когда вам удобно заниматься (необязательно). На телефоне откроется выбор времени.';
    $isContactsPage = ! empty($advocateContactsPage ?? false);
    if ($isContactsPage) {
        $goalLabel = 'Кратко опишите ситуацию';
    }
@endphp
<section id="{{ e($sectionId) }}" class="advocate-expert-lead expert-lead-mega relative min-w-0 scroll-mt-24 @if($isContactsPage) advocate-expert-lead--contacts-page mb-8 sm:mb-10 lg:mb-12 @else mb-14 sm:mb-20 lg:mb-28 @endif">
    {{-- На /contacts — шире и спокойнее, в тон странице «Контакты». --}}
    <div id="expert-inquiry-block" class="expert-lead-mega__shell relative mx-auto w-full min-w-0 overflow-hidden @if($isContactsPage) max-w-[40rem] rounded-2xl border border-[rgba(154,123,79,0.16)] bg-gradient-to-b from-[#fdfcfa] via-[#faf6f0] to-[#f0e8dc] p-6 shadow-[0_24px_56px_-28px_rgba(42,36,28,0.14)] sm:p-8 @else border border-[rgba(28,31,38,0.1)] bg-gradient-to-br from-[#fdfcfa] via-[#faf6f0] to-[#f2ebe2] ring-1 ring-inset ring-[rgba(154,123,79,0.08)] max-w-[min(72rem,100%)] rounded-[1.5rem] p-6 shadow-[0_24px_60px_-28px_rgba(28,31,38,0.18)] sm:rounded-[2rem] sm:p-10 lg:p-12 @endif">
        <div class="relative z-10" data-rb-expert-inquiry-root>
        @if($isContactsPage)
            @if($heading !== '' || $sub !== '')
                <header class="advocate-contact-form-intro text-left">
                    @if($heading !== '')
                        <h2 class="expert-section-title text-balance tracking-tight text-[rgb(24_27_32)] font-serif text-[clamp(1.25rem,2.2vw,1.65rem)] font-semibold leading-snug">{{ $headingDisplay }}</h2>
                    @endif
                    @if($sub !== '')
                        {{-- Та же ширина, что и поля: без узкого max-w — иначе «столбик» текста над широкой формой --}}
                        <p class="mt-2 text-[15px] leading-[1.55] text-[rgb(72_78_86)] sm:mt-2.5 sm:text-[16px] sm:leading-[1.5]">{{ $subDisplay }}</p>
                    @endif
                </header>
            @endif
        @else
            @if($heading !== '')
                <div class="px-1 text-center">
                    <h2 class="expert-section-title text-balance tracking-tight text-[rgb(24_27_32)] text-[clamp(1.55rem,4.5vw,3.1rem)] font-extrabold leading-[1.12] sm:leading-[1.1]">{{ $headingDisplay }}</h2>
                </div>
            @endif
            @if($sub !== '')
                <div class="mt-5 text-center">
                    <p class="mx-auto max-w-2xl text-[16px] leading-[1.65] text-[rgb(55_62_72)] sm:text-[17px] text-pretty font-normal">{{ $subDisplay }}</p>
                </div>
            @endif
        @endif
        @if(count($trustChips) > 0 && ! $isContactsPage)
            <ul class="mt-8 flex flex-wrap justify-center gap-2 sm:gap-3">
                @foreach($trustChips as $chip)
                    <li class="inline-flex rounded-lg border border-[rgba(154,123,79,0.35)] bg-white/90 px-3 py-1.5 text-[11px] font-bold uppercase tracking-widest text-[rgb(72_62_48)] sm:px-4 sm:py-2 sm:text-xs">{{ $chip }}</li>
                @endforeach
            </ul>
        @endif

        <div id="expert-inquiry-alert" data-rb-expert-inquiry-alert class="mt-4 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[15px] leading-snug text-red-900" role="alert" aria-live="assertive"></div>

        <div
            data-rb-expert-inquiry-success
            class="mt-4 hidden rounded-2xl border border-[rgba(154,123,79,0.4)] bg-[linear-gradient(168deg,#f6fffb_0%,#eefcf5_45%,#e2f5ea_100%)] px-6 py-10 text-center shadow-[0_20px_50px_-32px_rgba(22,101,52,0.25)] outline-none sm:px-10"
            tabindex="-1"
            role="status"
            aria-live="polite"
        >
            <div class="mx-auto max-w-xl text-balance">
                <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-600" aria-hidden="true">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <p class="mb-3 text-2xl font-bold tracking-tight text-[rgb(22_25_30)] sm:text-[1.65rem]" data-rb-public-form-success-title>Спасибо!</p>
                <p class="text-[17px] leading-relaxed text-[rgb(52_58_68)]" data-rb-public-form-success-lead></p>
            </div>
        </div>

        <form id="expert-inquiry-form" class="expert-inquiry-form relative @if($isContactsPage) mt-5 space-y-0 @else mt-8 space-y-6 sm:mt-10 sm:space-y-7 @endif" novalidate
              data-expert-inquiry-form
              data-expert-inquiry-main="1"
              data-expert-inquiry-endpoint="{{ e($endpoint) }}"
              data-expert-inquiry-default-success="{{ e($successMessage) }}">
            @csrf
            <script type="application/json" id="expert-inquiry-channel-meta" data-rb-expert-channel-meta>@json($contactChannelOptions)</script>
            <input type="hidden" name="expert_domain" value="legal_services">
            <input type="hidden" name="page_url" value="{{ url()->current() }}">
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label for="expert-inquiry-hp-website-adv">Website</label>
                <input id="expert-inquiry-hp-website-adv" type="text" name="website" tabindex="-1" autocomplete="off" value="">
            </div>

            @if($isContactsPage)
                <div class="advocate-contacts-expert-form-flow w-full space-y-5">
                    <div class="space-y-2">
                        <h3 class="advocate-contact-form-h3">Контакты</h3>
            @endif
            <div class="grid min-w-0 gap-3 sm:gap-4 md:grid-cols-2">
                <div data-rb-public-field="name" class="expert-public-field-wrap min-w-0">
                    <label for="expert-name" class="@if($isContactsPage) mb-1 @else mb-2 @endif block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">Имя <span class="text-moto-amber">*</span></label>
                    <input id="expert-name" name="name" type="text" required autocomplete="name" maxlength="255"
                           class="expert-form-input w-full rounded-lg border border-[rgba(28,31,38,0.22)] bg-white px-3 py-2 text-[15px] text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(95_102_115)] focus:border-moto-amber/55 focus:ring-1 focus:ring-moto-amber/25 @if($isContactsPage) min-h-[2.75rem] @else min-h-[3.5rem] px-4 py-3 text-[17px] rounded-xl focus:ring-2 @endif">
                </div>
                <div data-rb-public-field="phone" class="expert-public-field-wrap min-w-0">
                    <label for="expert-phone" class="@if($isContactsPage) mb-1 @else mb-2 @endif block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">Телефон <span class="text-moto-amber expert-phone-required-star">*</span></label>
                    {{-- data-rb-intl-phone: автоподключение маски из tenant-intl-phone.js (как booking-modal: handleInput + hint) --}}
                    <input id="expert-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel"
                           data-rb-expert-phone
                           data-rb-intl-phone="1"
                           aria-describedby="expert-phone-hint"
                           maxlength="28"
                           placeholder="+7 (999) 123-45-67"
                           class="expert-form-input w-full rounded-lg border border-[rgba(28,31,38,0.22)] bg-white px-3 py-2 text-[15px] text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(95_102_115)] focus:border-moto-amber/55 focus:ring-1 focus:ring-moto-amber/25 @if($isContactsPage) min-h-[2.75rem] @else min-h-[3.5rem] px-4 py-3 text-[17px] rounded-xl focus:ring-2 @endif">
                    <p id="expert-phone-hint" data-rb-expert-phone-hint class="@if($isContactsPage) mt-1 @else mt-2 @endif text-[12px] leading-snug text-[rgb(65_72_82)] sm:text-[13px]"></p>
                </div>
            </div>
            @if($isContactsPage)
                    </div>
                    <div class="space-y-2">
                        <h3 class="advocate-contact-form-h3">Как удобнее связаться</h3>
            @endif
            <div data-rb-public-field="preferred_contact_channel" class="expert-public-field-wrap min-w-0">
                @if(! $isContactsPage)
                <span class="mb-2 block text-[13px] font-semibold tracking-wide text-[rgb(28_31_32)]">Как с вами связаться?</span>
                @endif
                @if ($contactChannelCount <= 1)
                    @php $onlyId = $contactChannelOptions[0]['id'] ?? 'phone'; @endphp
                    <input type="hidden" name="preferred_contact_channel" value="{{ e($onlyId) }}">
                    <p class="text-[14px] leading-snug text-[rgb(65_72_82)]">Ответим по контактам, указанным в заявке (телефон обязателен).</p>
                @else
                    <div class="expert-channel-grid @if($isContactsPage) flex flex-wrap gap-2 sm:gap-2.5 @else flex flex-col gap-2.5 sm:gap-3 @endif">
                        @foreach ($contactChannelOptions as $idx => $opt)
                            @php $cid = $opt['id'] ?? ''; @endphp
                            @if ($cid !== '')
                                <label class="expert-channel-option @if($isContactsPage) relative flex min-h-[2.875rem] min-w-0 flex-1 cursor-pointer items-center justify-center rounded-xl border border-[rgba(154,123,79,0.2)] bg-[rgba(255,250,242,0.95)] px-3 py-2.5 text-center text-[14px] font-semibold leading-snug text-[rgb(42_46_54)] shadow-[0_1px_2px_rgba(42,36,28,0.06)] transition-all hover:border-[rgba(154,123,79,0.35)] has-[:checked]:border-transparent has-[:checked]:bg-[rgb(154_123_79)] has-[:checked]:text-white has-[:checked]:shadow-[0_8px_20px_-8px_rgba(95,72,42,0.45)] focus-within:ring-2 focus-within:ring-[rgba(154,123,79,0.35)] @else flex cursor-pointer items-start gap-3 rounded-xl border border-[rgba(28,31,38,0.14)] bg-white p-3.5 shadow-sm transition-colors has-[:checked]:border-[rgba(154,123,79,0.55)] has-[:checked]:bg-[#fffefb] sm:p-4 @endif">
                                    <input type="radio" name="preferred_contact_channel" value="{{ e($cid) }}"
                                           class="expert-channel-radio @if($isContactsPage) sr-only @else mt-0.5 h-4 w-4 @endif shrink-0 border-[rgba(28,31,38,0.35)] text-moto-amber focus:ring-2 focus:ring-moto-amber/35"
                                           @checked($idx === 0)>
                                    <span class="pointer-events-none @if($isContactsPage) min-w-0 @else min-w-0 text-[15px] font-medium leading-snug text-[rgb(28_31_32)] sm:text-[16px] @endif">{{ $opt['label'] ?? $cid }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <div id="expert-pref-value-wrap" data-rb-expert-pref-wrap data-rb-public-field="preferred_contact_value" class="expert-public-field-wrap hidden min-w-0">
                <label for="expert-pref-value" data-rb-expert-pref-label class="@if($isContactsPage) mb-1 @else mb-2 @endif block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">Контакт для связи</label>
                <input id="expert-pref-value" data-rb-expert-pref-input name="preferred_contact_value" type="text" maxlength="500"
                       class="expert-form-input w-full rounded-lg border border-[rgba(28,31,38,0.22)] bg-white px-3 py-2 text-[15px] text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(95_102_115)] focus:border-moto-amber/55 focus:ring-1 focus:ring-moto-amber/25 @if($isContactsPage) min-h-[2.75rem] @else min-h-[3.5rem] px-4 py-3 text-[17px] rounded-xl focus:ring-2 @endif">
                <p id="expert-pref-value-hint" data-rb-expert-pref-hint class="@if($isContactsPage) mt-1 @else mt-2 @endif hidden text-[13px] leading-snug text-[rgb(65_72_82)] sm:text-[14px]"></p>
            </div>
            @if($isContactsPage)
                    </div>
                    <div class="space-y-2">
                        <h3 id="expert-goal-section-{{ $sectionId }}" class="advocate-contact-form-h3">Суть ситуации</h3>
            @endif
            <div data-rb-public-field="goal_text" class="expert-public-field-wrap min-w-0">
                <label for="expert-goal" class="@if($isContactsPage) sr-only @else mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)] @endif">{{ $goalLabel }} <span class="text-moto-amber">*</span></label>
                <textarea id="expert-goal" name="goal_text" required rows="{{ $isContactsPage ? '4' : '4' }}" maxlength="2000"
                          @if($isContactsPage) aria-labelledby="expert-goal-section-{{ $sectionId }}" @endif
                          @if($isContactsPage) placeholder="Кратко: обстоятельства, что уже сделано, какой результат нужен" @endif
                          class="expert-form-input w-full rounded-lg border border-[rgba(28,31,38,0.22)] bg-white px-3 py-2 text-[15px] leading-relaxed text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(95_102_115)] focus:border-moto-amber/55 focus:ring-1 focus:ring-moto-amber/25 @if($isContactsPage) min-h-[7.5rem] resize-y @else min-h-[8rem] rounded-xl px-4 py-3 text-[17px] focus:ring-2 @endif"></textarea>
            </div>
            @if($isContactsPage)
                    </div>
            @endif

            @if($programs->isNotEmpty() && ! $isContactsPage)
                <div data-rb-public-field="program_slug" class="expert-public-field-wrap min-w-0">
                    <label for="expert-program" class="mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">Программа (необязательно)</label>
                    <select id="expert-program" data-rb-expert-program name="program_slug"
                            class="expert-form-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20 appearance-none">
                        <option value="" class="bg-white text-[rgb(24_27_32)]">—</option>
                        @foreach($programs as $p)
                            <option value="{{ $p->slug }}" data-rb-program-db-id="{{ $p->id }}" class="bg-white text-[rgb(24_27_32)]">{{ $p->title }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if($isContactsPage)
                    <div class="space-y-2">
                        <h3 class="advocate-contact-form-h3">Когда удобно ответить</h3>
                        <div data-rb-public-field="preferred_schedule" class="expert-public-field-wrap min-w-0">
                            <label for="expert-schedule-simple" class="sr-only">Удобное время для связи <span class="font-normal">(необязательно)</span></label>
                            <input
                                id="expert-schedule-simple"
                                name="preferred_schedule"
                                type="text"
                                data-rb-expert-schedule-simple
                                maxlength="120"
                                autocomplete="off"
                                placeholder="Например: будни после 18:00 или 18:00 – 21:00"
                                class="expert-form-input w-full min-h-[2.75rem] rounded-lg border border-[rgba(28,31,38,0.22)] bg-white px-3 py-2 text-[15px] text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(140_145_155)] focus:border-moto-amber/55 focus:ring-1 focus:ring-moto-amber/25"
                            >
                            <p class="mt-1.5 text-[12px] leading-snug text-[rgb(90_96_106)] sm:text-[13px]">Одна строка: интервал или кратко, когда можно перезвонить или написать.</p>
                        </div>
                    </div>
                </div>
            @else
            {{-- Сетка 3×2 на md+: заголовки в одной строке, подсказка только слева, поля в одной строке — без «пляски» колонок. --}}
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:gap-5 md:grid-cols-2 md:grid-rows-[auto_auto_auto] md:items-start md:gap-x-5 md:gap-y-3">
                <div data-rb-public-field="preferred_schedule" class="expert-public-field-wrap min-w-0 md:contents">
                    <span id="expert-schedule-legend" data-expert-schedule-activator tabindex="0" role="button" class="order-1 mb-2 block cursor-pointer select-none rounded-md text-sm font-semibold tracking-wide text-[rgb(28_31_32)] underline-offset-2 transition hover:text-[rgb(95_72_42)] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-moto-amber/45 md:order-none md:col-start-1 md:row-start-1 md:mb-0">Удобное время</span>
                    <p id="expert-schedule-desc" data-expert-schedule-activator tabindex="0" role="button" class="order-2 mb-3 cursor-pointer select-none rounded-md text-[14px] leading-snug text-[rgb(65_72_82)] sm:text-[15px] md:order-none md:col-start-1 md:row-start-2 md:mb-0">{{ $scheduleHint }}</p>
                    <div class="order-3 grid min-w-0 gap-3 rounded-xl border border-[rgba(28,31,38,0.14)] bg-white/70 p-2 shadow-sm transition focus-within:border-moto-amber/50 focus-within:bg-white focus-within:shadow-md focus-within:ring-2 focus-within:ring-moto-amber/25 sm:grid-cols-2 sm:gap-4 sm:p-3 md:order-none md:col-start-1 md:row-start-3" data-expert-schedule-time-group role="group" aria-labelledby="expert-schedule-legend" aria-describedby="expert-schedule-desc">
                        <div class="min-w-0">
                            <label for="expert-schedule-from" class="mb-1.5 block text-[14px] font-medium tracking-wide text-[rgb(28_31_32)]">С</label>
                            <input id="expert-schedule-from" data-rb-expert-schedule-from type="time" min="07:00" max="22:00" step="300" autocomplete="off"
                                   class="expert-form-input expert-schedule-time-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors focus:border-moto-amber/60 focus:ring-2 focus:ring-moto-amber/30 [color-scheme:light]">
                        </div>
                        <div class="min-w-0">
                            <label for="expert-schedule-to" class="mb-1.5 block text-[14px] font-medium tracking-wide text-[rgb(28_31_32)]">До</label>
                            <input id="expert-schedule-to" data-rb-expert-schedule-to type="time" min="07:00" max="22:00" step="300" autocomplete="off"
                                   class="expert-form-input expert-schedule-time-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors focus:border-moto-amber/60 focus:ring-2 focus:ring-moto-amber/30 [color-scheme:light]">
                        </div>
                    </div>
                </div>
                <div class="hidden md:block md:col-start-2 md:row-start-2" aria-hidden="true"></div>
                <div data-rb-public-field="district" class="expert-public-field-wrap min-w-0 md:contents">
                    <label for="expert-district" class="order-4 mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)] md:order-none md:col-start-2 md:row-start-1 md:mb-0">Район / город</label>
                    <input id="expert-district" name="district" type="text" maxlength="255"
                           class="expert-form-input order-5 w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(95_102_115)] focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20 md:order-none md:col-start-2 md:row-start-3 md:self-stretch">
                </div>
            </div>
            <input type="hidden" name="preferred_schedule" id="expert-schedule-value" data-rb-expert-schedule-value value="">
            @endif

            @if($showDrivingFields)
            <div class="grid min-w-0 gap-4 sm:grid-cols-2 md:grid-cols-3 sm:gap-5">
                <div data-rb-public-field="has_own_car" class="expert-public-field-wrap min-w-0">
                    <label for="expert-car" class="mb-2 block text-[13px] font-semibold tracking-wide text-[rgb(28_31_32)]">Свой авто</label>
                    <select id="expert-car" name="has_own_car" class="expert-form-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none appearance-none focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20">
                        <option value="" class="bg-white text-[rgb(24_27_32)]">Не указано</option>
                        <option value="yes" class="bg-white text-[rgb(24_27_32)]">Да</option>
                        <option value="no" class="bg-white text-[rgb(24_27_32)]">Нет</option>
                    </select>
                </div>
                <div data-rb-public-field="transmission" class="expert-public-field-wrap min-w-0">
                    <label for="expert-trans" class="mb-2 block text-[13px] font-semibold tracking-wide text-[rgb(28_31_32)]">Коробка передач</label>
                    <select id="expert-trans" name="transmission" class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-stone-300/80 bg-white px-4 py-3 text-[14px] text-stone-900 outline-none appearance-none focus:border-moto-amber/55">
                        <option value="">Не указано</option>
                        <option value="manual">Механика (МКПП)</option>
                        <option value="automatic">Автомат (АКПП)</option>
                        <option value="robot">Робот / вариатор (уточню в комментарии)</option>
                    </select>
                </div>
                <div data-rb-public-field="has_license" class="expert-public-field-wrap min-w-0">
                    <label for="expert-license" class="mb-2 block text-[13px] font-semibold tracking-wide text-[rgb(28_31_32)]">Есть права</label>
                    <select id="expert-license" name="has_license" class="expert-form-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none appearance-none focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20">
                        <option value="" class="bg-white text-[rgb(24_27_32)]">Не указано</option>
                        <option value="yes" class="bg-white text-[rgb(24_27_32)]">Да</option>
                        <option value="no" class="bg-white text-[rgb(24_27_32)]">Нет</option>
                    </select>
                </div>
            </div>
            @endif

            @if($isContactsPage)
                <p class="mt-6 w-full text-[13px] leading-relaxed text-[rgb(90_96_106)] sm:text-[14px]">
                    После получения обращения я ознакомлюсь с описанием и свяжусь с вами удобным способом. Если вопрос срочный — лучше сразу позвонить.
                </p>
            @else
            <div data-rb-public-field="comment" class="expert-public-field-wrap min-w-0">
                <label for="expert-comment" class="mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">Комментарий</label>
                <textarea id="expert-comment" name="comment" rows="3" maxlength="2000"
                          class="expert-form-input w-full min-h-[5.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] leading-relaxed text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(95_102_115)] focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20"></textarea>
            </div>
            @endif

            <div class="@if($isContactsPage) mt-5 w-full text-left @else mt-8 sm:mt-10 text-center @endif">
                <button type="submit" id="expert-inquiry-submit" data-rb-expert-inquiry-submit class="advocate-contact-form-submit tenant-btn-primary inline-flex w-full items-center justify-center rounded-full px-10 font-semibold shadow-[0_16px_36px_-14px_rgba(95,72,42,0.5)] transition hover:brightness-[1.03] @if($isContactsPage) min-h-[3.5rem] text-[16px] @else min-h-[3.75rem] text-[18px] sm:min-h-[4rem] @endif">
                    @if($isContactsPage)
                        Отправить обращение
                    @else
                        Отправить заявку
                    @endif
                </button>
            </div>
        </form>
        </div>
    </div>
</section>

@once('expert-sticky-bar')
    <div id="expert-sticky-cta" class="expert-sticky-cta" data-target="{{ e($sectionId) }}" aria-hidden="false">
        <div class="expert-sticky-cta__inner">
            <a href="#{{ e($sectionId) }}" class="expert-sticky-cta__btn tenant-btn-primary flex w-full justify-center rounded-xl py-3 text-[15px] font-bold shadow-md shadow-black/30 min-h-0">{{ e($stickyLabel) }}</a>
        </div>
    </div>
@endonce

@once('expert-sticky-bar-script')
        <script>
            (function () {
                const bar = document.getElementById('expert-sticky-cta');
                if (!bar || bar.dataset.bound === '1') return;
                bar.dataset.bound = '1';
                const targetId = bar.getAttribute('data-target') || 'expert-inquiry';
                const target = document.getElementById(targetId);
                const form = document.querySelector('form[data-expert-inquiry-main]');
                let hiddenByFocus = false;
                let hiddenByIntersect = false;

                function apply() {
                    const hide = hiddenByFocus || hiddenByIntersect;
                    bar.classList.toggle('is-hidden', hide);
                    bar.setAttribute('aria-hidden', hide ? 'true' : 'false');
                }

                if (target && 'IntersectionObserver' in window) {
                    const io = new IntersectionObserver(
                        (entries) => {
                            hiddenByIntersect = entries.some((e) => e.isIntersecting && e.intersectionRatio > 0.15);
                            apply();
                        },
                        { root: null, threshold: [0, 0.15, 0.35] }
                    );
                    io.observe(target);
                }

                if (form) {
                    form.addEventListener('focusin', () => {
                        hiddenByFocus = true;
                        apply();
                    });
                    form.addEventListener('focusout', () => {
                        setTimeout(() => {
                            if (!form.contains(document.activeElement)) {
                                hiddenByFocus = false;
                                apply();
                            }
                        }, 50);
                    });
                }

                const mq = window.matchMedia('(min-width: 1024px)');
                function onMq() {
                    if (mq.matches) {
                        bar.style.display = 'none';
                    } else {
                        bar.style.display = '';
                        apply();
                    }
                }
                mq.addEventListener('change', onMq);
                onMq();
                hiddenByIntersect = false;
                hiddenByFocus = false;
                apply();
            })();
        </script>
@endonce
