@php
    /** @var \App\Models\PageSection $section */
    /** @var array $data */
    $tenant = tenant();
    if ($tenant === null) {
        return;
    }
    $enabled = ($data['enabled'] ?? true) !== false;
    if (! $enabled) {
        return;
    }
    $variant = $variant ?? 'default';
    $heading = trim((string) ($data['heading'] ?? ''));
    $sub = trim((string) ($data['subheading'] ?? ''));
    $submitLabel = trim((string) ($data['submit_label'] ?? '')) ?: 'Отправить сообщение';
    $expectationNote = trim((string) ($data['expectation_note'] ?? ''));
    $messageLabel = trim((string) ($data['message_label'] ?? '')) ?: 'Сообщение';
    $successMessage = trim((string) ($data['success_message'] ?? ''));
    $sectionId = trim((string) ($data['section_id'] ?? 'contact-inquiry')) ?: 'contact-inquiry';
    $showEmail = (bool) ($data['show_email'] ?? true);
    $showPreferred = (bool) ($data['show_preferred_channel'] ?? true);
    $consentEnabled = (bool) ($data['consent_enabled'] ?? false);
    $consentLabel = trim((string) ($data['consent_label'] ?? '')) ?: 'Я согласен(на) на обработку персональных данных.';
    $endpoint = route('api.tenant.contact-inquiry.store');
    $contactChannelOptions = app(\App\ContactChannels\TenantContactChannelsStore::class)->publicFormPreferredOptions((int) $tenant->id);
    $contactChannelCount = count($contactChannelOptions);
    $fieldErrorClass = match ($variant) {
        'advocate' => 'text-red-700',
        'expert' => 'text-red-400',
        default => 'text-red-400',
    };
    $alertClass = match ($variant) {
        'advocate' => 'rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900',
        'expert' => 'rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200',
        default => 'rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200',
    };
    $successClass = match ($variant) {
        'advocate' => 'rounded-2xl border border-[rgba(154,123,79,0.28)] bg-[linear-gradient(168deg,#fdfcfa_0%,#f3ebe0_48%,#ebe1d4_100%)] px-6 py-8 text-center text-[17px] font-medium leading-relaxed text-[rgb(42_46_54)] shadow-[0_18px_48px_-28px_rgba(42,36,28,0.25)] outline-none',
        'expert' => 'rounded-[1.5rem] border border-white/[0.08] bg-gradient-to-br from-[#12141c] to-[#0a0c12] px-6 py-10 text-center text-[17px] font-medium leading-relaxed text-white/90 sm:px-10 outline-none',
        default => 'rounded-xl border border-white/10 bg-white/[0.04] px-6 py-6 text-center text-[15px] sm:text-base font-medium leading-relaxed text-white/90 outline-none',
    };
    $shellClass = match ($variant) {
        'advocate' => 'relative rounded-[2rem] border border-[rgba(154,123,79,0.28)] bg-[linear-gradient(168deg,#fdfcfa_0%,#f3ebe0_48%,#ebe1d4_100%)] p-6 shadow-[0_24px_70px_-34px_rgba(28,31,38,0.16)] sm:p-9',
        'expert' => 'relative overflow-hidden rounded-[1.5rem] border border-white/[0.08] bg-gradient-to-br from-[#0c0f17] to-[#050608] p-5 shadow-[0_28px_64px_-20px_rgba(0,0,0,0.72)] ring-1 ring-inset ring-white/[0.04] sm:rounded-[2rem] sm:p-10 lg:p-12',
        default => 'relative rounded-xl border border-white/10 bg-white/[0.03] p-5 sm:p-6',
    };
    $labelClass = match ($variant) {
        'advocate' => 'mb-2 block text-sm font-semibold tracking-wide text-[rgb(22_25_30)]',
        'expert' => 'mb-2 block text-sm font-semibold tracking-wide text-white/90',
        default => 'mb-2 block text-sm font-semibold tracking-wide text-white/90',
    };
    $inputClass = match ($variant) {
        'advocate' => 'w-full min-h-[3.25rem] rounded-xl border border-[rgba(28,31,38,0.12)] bg-white px-4 py-3 text-[15px] text-[rgb(22_25_30)] outline-none transition placeholder:text-[rgb(120_128_140)] focus:border-[rgba(154,123,79,0.55)] focus:ring-2 focus:ring-[rgba(154,123,79,0.2)]',
        'expert' => 'w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition focus:border-moto-amber/50 focus:bg-white/[0.04]',
        default => 'w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition focus:border-moto-amber/50 focus:bg-white/[0.04]',
    };
    $radioLabelClass = match ($variant) {
        'advocate' => 'flex cursor-pointer items-start gap-3 rounded-xl border border-[rgba(28,31,38,0.1)] bg-white p-3.5 transition-colors has-[:checked]:border-[rgba(154,123,79,0.45)] has-[:checked]:bg-[rgba(255,252,247,0.95)] sm:p-4',
        'expert' => 'flex cursor-pointer items-start gap-3 rounded-xl border border-white/[0.08] bg-white/[0.02] p-3.5 transition-colors has-[:checked]:border-moto-amber/45 has-[:checked]:bg-white/[0.04] sm:p-4',
        default => 'flex cursor-pointer items-start gap-3 rounded-xl border border-white/[0.08] bg-white/[0.02] p-3.5 transition-colors has-[:checked]:border-moto-amber/45 has-[:checked]:bg-white/[0.04] sm:p-4',
    };
    $btnClass = match ($variant) {
        'advocate' => 'inline-flex min-h-[3.25rem] w-full items-center justify-center rounded-full bg-[rgb(154_123_79)] px-8 text-[15px] font-semibold text-white shadow-[0_14px_32px_-12px_rgba(95,72,42,0.55)] transition hover:bg-[rgb(130_103_68)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgba(154,123,79,0.65)] sm:w-auto',
        'expert' => 'tenant-btn-primary inline-flex min-h-[3.5rem] w-full items-center justify-center rounded-xl px-10 text-[16px] font-bold shadow-2xl transition-transform hover:scale-[1.02] sm:w-auto',
        default => 'tenant-btn-primary inline-flex min-h-[3.25rem] w-full items-center justify-center rounded-xl px-8 text-[15px] font-bold shadow-lg transition sm:w-auto',
    };
    $successTitleClass = match ($variant) {
        'advocate' => 'mb-3 text-2xl font-bold tracking-tight text-[rgb(22_25_30)] sm:text-[1.65rem]',
        'expert' => 'mb-3 text-2xl font-bold tracking-tight text-white sm:text-[1.65rem]',
        default => 'mb-3 text-2xl font-bold tracking-tight text-white/95 sm:text-[1.65rem]',
    };
    $successLeadClass = match ($variant) {
        'advocate' => 'text-[17px] leading-relaxed text-[rgb(52_58_68)]',
        'expert' => 'text-[16px] leading-relaxed text-silver/85',
        default => 'text-base leading-relaxed text-silver/90',
    };
    $expectationBoxClass = match ($variant) {
        'advocate' => 'rounded-xl border border-[rgba(154,123,79,0.2)] bg-[rgba(255,252,247,0.85)] px-4 py-3 text-[14px] leading-relaxed text-[rgb(52_58_68)] sm:text-[15px]',
        'expert' => 'rounded-xl border border-white/[0.1] bg-white/[0.04] px-4 py-3 text-[14px] leading-relaxed text-silver/88 sm:text-[15px]',
        default => 'rounded-xl border border-white/10 bg-white/[0.04] px-4 py-3 text-[14px] leading-relaxed text-silver/90 sm:text-[15px]',
    };
    $compact = ! empty($compact ?? false);
    if ($compact && $variant === 'advocate') {
        // Рабочая форма: одна карточка, мягкая внутренняя подложка для полей, без полосатых линий
        $shellClass = 'relative mx-auto w-full max-w-[40rem] rounded-2xl border border-[rgba(154,123,79,0.16)] bg-gradient-to-b from-[#fdfcfa] via-[#faf6f0] to-[#f0e8dc] p-6 shadow-[0_24px_56px_-28px_rgba(42,36,28,0.14)] sm:p-8';
        $inputClass = 'w-full min-h-[2.75rem] rounded-lg border border-[rgba(28,31,38,0.14)] bg-white px-3 py-2 text-[15px] leading-snug text-[rgb(22_25_30)] outline-none transition placeholder:text-[rgb(120_128_140)] focus:border-[rgba(154,123,79,0.55)] focus:ring-1 focus:ring-[rgba(154,123,79,0.25)]';
        $labelClass = 'mb-1 block text-[13px] font-semibold tracking-wide text-[rgb(22_25_30)]';
        // Pill / сегмент: как на expert_lead contacts
        $radioLabelClass = 'relative flex min-h-[2.875rem] min-w-0 flex-1 cursor-pointer items-center justify-center rounded-xl border border-[rgba(154,123,79,0.2)] bg-[rgba(255,250,242,0.95)] px-3 py-2.5 text-center text-[14px] font-semibold leading-snug text-[rgb(42_46_54)] shadow-[0_1px_2px_rgba(42,36,28,0.06)] transition-all hover:border-[rgba(154,123,79,0.35)] has-[:checked]:border-transparent has-[:checked]:bg-[rgb(154_123_79)] has-[:checked]:text-white has-[:checked]:shadow-[0_8px_20px_-8px_rgba(95,72,42,0.45)] focus-within:ring-2 focus-within:ring-[rgba(154,123,79,0.35)]';
        $btnClass = 'advocate-contact-form-submit inline-flex min-h-[3.5rem] w-full items-center justify-center rounded-full bg-[rgb(154_123_79)] px-8 text-[16px] font-semibold text-white shadow-[0_16px_36px_-14px_rgba(95,72,42,0.5)] transition hover:brightness-[1.03] hover:bg-[rgb(130_103_68)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgba(154,123,79,0.65)]';
        $successClass = 'rounded-xl border border-[rgba(154,123,79,0.28)] bg-[linear-gradient(168deg,#fdfcfa_0%,#f3ebe0_48%,#ebe1d4_100%)] px-5 py-5 text-center text-[15px] font-medium leading-relaxed text-[rgb(42_46_54)] shadow-[0_10px_28px_-18px_rgba(42,36,28,0.15)] outline-none';
    }
    $formStackClass = ($compact && $variant === 'advocate') ? 'mt-5 space-y-0' : 'mt-8 space-y-5';
    $subHeadingMarginClass = ($compact && $variant === 'advocate') ? 'mt-2 text-[15px] leading-[1.55] text-[rgb(72_78_86)] sm:mt-2.5 sm:text-[16px] sm:leading-[1.5]' : 'mt-3 text-[16px] leading-relaxed text-[rgb(68_74_84)] sm:text-[17px]';
    $headingH2Class = ($compact && $variant === 'advocate')
        ? 'font-serif text-[clamp(1.25rem,2.2vw,1.65rem)] font-semibold leading-snug tracking-tight text-[rgb(22_25_30)]'
        : 'font-serif text-[clamp(1.35rem,2.4vw,1.75rem)] font-semibold leading-snug tracking-tight text-[rgb(22_25_30)]';
    $textareaMinClass = ($compact && $variant === 'advocate') ? 'min-h-[7.5rem] max-h-[10rem]' : 'min-h-[6rem]';
    $submitWrapPt = ($compact && $variant === 'advocate') ? 'pt-0' : 'pt-2';
    $inquiry = app(\App\Services\PublicSite\ContactInquiryFormPresenter::class)->present($section, $data, $tenant);
    $prefillMessage = (string) ($inquiry['prefill_message'] ?? '');
