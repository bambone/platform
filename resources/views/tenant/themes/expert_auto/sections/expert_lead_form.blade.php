@php
    $tenant = tenant();
    if ($tenant === null) {
        return;
    }
    $formKey = trim((string) ($data['form_key'] ?? 'expert_lead'));
    $config = \App\Models\FormConfig::findEnabledForTenant((int) $tenant->id, $formKey);
    $heading = trim((string) ($data['heading'] ?? ''));
    $sub = trim((string) ($data['subheading'] ?? ''));
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
@endphp
<section id="{{ e($sectionId) }}" class="expert-lead-mega relative mb-14 min-w-0 scroll-mt-24 sm:mb-20 lg:mb-28">
    <div id="expert-inquiry-block" class="expert-lead-mega__shell relative mx-auto max-w-4xl overflow-hidden rounded-[1.5rem] border border-white/[0.08] bg-gradient-to-br from-[#0c0f17] to-[#050608] p-5 shadow-[0_28px_64px_-20px_rgba(0,0,0,0.72)] ring-1 ring-inset ring-white/[0.04] sm:rounded-[2rem] sm:p-10 lg:p-14">
        <div class="relative z-10" data-rb-expert-inquiry-root>
        @if($heading !== '')
            <div class="px-1 text-center">
                <h2 class="expert-section-title text-balance text-[clamp(1.55rem,4.5vw,3.1rem)] font-extrabold leading-[1.12] tracking-tight text-white/95 sm:leading-[1.1]">{{ $heading }}</h2>
            </div>
        @endif
        @if($sub !== '')
            <div class="mt-5 text-center">
                <p class="mx-auto max-w-2xl text-[15px] font-normal leading-[1.6] text-silver/85 sm:text-[17px]">{{ $sub }}</p>
            </div>
        @endif
        @if(count($trustChips) > 0)
            <ul class="mt-8 flex flex-wrap justify-center gap-2 sm:gap-3">
                @foreach($trustChips as $chip)
                    <li class="inline-flex rounded-lg border border-white/[0.06] bg-white/[0.02] px-3 py-1.5 text-[11px] font-bold uppercase tracking-widest text-silver/70 sm:px-4 sm:py-2">{{ $chip }}</li>
                @endforeach
            </ul>
        @endif

        <div id="expert-inquiry-alert" data-rb-expert-inquiry-alert class="mt-4 hidden rounded-xl border border-red-500/35 bg-red-500/10 px-4 py-3 text-sm text-red-100" role="alert" aria-live="assertive"></div>

        <div
            data-rb-expert-inquiry-success
            class="mt-4 hidden rounded-[1.25rem] border border-emerald-500/30 bg-gradient-to-b from-emerald-950/35 to-[#0a0c12] px-6 py-10 text-center outline-none shadow-[0_20px_50px_-28px_rgba(16,185,129,0.35)] sm:px-10"
            tabindex="-1"
            role="status"
            aria-live="polite"
        >
            <div class="mx-auto max-w-xl text-balance">
                <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full border border-emerald-500/40 bg-emerald-500/15 text-emerald-400" aria-hidden="true">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <p class="mb-3 text-2xl font-bold tracking-tight text-white sm:text-[1.65rem]" data-rb-public-form-success-title>Спасибо!</p>
                <p class="text-[16px] leading-relaxed text-silver/85" data-rb-public-form-success-lead></p>
            </div>
        </div>

        <form id="expert-inquiry-form" class="expert-inquiry-form relative mt-8 space-y-5 sm:mt-10 sm:space-y-5" novalidate
              data-expert-inquiry-form
              data-expert-inquiry-main="1"
              data-expert-inquiry-endpoint="{{ e($endpoint) }}"
              data-expert-inquiry-default-success="{{ e($successMessage) }}">
            @csrf
            <script type="application/json" id="expert-inquiry-channel-meta" data-rb-expert-channel-meta>@json($contactChannelOptions)</script>
            <input type="hidden" name="expert_domain" value="{{ $tenant->themeKey() === 'black_duck' ? 'vehicle_detailing' : 'driving_instruction' }}">
            <input type="hidden" name="page_url" value="{{ url()->current() }}">
            @if ($tenant->themeKey() === 'black_duck')
                @php
                    $bdRouteSlug = request()->route('slug');
                    $bdRouteSlug = is_string($bdRouteSlug) ? $bdRouteSlug : '';
                    $bdPageSlugs = ['home', 'contacts', 'uslugi', 'raboty', 'otzyvy', 'faq', 'akcii', 'privacy-policy', 'usloviya-arenda', 'politika-konfidencialnosti', 'order', 'blog', 'about'];
                    $bdServiceSlug = $bdRouteSlug !== '' && ! in_array($bdRouteSlug, $bdPageSlugs, true)
                        ? $bdRouteSlug
                        : '';
                @endphp
                <input type="hidden" name="inquiry_intent" value="service_inquiry">
                <input type="hidden" name="source_page" value="{{ e(request()->getPathInfo() ?: '/') }}">
                @if ($bdServiceSlug !== '')
                    <input type="hidden" name="service_slug" value="{{ e($bdServiceSlug) }}">
                @endif
            @endif
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label for="expert-inquiry-hp-website">Website</label>
                <input id="expert-inquiry-hp-website" type="text" name="website" tabindex="-1" autocomplete="off" value="">
            </div>

            <div class="grid min-w-0 gap-4 sm:gap-5 md:grid-cols-2">
                <div data-rb-public-field="name" class="expert-public-field-wrap min-w-0">
                    <label for="expert-name" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Имя <span class="text-moto-amber">*</span></label>
                    <input id="expert-name" name="name" type="text" required autocomplete="name" maxlength="255"
                           class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors focus:border-moto-amber/50 focus:bg-white/[0.04]">
                </div>
                <div data-rb-public-field="phone" class="expert-public-field-wrap min-w-0">
                    <label for="expert-phone" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Телефон <span class="text-moto-amber expert-phone-required-star">*</span></label>
                    {{-- data-rb-intl-phone: автоподключение маски из tenant-intl-phone.js (как booking-modal: handleInput + hint) --}}
                    <input id="expert-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel"
                           data-rb-expert-phone
                           data-rb-intl-phone="1"
                           aria-describedby="expert-phone-hint"
                           maxlength="28"
                           placeholder="+7 (999) 123-45-67"
                           class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors focus:border-moto-amber/50 focus:bg-white/[0.04]">
                    <p id="expert-phone-hint" data-rb-expert-phone-hint class="mt-2 text-[12px] leading-snug text-silver/80 sm:text-[13px]"></p>
                </div>
            </div>

            <div data-rb-public-field="preferred_contact_channel" class="expert-public-field-wrap min-w-0">
                <span class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Как с вами связаться?</span>
                @if ($contactChannelCount <= 1)
                    @php $onlyId = $contactChannelOptions[0]['id'] ?? 'phone'; @endphp
                    <input type="hidden" name="preferred_contact_channel" value="{{ e($onlyId) }}">
                    <p class="text-[13px] leading-relaxed text-silver/70">Ответим по контактам, указанным в заявке (телефон обязателен).</p>
                @else
                    <div class="expert-channel-grid flex flex-col gap-2.5 sm:gap-3">
                        @foreach ($contactChannelOptions as $idx => $opt)
                            @php $cid = $opt['id'] ?? ''; @endphp
                            @if ($cid !== '')
                                <label class="expert-channel-option flex cursor-pointer items-start gap-3 rounded-xl border border-white/[0.08] bg-white/[0.02] p-3.5 transition-colors has-[:checked]:border-moto-amber/45 has-[:checked]:bg-white/[0.04] sm:p-4">
                                    <input type="radio" name="preferred_contact_channel" value="{{ e($cid) }}"
                                           class="expert-channel-radio mt-0.5 h-4 w-4 shrink-0 border-white/25 text-moto-amber focus:ring-2 focus:ring-moto-amber/35"
                                           @checked($idx === 0)>
                                    <span class="min-w-0 text-[14px] font-medium leading-snug text-white/90 sm:text-[15px]">{{ $opt['label'] ?? $cid }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <div id="expert-pref-value-wrap" data-rb-expert-pref-wrap data-rb-public-field="preferred_contact_value" class="expert-public-field-wrap hidden min-w-0">
                <label for="expert-pref-value" data-rb-expert-pref-label class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Контакт для связи</label>
                <input id="expert-pref-value" data-rb-expert-pref-input name="preferred_contact_value" type="text" maxlength="500"
                       class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors focus:border-moto-amber/50 focus:bg-white/[0.04]">
                <p id="expert-pref-value-hint" data-rb-expert-pref-hint class="mt-2 hidden text-[12px] leading-snug text-silver/60 sm:text-[13px]"></p>
            </div>

            <div data-rb-public-field="goal_text" class="expert-public-field-wrap min-w-0">
                <label for="expert-goal" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Что хотите улучшить <span class="text-moto-amber">*</span></label>
                <textarea id="expert-goal" name="goal_text" required rows="3" maxlength="2000"
                          class="expert-form-input w-full min-h-[6.5rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors focus:border-moto-amber/50 focus:bg-white/[0.04]"></textarea>
            </div>

            @if($programs->isNotEmpty())
                <div data-rb-public-field="program_slug" class="expert-public-field-wrap min-w-0">
                    <label for="expert-program" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">{{ $tenant->themeKey() === 'black_duck' ? 'Пакет / направление (необязательно)' : 'Программа (необязательно)' }}</label>
                    <select id="expert-program" data-rb-expert-program name="program_slug"
                            class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors focus:border-moto-amber/50 focus:bg-white/[0.04] appearance-none">
                        <option value="" class="bg-black text-white">—</option>
                        @foreach($programs as $p)
                            <option value="{{ e($p->slug) }}" class="bg-black text-white">{{ e($p->title) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="grid min-w-0 grid-cols-1 gap-4 sm:gap-5 md:grid-cols-2 md:grid-rows-[auto_auto_auto] md:items-start md:gap-x-5 md:gap-y-3">
                <div data-rb-public-field="preferred_schedule" class="expert-public-field-wrap min-w-0 md:contents">
                    <span id="expert-schedule-legend" data-expert-schedule-activator tabindex="0" role="button" class="order-1 mb-2 block cursor-pointer select-none rounded-md text-sm font-semibold tracking-wide text-white/90 underline-offset-2 transition hover:text-moto-amber hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-moto-amber/45 md:order-none md:col-start-1 md:row-start-1 md:mb-0">Удобное время</span>
                    <p id="expert-schedule-desc" data-expert-schedule-activator tabindex="0" role="button" class="order-2 mb-3 cursor-pointer select-none rounded-md text-[12px] leading-snug text-silver/60 sm:text-[13px] md:order-none md:col-start-1 md:row-start-2 md:mb-0">@if($tenant->themeKey() === 'black_duck')Интервал, когда вам удобно приехать (необязательно). На телефоне откроется выбор времени.@elseИнтервал, когда вам удобно заниматься (необязательно). На телефоне откроется выбор времени.@endif</p>
                    <div class="order-3 grid min-w-0 gap-3 rounded-xl border border-white/[0.1] bg-white/[0.03] p-2 shadow-sm transition focus-within:border-moto-amber/45 focus-within:bg-white/[0.05] focus-within:shadow-md focus-within:ring-2 focus-within:ring-moto-amber/25 sm:grid-cols-2 sm:gap-4 sm:p-3 md:order-none md:col-start-1 md:row-start-3" data-expert-schedule-time-group role="group" aria-labelledby="expert-schedule-legend" aria-describedby="expert-schedule-desc">
                        <div class="min-w-0">
                            <label for="expert-schedule-from" class="mb-1.5 block text-[13px] font-medium tracking-wide text-white/80">С</label>
                            <input id="expert-schedule-from" data-rb-expert-schedule-from type="time" min="07:00" max="22:00" step="300" autocomplete="off"
                                   class="expert-form-input expert-schedule-time-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors focus:border-moto-amber/55 focus:bg-white/[0.06] focus:ring-2 focus:ring-moto-amber/30 [color-scheme:dark]">
                        </div>
                        <div class="min-w-0">
                            <label for="expert-schedule-to" class="mb-1.5 block text-[13px] font-medium tracking-wide text-white/80">До</label>
                            <input id="expert-schedule-to" data-rb-expert-schedule-to type="time" min="07:00" max="22:00" step="300" autocomplete="off"
                                   class="expert-form-input expert-schedule-time-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors focus:border-moto-amber/55 focus:bg-white/[0.06] focus:ring-2 focus:ring-moto-amber/30 [color-scheme:dark]">
                        </div>
                    </div>
                </div>
                <div class="hidden md:block md:col-start-2 md:row-start-2" aria-hidden="true"></div>
                <div data-rb-public-field="district" class="expert-public-field-wrap min-w-0 md:contents">
                    <label for="expert-district" class="order-4 mb-2 block text-sm font-semibold tracking-wide text-white/90 md:order-none md:col-start-2 md:row-start-1 md:mb-0">Район</label>
                    <input id="expert-district" name="district" type="text" maxlength="255"
                           class="expert-form-input order-5 w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors focus:border-moto-amber/50 focus:bg-white/[0.04] md:order-none md:col-start-2 md:row-start-3 md:self-stretch">
                </div>
            </div>
            <input type="hidden" name="preferred_schedule" id="expert-schedule-value" data-rb-expert-schedule-value value="">

            <div class="grid min-w-0 gap-4 sm:grid-cols-2 md:grid-cols-3 sm:gap-5">
                <div data-rb-public-field="has_own_car" class="expert-public-field-wrap min-w-0">
                    <label for="expert-car" class="mb-2 block text-[13px] font-semibold tracking-wide text-white/90">Свой авто</label>
                    <select id="expert-car" name="has_own_car" class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[14px] text-white outline-none appearance-none focus:border-moto-amber/50">
                        <option value="" class="bg-black text-white">Не указано</option>
                        <option value="yes" class="bg-black text-white">Да</option>
                        <option value="no" class="bg-black text-white">Нет</option>
                    </select>
                </div>
                <div data-rb-public-field="transmission" class="expert-public-field-wrap min-w-0">
                    <label for="expert-trans" class="mb-2 block text-[13px] font-semibold tracking-wide text-white/90">Коробка передач</label>
                    <select id="expert-trans" name="transmission" class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[14px] text-white outline-none appearance-none focus:border-moto-amber/50">
                        <option value="" class="bg-black text-white">Не указано</option>
                        <option value="manual" class="bg-black text-white">Механика (МКПП)</option>
                        <option value="automatic" class="bg-black text-white">Автомат (АКПП)</option>
                        <option value="robot" class="bg-black text-white">Робот / вариатор (уточню в комментарии)</option>
                    </select>
                </div>
                <div data-rb-public-field="has_license" class="expert-public-field-wrap min-w-0">
                    <label for="expert-license" class="mb-2 block text-[13px] font-semibold tracking-wide text-white/90">Есть права</label>
                    <select id="expert-license" name="has_license" class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[14px] text-white outline-none appearance-none focus:border-moto-amber/50">
                        <option value="" class="bg-black text-white">Не указано</option>
                        <option value="yes" class="bg-black text-white">Да</option>
                        <option value="no" class="bg-black text-white">Нет</option>
                    </select>
                </div>
            </div>
            
            <div data-rb-public-field="comment" class="expert-public-field-wrap min-w-0">
                <label for="expert-comment" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Комментарий</label>
                <textarea id="expert-comment" name="comment" rows="2" maxlength="2000"
                          class="expert-form-input w-full min-h-[4.5rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors focus:border-moto-amber/50 focus:bg-white/[0.04]"></textarea>
            </div>

            @if (tenant()->themeKey() === 'black_duck')
                <div data-rb-public-field="privacy_accepted" class="expert-public-field-wrap min-w-0 rounded-xl border border-white/[0.08] bg-white/[0.02] p-4">
                    <label class="flex cursor-pointer items-start gap-3 text-left">
                        <input type="checkbox" name="privacy_accepted" value="1" required
                               class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 text-moto-amber focus:ring-2 focus:ring-moto-amber/40">
                        <span class="text-[13px] leading-snug text-silver/85 sm:text-[14px]">
                            Соглашаюсь на обработку персональных данных
                            <a href="{{ url('/privacy-policy') }}" class="text-moto-amber underline-offset-2 hover:underline" target="_blank" rel="noopener">политика конфиденциальности</a>
                        </span>
                    </label>
                </div>
            @endif

            <div class="mt-8 text-center sm:mt-10">
                <button type="submit" id="expert-inquiry-submit" data-rb-expert-inquiry-submit class="tenant-btn-primary inline-flex min-h-[4rem] w-full items-center justify-center rounded-xl px-12 text-[17px] font-bold shadow-2xl transition-transform hover:scale-[1.02] sm:w-auto">
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
