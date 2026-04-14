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
        ->where('is_visible', true)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get(['slug', 'title']);
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
@endphp
<section id="{{ e($sectionId) }}" class="advocate-expert-lead expert-lead-mega relative mb-14 min-w-0 scroll-mt-24 sm:mb-20 lg:mb-28 @if($isContactsPage) advocate-expert-lead--contacts-page @endif">
    {{-- На /contacts — шире и спокойнее, в тон странице «Контакты». --}}
    <div id="expert-inquiry-block" class="expert-lead-mega__shell relative mx-auto w-full min-w-0 overflow-hidden rounded-[1.5rem] border border-[rgba(28,31,38,0.1)] bg-gradient-to-br from-[#fdfcfa] via-[#faf6f0] to-[#f2ebe2] p-6 shadow-[0_24px_60px_-28px_rgba(28,31,38,0.18)] ring-1 ring-inset ring-[rgba(154,123,79,0.08)] sm:rounded-[2rem] sm:p-10 lg:p-12 @if($isContactsPage) max-w-[min(88rem,100%)] sm:p-9 lg:p-10 @else max-w-[min(72rem,100%)] @endif">
        <div class="relative z-10">
        @if($heading !== '')
            <div class="px-1 @if(!$isContactsPage) text-center @else text-left @endif">
                <h2 class="expert-section-title text-balance tracking-tight text-[rgb(24_27_32)] @if($isContactsPage) font-serif text-[clamp(1.85rem,3.6vw,2.75rem)] font-semibold leading-[1.12] @else text-[clamp(1.55rem,4.5vw,3.1rem)] font-extrabold leading-[1.12] sm:leading-[1.1] @endif">{{ $headingDisplay }}</h2>
            </div>
        @endif
        @if($sub !== '')
            <div class="mt-5 @if(!$isContactsPage) text-center @else text-left @endif">
                <p class="@if($isContactsPage) max-w-3xl text-[17px] leading-[1.7] text-[rgb(62_68_78)] sm:text-[18px] @else mx-auto max-w-2xl text-[16px] leading-[1.65] text-[rgb(70_76_88)] sm:text-[17px] @endif text-pretty font-normal">{{ $subDisplay }}</p>
            </div>
        @endif
        @if(count($trustChips) > 0 && ! $isContactsPage)
            <ul class="mt-8 flex flex-wrap justify-center gap-2 sm:gap-3">
                @foreach($trustChips as $chip)
                    <li class="inline-flex rounded-lg border border-[rgba(154,123,79,0.35)] bg-white/90 px-3 py-1.5 text-[11px] font-bold uppercase tracking-widest text-[rgb(72_62_48)] sm:px-4 sm:py-2 sm:text-xs">{{ $chip }}</li>
                @endforeach
            </ul>
        @endif

        <div id="expert-inquiry-alert" class="mt-4 hidden rounded-xl border border-[rgba(28,31,38,0.12)] bg-white px-4 py-3 text-[15px] leading-snug text-[rgb(40_44_52)]" role="status"></div>

        <script type="application/json" id="expert-inquiry-channel-meta">@json($contactChannelOptions)</script>

        <form id="expert-inquiry-form" class="expert-inquiry-form @if($isContactsPage) mt-6 space-y-5 sm:mt-8 sm:space-y-6 @else mt-8 space-y-6 sm:mt-10 sm:space-y-7 @endif" novalidate
              data-expert-inquiry-endpoint="{{ e($endpoint) }}"
              data-expert-inquiry-default-success="{{ e($successMessage) }}">
            @csrf
            <input type="hidden" name="expert_domain" value="legal_services">
            <input type="hidden" name="page_url" value="{{ url()->current() }}">

            @if($isContactsPage)
                <div class="advocate-contacts-form-panel">
                <p class="advocate-contacts-form-group-label">Контакты</p>
            @endif
            <div class="grid min-w-0 gap-4 sm:gap-5 md:grid-cols-2">
                <div data-rb-public-field="name" class="expert-public-field-wrap min-w-0">
                    <label for="expert-name" class="mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">Имя <span class="text-moto-amber">*</span></label>
                    <input id="expert-name" name="name" type="text" required autocomplete="name" maxlength="255"
                           class="expert-form-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(130_137_148)] focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20">
                </div>
                <div data-rb-public-field="phone" class="expert-public-field-wrap min-w-0">
                    <label for="expert-phone" class="mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">Телефон <span class="text-moto-amber expert-phone-required-star">*</span></label>
                    {{-- data-rb-intl-phone: автоподключение маски из tenant-intl-phone.js (как booking-modal: handleInput + hint) --}}
                    <input id="expert-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel"
                           data-rb-intl-phone="1"
                           aria-describedby="expert-phone-hint"
                           maxlength="28"
                           placeholder="+7 (999) 123-45-67"
                           class="expert-form-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(130_137_148)] focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20">
                    <p id="expert-phone-hint" class="mt-2 text-[13px] leading-snug text-[rgb(82_88_99)] sm:text-[14px]"></p>
                </div>
            </div>
            @if($isContactsPage)
                </div>
                <div class="advocate-contacts-form-panel">
                <p class="advocate-contacts-form-group-label">Способ связи</p>
            @endif
            <div data-rb-public-field="preferred_contact_channel" class="expert-public-field-wrap min-w-0">
                <span class="mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">Как с вами связаться?</span>
                @if ($contactChannelCount <= 1)
                    @php $onlyId = $contactChannelOptions[0]['id'] ?? 'phone'; @endphp
                    <input type="hidden" name="preferred_contact_channel" value="{{ e($onlyId) }}">
                    <p class="text-[15px] leading-relaxed text-[rgb(82_88_99)]">Ответим по контактам, указанным в заявке (телефон обязателен).</p>
                @else
                    <div class="expert-channel-grid flex flex-col gap-2.5 sm:gap-3">
                        @foreach ($contactChannelOptions as $idx => $opt)
                            @php $cid = $opt['id'] ?? ''; @endphp
                            @if ($cid !== '')
                                <label class="expert-channel-option flex cursor-pointer items-start gap-3 rounded-xl border border-[rgba(28,31,38,0.14)] bg-white p-3.5 shadow-sm transition-colors has-[:checked]:border-[rgba(154,123,79,0.55)] has-[:checked]:bg-[#fffefb] sm:p-4">
                                    <input type="radio" name="preferred_contact_channel" value="{{ e($cid) }}"
                                           class="expert-channel-radio mt-0.5 h-4 w-4 shrink-0 border-[rgba(28,31,38,0.35)] text-moto-amber focus:ring-2 focus:ring-moto-amber/35"
                                           @checked($idx === 0)>
                                    <span class="min-w-0 text-[15px] font-medium leading-snug text-[rgb(28_31_32)] sm:text-[16px]">{{ $opt['label'] ?? $cid }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <div id="expert-pref-value-wrap" data-rb-public-field="preferred_contact_value" class="expert-public-field-wrap hidden min-w-0">
                <label for="expert-pref-value" class="mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">Контакт для связи</label>
                <input id="expert-pref-value" name="preferred_contact_value" type="text" maxlength="500"
                       class="expert-form-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(130_137_148)] focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20">
                <p id="expert-pref-value-hint" class="mt-2 hidden text-[13px] leading-snug text-[rgb(82_88_99)] sm:text-[14px]"></p>
            </div>
            @if($isContactsPage)
                </div>
                <div class="advocate-contacts-form-panel">
                <p class="advocate-contacts-form-group-label">Суть вопроса</p>
            @endif
            <div data-rb-public-field="goal_text" class="expert-public-field-wrap min-w-0">
                <label for="expert-goal" class="mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">{{ $goalLabel }} <span class="text-moto-amber">*</span></label>
                <textarea id="expert-goal" name="goal_text" required rows="{{ $isContactsPage ? '3' : '4' }}" maxlength="2000"
                          class="expert-form-input w-full rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] leading-relaxed text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(130_137_148)] focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20 @if($isContactsPage) min-h-[6.5rem] @else min-h-[8rem] @endif"></textarea>
            </div>

            @if($programs->isNotEmpty())
                <div data-rb-public-field="program_slug" class="expert-public-field-wrap min-w-0">
                    <label for="expert-program" class="mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">Программа (необязательно)</label>
                    <select id="expert-program" name="program_slug"
                            class="expert-form-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20 appearance-none">
                        <option value="" class="bg-white text-[rgb(24_27_32)]">—</option>
                        @foreach($programs as $p)
                            <option value="{{ e($p->slug) }}" class="bg-white text-[rgb(24_27_32)]">{{ e($p->title) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if($isContactsPage)
                </div>
                <div class="advocate-contacts-form-panel">
                <p class="advocate-contacts-form-group-label">Уточнение</p>
            @endif
            {{-- Сетка 3×2 на md+: заголовки в одной строке, подсказка только слева, поля в одной строке — без «пляски» колонок. --}}
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:gap-5 md:grid-cols-2 md:grid-rows-[auto_auto_auto] md:items-start md:gap-x-5 md:gap-y-3">
                <div data-rb-public-field="preferred_schedule" class="expert-public-field-wrap min-w-0 md:contents">
                    <span id="expert-schedule-legend" data-expert-schedule-activator tabindex="0" role="button" class="order-1 mb-2 block cursor-pointer select-none rounded-md text-sm font-semibold tracking-wide text-[rgb(28_31_32)] underline-offset-2 transition hover:text-[rgb(95_72_42)] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-moto-amber/45 md:order-none md:col-start-1 md:row-start-1 md:mb-0">Удобное время</span>
                    <p id="expert-schedule-desc" data-expert-schedule-activator tabindex="0" role="button" class="order-2 mb-3 cursor-pointer select-none rounded-md text-[14px] leading-snug text-[rgb(82_88_99)] sm:text-[15px] md:order-none md:col-start-1 md:row-start-2 md:mb-0">{{ $scheduleHint }}</p>
                    <div class="order-3 grid min-w-0 gap-3 rounded-xl border border-[rgba(28,31,38,0.14)] bg-white/70 p-2 shadow-sm transition focus-within:border-moto-amber/50 focus-within:bg-white focus-within:shadow-md focus-within:ring-2 focus-within:ring-moto-amber/25 sm:grid-cols-2 sm:gap-4 sm:p-3 md:order-none md:col-start-1 md:row-start-3" data-expert-schedule-time-group role="group" aria-labelledby="expert-schedule-legend" aria-describedby="expert-schedule-desc">
                        <div class="min-w-0">
                            <label for="expert-schedule-from" class="mb-1.5 block text-[14px] font-medium tracking-wide text-[rgb(28_31_32)]">С</label>
                            <input id="expert-schedule-from" type="time" min="07:00" max="22:00" step="300" autocomplete="off"
                                   class="expert-form-input expert-schedule-time-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors focus:border-moto-amber/60 focus:ring-2 focus:ring-moto-amber/30 [color-scheme:light]">
                        </div>
                        <div class="min-w-0">
                            <label for="expert-schedule-to" class="mb-1.5 block text-[14px] font-medium tracking-wide text-[rgb(28_31_32)]">До</label>
                            <input id="expert-schedule-to" type="time" min="07:00" max="22:00" step="300" autocomplete="off"
                                   class="expert-form-input expert-schedule-time-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors focus:border-moto-amber/60 focus:ring-2 focus:ring-moto-amber/30 [color-scheme:light]">
                        </div>
                    </div>
                </div>
                <div class="hidden md:block md:col-start-2 md:row-start-2" aria-hidden="true"></div>
                <div data-rb-public-field="district" class="expert-public-field-wrap min-w-0 md:contents">
                    <label for="expert-district" class="order-4 mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)] md:order-none md:col-start-2 md:row-start-1 md:mb-0">Район / город</label>
                    <input id="expert-district" name="district" type="text" maxlength="255"
                           class="expert-form-input order-5 w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(130_137_148)] focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20 md:order-none md:col-start-2 md:row-start-3 md:self-stretch">
                </div>
            </div>
            <input type="hidden" name="preferred_schedule" id="expert-schedule-value" value="">
            @if($isContactsPage)
                </div>
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
                    <input id="expert-trans" name="transmission" type="text" maxlength="64"
                           class="expert-form-input w-full min-h-[3.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(130_137_148)] focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20">
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
                <div class="advocate-contacts-form-panel">
                <p class="advocate-contacts-form-group-label">Дополнительно</p>
            @endif
            <div data-rb-public-field="comment" class="expert-public-field-wrap min-w-0">
                <label for="expert-comment" class="mb-2 block text-sm font-semibold tracking-wide text-[rgb(28_31_32)]">Комментарий</label>
                <textarea id="expert-comment" name="comment" rows="3" maxlength="2000"
                          class="expert-form-input w-full min-h-[5.5rem] rounded-xl border border-[rgba(28,31,38,0.22)] bg-white px-4 py-3 text-[17px] leading-relaxed text-[rgb(24_27_32)] outline-none transition-colors placeholder:text-[rgb(130_137_148)] focus:border-moto-amber/55 focus:ring-2 focus:ring-moto-amber/20"></textarea>
            </div>
            @if($isContactsPage)
                </div>
            @endif

            <div class="mt-8 @if($isContactsPage) text-left @else text-center @endif sm:mt-10">
                <button type="submit" id="expert-inquiry-submit" class="tenant-btn-primary inline-flex min-h-[3.75rem] w-full items-center justify-center rounded-xl px-12 text-[18px] font-bold shadow-lg transition-transform hover:scale-[1.02] sm:min-h-[4rem] @if($isContactsPage) sm:max-w-md @endif sm:w-auto">
                    Отправить заявку
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
                const form = document.getElementById('expert-inquiry-form');
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
