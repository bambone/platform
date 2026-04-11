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
@endphp
<section id="{{ e($sectionId) }}" class="expert-lead-mega relative mb-14 min-w-0 scroll-mt-24 sm:mb-20 lg:mb-28">
    <div id="expert-inquiry-block" class="expert-lead-mega__shell relative mx-auto max-w-4xl overflow-hidden rounded-[1.5rem] border border-white/[0.08] bg-gradient-to-br from-[#0c0f17] to-[#050608] p-5 shadow-[0_32px_80px_-24px_rgba(201,168,124,0.2)] sm:rounded-[2rem] sm:p-10 lg:p-14">
        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-moto-amber/10 blur-3xl" aria-hidden="true"></div>
        <div class="relative z-10">
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

        <div id="expert-inquiry-alert" class="mt-4 hidden rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-silver" role="status"></div>

        <form id="expert-inquiry-form" class="expert-inquiry-form mt-8 space-y-5 sm:mt-10 sm:space-y-5" novalidate
              data-expert-inquiry-endpoint="{{ e($endpoint) }}"
              data-expert-inquiry-default-success="{{ e($successMessage) }}">
            @csrf
            <input type="hidden" name="expert_domain" value="driving_instruction">
            <input type="hidden" name="preferred_contact_channel" value="phone">
            <input type="hidden" name="page_url" value="{{ url()->current() }}">

            <div class="grid min-w-0 gap-4 sm:gap-5 md:grid-cols-2">
                <div>
                    <label for="expert-name" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Имя <span class="text-moto-amber">*</span></label>
                    <input id="expert-name" name="name" type="text" required autocomplete="name" maxlength="255"
                           class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors placeholder:text-silver/40 focus:border-moto-amber/50 focus:bg-white/[0.04]">
                </div>
                <div>
                    <label for="expert-phone" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Телефон <span class="text-moto-amber">*</span></label>
                    <input id="expert-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel" maxlength="16"
                           class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors placeholder:text-silver/40 focus:border-moto-amber/50 focus:bg-white/[0.04]">
                </div>
            </div>

            <div>
                <label for="expert-goal" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Что хотите улучшить <span class="text-moto-amber">*</span></label>
                <textarea id="expert-goal" name="goal_text" required rows="3" maxlength="2000"
                          class="expert-form-input w-full min-h-[6.5rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors placeholder:text-silver/40 focus:border-moto-amber/50 focus:bg-white/[0.04]"></textarea>
            </div>

            @if($programs->isNotEmpty())
                <div>
                    <label for="expert-program" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Программа (необязательно)</label>
                    <select id="expert-program" name="program_slug"
                            class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors focus:border-moto-amber/50 focus:bg-white/[0.04] appearance-none">
                        <option value="" class="bg-black text-white">—</option>
                        @foreach($programs as $p)
                            <option value="{{ e($p->slug) }}" class="bg-black text-white">{{ e($p->title) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="grid min-w-0 gap-4 sm:gap-5 md:grid-cols-2">
                <div>
                    <label for="expert-schedule" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Удобное время</label>
                    <input id="expert-schedule" name="preferred_schedule" type="text" maxlength="500"
                           class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors placeholder:text-silver/40 focus:border-moto-amber/50 focus:bg-white/[0.04]">
                </div>
                <div>
                    <label for="expert-district" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Район</label>
                    <input id="expert-district" name="district" type="text" maxlength="255"
                           class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors placeholder:text-silver/40 focus:border-moto-amber/50 focus:bg-white/[0.04]">
                </div>
            </div>

            <div class="grid min-w-0 gap-4 sm:grid-cols-3 sm:gap-5">
                <div>
                    <label for="expert-car" class="mb-2 block text-[13px] font-semibold tracking-wide text-white/90">Свой авто</label>
                    <select id="expert-car" name="has_own_car" class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[14px] text-white outline-none appearance-none focus:border-moto-amber/50">
                        <option value="" class="bg-black text-white">Не указано</option>
                        <option value="yes" class="bg-black text-white">Да</option>
                        <option value="no" class="bg-black text-white">Нет</option>
                    </select>
                </div>
                <div>
                    <label for="expert-trans" class="mb-2 block text-[13px] font-semibold tracking-wide text-white/90">Коробка передач</label>
                    <input id="expert-trans" name="transmission" type="text" maxlength="64"
                           class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[14px] text-white outline-none transition-colors placeholder:text-silver/40 focus:border-moto-amber/50 focus:bg-white/[0.04]">
                </div>
                <div>
                    <label for="expert-license" class="mb-2 block text-[13px] font-semibold tracking-wide text-white/90">Есть права</label>
                    <select id="expert-license" name="has_license" class="expert-form-input w-full min-h-[3.25rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[14px] text-white outline-none appearance-none focus:border-moto-amber/50">
                        <option value="" class="bg-black text-white">Не указано</option>
                        <option value="yes" class="bg-black text-white">Да</option>
                        <option value="no" class="bg-black text-white">Нет</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label for="expert-comment" class="mb-2 block text-sm font-semibold tracking-wide text-white/90">Комментарий</label>
                <textarea id="expert-comment" name="comment" rows="2" maxlength="2000"
                          class="expert-form-input w-full min-h-[4.5rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none transition-colors placeholder:text-silver/40 focus:border-moto-amber/50 focus:bg-white/[0.04]"></textarea>
            </div>

            <div class="mt-8 text-center sm:mt-10">
                <button type="submit" id="expert-inquiry-submit" class="tenant-btn-primary inline-flex min-h-[4rem] w-full items-center justify-center rounded-xl px-12 text-[17px] font-bold shadow-2xl transition-transform hover:scale-[1.02] sm:w-auto">
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

@once('expert-inquiry-form-script')
    <script>
        (function () {
            const form = document.getElementById('expert-inquiry-form');
            if (!form || form.dataset.bound === '1') return;
            form.dataset.bound = '1';
            const endpoint = form.getAttribute('data-expert-inquiry-endpoint') || '';
            const defaultSuccessMessage = form.getAttribute('data-expert-inquiry-default-success') || '';
            const alertEl = document.getElementById('expert-inquiry-alert');
            const submitBtn = document.getElementById('expert-inquiry-submit');
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (alertEl) {
                    alertEl.classList.add('hidden');
                    alertEl.textContent = '';
                }
                const fd = new FormData(form);
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (submitBtn) submitBtn.disabled = true;
                try {
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token || '',
                            'Accept': 'application/json',
                        },
                        body: fd,
                    });
                    const body = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        let msg = typeof body.message === 'string' ? body.message : 'Ошибка отправки.';
                        if (body.errors && typeof body.errors === 'object') {
                            const flat = Object.values(body.errors).flat();
                            if (flat.length) {
                                msg = flat.join(' ');
                            }
                        }
                        if (alertEl) {
                            alertEl.textContent = msg;
                            alertEl.classList.remove('hidden');
                        }
                        return;
                    }
                    if (alertEl) {
                        alertEl.textContent = (typeof body.message === 'string' && body.message !== '')
                            ? body.message
                            : defaultSuccessMessage;
                        alertEl.classList.remove('hidden');
                    }
                    form.reset();
                } catch (err) {
                    if (alertEl) {
                        alertEl.textContent = 'Сеть недоступна. Попробуйте позже.';
                        alertEl.classList.remove('hidden');
                    }
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        })();
    </script>
@endonce