@endphp
<section id="{{ e($sectionId) }}" class="rb-contact-inquiry w-full min-w-0 {{ $compact ? 'scroll-mt-20' : 'scroll-mt-24' }}" data-page-section-type="contact_inquiry">
    <div id="rb-ci-root-{{ $section->id }}" data-rb-contact-inquiry-root>
        <div
            data-rb-contact-inquiry-alert
            class="{{ $alertClass }} hidden"
            role="alert"
            aria-live="assertive"
        ></div>

        <div class="{{ $shellClass }}">
            @if(($compact ?? false) && $variant === 'advocate' && ($heading !== '' || $sub !== ''))
                <header class="advocate-contact-form-intro text-left">
                    @if($heading !== '')
                        <h2 class="{{ $headingH2Class }}">
                            {{ $heading }}
                        </h2>
                    @endif
                    @if($sub !== '')
                        <p class="{{ $subHeadingMarginClass }}">
                            {{ $sub }}
                        </p>
                    @endif
                </header>
            @else
                @if($heading !== '')
                    <h2 class="{{ $variant === 'advocate' ? $headingH2Class : 'text-xl font-bold text-white sm:text-2xl' }}">
                        {{ $heading }}
                    </h2>
                @endif
                @if($sub !== '')
                    <p class="{{ $variant === 'advocate' ? $subHeadingMarginClass : 'mt-3 text-sm leading-relaxed text-silver sm:text-base' }}">
                        {{ $sub }}
                    </p>
                @endif
            @endif

            <form
                class="relative {{ $formStackClass }}"
                novalidate
                data-rb-contact-inquiry-form
                data-rb-contact-inquiry-endpoint="{{ e($endpoint) }}"
                data-rb-contact-inquiry-default-success="{{ e($successMessage !== '' ? $successMessage : 'Спасибо! Мы получили ваше сообщение и свяжемся с вами.') }}"
                data-rb-contact-inquiry-field-error-class="{{ e($fieldErrorClass) }}"
                data-rb-contact-inquiry-consent="{{ $consentEnabled ? '1' : '0' }}"
                data-rb-contact-inquiry-show-preferred="{{ $showPreferred ? '1' : '0' }}"
                data-rb-contact-inquiry-prefill-message="{{ e($prefillMessage) }}"
            >
                @csrf
                {{-- JSON внутри form: tenant-contact-inquiry-form.js читает meta; как в expert_lead_form. --}}
                <script type="application/json" data-rb-contact-inquiry-channel-meta>@json($contactChannelOptions)</script>
                <input type="hidden" name="page_section_id" value="{{ (int) $section->id }}">
                <input type="hidden" name="page_url" value="{{ url()->current() }}">

                {{-- Honeypot: должно оставаться пустым --}}
                <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                    <label for="rb-ci-website-{{ $section->id }}">Website</label>
                    <input id="rb-ci-website-{{ $section->id }}" type="text" name="website" tabindex="-1" autocomplete="off" value="">
                </div>

                @if(($compact ?? false) && $variant === 'advocate')
                    <div class="advocate-contacts-expert-form-flow w-full space-y-5">
                        <h3 class="advocate-contact-form-h3">Контакты</h3>
                @endif

                <div class="grid min-w-0 gap-2.5 sm:grid-cols-2 sm:gap-3">
                    <div data-rb-public-field="name" class="min-w-0">
                        <label for="rb-ci-name-{{ $section->id }}" class="{{ $labelClass }}">Имя <span class="{{ $variant === 'advocate' ? 'text-[rgb(154_123_79)]' : 'text-moto-amber' }}">*</span></label>
                        <input
                            id="rb-ci-name-{{ $section->id }}"
                            name="name"
                            type="text"
                            required
                            autocomplete="name"
                            maxlength="255"
                            class="{{ $inputClass }}"
                        >
                    </div>
                    <div data-rb-public-field="phone" class="min-w-0">
                        <label for="rb-ci-phone-{{ $section->id }}" class="{{ $labelClass }}">Телефон <span class="{{ $variant === 'advocate' ? 'text-[rgb(154_123_79)]' : 'text-moto-amber' }}">*</span></label>
                        <input
                            id="rb-ci-phone-{{ $section->id }}"
                            name="phone"
                            type="tel"
                            required
                            autocomplete="tel"
                            inputmode="tel"
                            data-rb-intl-phone="1"
                            data-rb-contact-inquiry-phone
                            aria-describedby="rb-ci-phone-hint-{{ $section->id }}"
                            maxlength="28"
                            placeholder="+7 (999) 123-45-67"
                            class="{{ $inputClass }}"
                        >
                        <p id="rb-ci-phone-hint-{{ $section->id }}" data-rb-contact-inquiry-hint class="{{ ($compact ?? false) && $variant === 'advocate' ? 'mt-1' : 'mt-2' }} text-[12px] leading-snug {{ $variant === 'advocate' ? 'text-[rgb(120_128_140)]' : 'text-silver/80' }} sm:text-[13px]"></p>
                    </div>
                </div>

                @if($showEmail)
                    <div data-rb-public-field="email" class="min-w-0">
                        <label for="rb-ci-email-{{ $section->id }}" class="{{ $labelClass }}">Email <span class="{{ $variant === 'advocate' ? 'font-normal text-[rgb(120_128_140)]' : 'font-normal text-silver/70' }}">(необязательно)</span></label>
                        <input
                            id="rb-ci-email-{{ $section->id }}"
                            name="email"
                            type="email"
                            autocomplete="email"
                            maxlength="255"
                            class="{{ $inputClass }}"
                        >
                        <p class="{{ ($compact ?? false) && $variant === 'advocate' ? 'mt-1' : 'mt-2' }} text-[12px] leading-snug {{ $variant === 'advocate' ? 'text-[rgb(120_128_140)]' : 'text-silver/70' }} sm:text-[13px]">
                            Если хотите получить ответ на почту — оставьте адрес; кратко можно попросить об этом в тексте обращения.
                        </p>
                    </div>
                @endif

                @if($showPreferred)
                    <div data-rb-public-field="preferred_contact_channel" class="min-w-0">
                        @if(($compact ?? false) && $variant === 'advocate')
                            <h3 class="advocate-contact-form-h3">Как удобнее связаться</h3>
                        @else
                            <span class="{{ $labelClass }}">Как с вами связаться?</span>
                        @endif
                        @if ($contactChannelCount <= 1)
                            @php $onlyId = $contactChannelOptions[0]['id'] ?? 'phone'; @endphp
                            <input type="hidden" name="preferred_contact_channel" value="{{ e($onlyId) }}">
                            <p class="{{ $variant === 'advocate' ? 'text-[14px] leading-relaxed text-[rgb(90_96_106)]' : 'text-[13px] leading-relaxed text-silver/70' }}">
                                Ответим по выбранному в настройках способу; телефон в заявке обязателен.
                            </p>
                        @else
                            <div class="@if(($compact ?? false) && $variant === 'advocate') flex flex-wrap gap-2 sm:gap-2.5 @else flex flex-col gap-1.5 sm:flex-row sm:flex-wrap sm:gap-2 @endif">
                                @foreach ($contactChannelOptions as $idx => $opt)
                                    @php $cid = $opt['id'] ?? ''; @endphp
                                    @if ($cid !== '')
                                        <label class="{{ $radioLabelClass }}">
                                            <input
                                                type="radio"
                                                name="preferred_contact_channel"
                                                value="{{ e($cid) }}"
                                                class="@if(($compact ?? false) && $variant === 'advocate') sr-only @else mt-0.5 h-4 w-4 shrink-0 border-white/25 text-moto-amber focus:ring-2 focus:ring-moto-amber/35 @endif"
                                                @checked($idx === 0)
                                            >
                                            <span class="min-w-0 text-[14px] font-medium leading-snug {{ $variant === 'advocate' ? 'text-[rgb(28_31_38)]' : 'text-white/90' }} sm:text-[15px] {{ ($compact ?? false) && $variant === 'advocate' ? 'pointer-events-none' : '' }}">{{ $opt['label'] ?? $cid }}</span>
                                        </label>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div data-rb-pref-value-wrap data-rb-public-field="preferred_contact_value" class="hidden min-w-0">
                        <label for="rb-ci-pref-{{ $section->id }}" data-rb-pref-value-label class="{{ $labelClass }}">Контакт для связи</label>
                        <input
                            id="rb-ci-pref-{{ $section->id }}"
                            data-rb-pref-value-input
                            name="preferred_contact_value"
                            type="text"
                            maxlength="500"
                            class="{{ $inputClass }}"
                        >
                        <p data-rb-pref-value-hint class="mt-2 hidden text-[12px] leading-snug text-silver/60 sm:text-[13px]"></p>
                    </div>
                @else
                    <input type="hidden" name="preferred_contact_channel" value="phone">
                @endif

                @if($inquiry['show_service_field'] ?? false)
                    @if(($inquiry['prefilled_service_slug'] ?? '') !== '')
                        <input type="hidden" name="inquiry_service_slug" value="{{ e($inquiry['prefilled_service_slug']) }}">
                    @else
                        <div data-rb-public-field="inquiry_service_slug" class="min-w-0">
                            <label for="rb-ci-svc-{{ $section->id }}" class="{{ $labelClass }}">Услуга <span class="{{ $variant === 'advocate' ? 'text-[rgb(154_123_79)]' : 'text-moto-amber' }}">*</span></label>
                            <select
                                id="rb-ci-svc-{{ $section->id }}"
                                name="inquiry_service_slug"
                                required
                                class="{{ $inputClass }}"
                            >
                                <option value="" selected disabled>Выберите направление</option>
                                @foreach (($inquiry['service_options'] ?? []) as $opt)
                                    <option value="{{ e($opt['slug']) }}">{{ e($opt['title']) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @endif

                <div data-rb-public-field="message" class="min-w-0">
                    @if(($compact ?? false) && $variant === 'advocate')
                        <h3 class="advocate-contact-form-h3" id="rb-ci-msg-heading-{{ $section->id }}">{{ $messageLabel }}</h3>
                        <label for="rb-ci-msg-{{ $section->id }}" class="sr-only">{{ $messageLabel }} <span class="text-[rgb(154_123_79)]">*</span></label>
                    @else
                        <label for="rb-ci-msg-{{ $section->id }}" class="{{ $labelClass }}">{{ $messageLabel }} <span class="{{ $variant === 'advocate' ? 'text-[rgb(154_123_79)]' : 'text-moto-amber' }}">*</span></label>
                    @endif
                    <textarea
                        id="rb-ci-msg-{{ $section->id }}"
                        name="message"
                        required
                        rows="4"
                        maxlength="5000"
                        class="{{ $inputClass }} {{ $textareaMinClass }} {{ ($compact ?? false) && $variant === 'advocate' ? 'resize-y' : '' }}"
                        @if(($compact ?? false) && $variant === 'advocate')
                            aria-labelledby="rb-ci-msg-heading-{{ $section->id }}"
                        @endif
                    ></textarea>
                </div>

                @if($consentEnabled)
                    <div data-rb-public-field="consent_accepted" class="min-w-0">
                        <label class="flex cursor-pointer items-start gap-3 text-[14px] leading-snug {{ $variant === 'advocate' ? 'text-[rgb(42_46_54)]' : 'text-silver/90' }}">
                            <input
                                type="checkbox"
                                name="consent_accepted"
                                value="1"
                                class="mt-1 h-4 w-4 shrink-0 rounded border-white/20 text-moto-amber focus:ring-moto-amber/35"
                            >
                            <span>{{ $consentLabel }}</span>
                        </label>
                    </div>
                @endif

                @if(($compact ?? false) && $variant === 'advocate')
                    </div>
                @endif

                @if($expectationNote !== '')
                    @if($compact && $variant === 'advocate')
                        <p class="mt-6 w-full text-[13px] leading-relaxed text-[rgb(90_96_106)] sm:text-[14px]">
                            {{ $expectationNote }}
                        </p>
                    @else
                        <p class="{{ $expectationBoxClass }}">
                            {{ $expectationNote }}
                        </p>
                    @endif
                @endif

                <div class="{{ ($compact && $variant === 'advocate') ? 'mt-5 w-full text-left' : $submitWrapPt.' text-center sm:text-left' }}">
                    <button
                        type="submit"
                        data-rb-contact-inquiry-submit
                        class="{{ $btnClass }}"
                    >
                        {{ $submitLabel }}
                    </button>
                </div>
            </form>

                       <div
                data-rb-contact-inquiry-success
                class="{{ $successClass }} hidden"
                tabindex="-1"
                role="status"
                aria-live="polite"
            >
                <div class="mx-auto max-w-lg text-balance">
                    <p class="{{ $successTitleClass }}" data-rb-public-form-success-title>Спасибо!</p>
                    <p class="{{ $successLeadClass }}" data-rb-public-form-success-lead></p>
                </div>
            </div>
        </div>
    </div>
</section>
