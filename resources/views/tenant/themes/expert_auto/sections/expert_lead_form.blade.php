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
    $stickyLabel = trim((string) ($data['sticky_cta_label'] ?? ''));
    $programs = \App\Models\TenantServiceProgram::query()
        ->where('is_visible', true)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get(['slug', 'title']);
    $successMessage = $config?->success_message ?? 'Спасибо! Заявка отправлена.';
    $endpoint = route('api.tenant.expert-inquiry.store');
@endphp
<section id="{{ e($sectionId) }}" class="expert-lead-mega mb-14 scroll-mt-24 sm:mb-20">
    <div id="expert-inquiry-block" class="expert-lead-mega__shell relative overflow-hidden rounded-[1.35rem] border border-moto-amber/20 bg-gradient-to-br from-moto-amber/[0.08] via-[#0e121c] to-[#080a10] p-6 shadow-[0_24px_60px_-28px_rgba(201,168,124,0.2)] sm:p-9 lg:p-10">
        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-moto-amber/10 blur-3xl" aria-hidden="true"></div>
        <div class="relative z-10">
        @if($heading !== '')
            <h2 class="text-balance text-[clamp(1.45rem,3.2vw,2rem)] font-extrabold leading-tight text-white sm:text-3xl">{{ $heading }}</h2>
        @endif
        @if($sub !== '')
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-silver sm:mt-4 sm:text-base">{{ $sub }}</p>
        @endif
        @if(count($trustChips) > 0)
            <ul class="mt-6 flex flex-wrap gap-2 sm:gap-3">
                @foreach($trustChips as $chip)
                    <li class="expert-trust-badge expert-trust-badge--soft">{{ $chip }}</li>
                @endforeach
            </ul>
        @endif

        <div id="expert-inquiry-alert" class="mt-4 hidden rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-silver" role="status"></div>

        <form id="expert-inquiry-form" class="mt-6 space-y-4" novalidate
              data-expert-inquiry-endpoint="{{ e($endpoint) }}"
              data-expert-inquiry-default-success="{{ e($successMessage) }}">
            @csrf
            <input type="hidden" name="expert_domain" value="driving_instruction">
            <input type="hidden" name="preferred_contact_channel" value="phone">
            <input type="hidden" name="page_url" value="{{ url()->current() }}">

            <div>
                <label for="expert-name" class="mb-1 block text-sm font-medium text-white">Имя <span class="text-red-400">*</span></label>
                <input id="expert-name" name="name" type="text" required autocomplete="name" maxlength="255"
                       class="w-full min-h-11 rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white outline-none focus:border-moto-amber/60">
            </div>
            <div>
                <label for="expert-phone" class="mb-1 block text-sm font-medium text-white">Телефон <span class="text-red-400">*</span></label>
                <input id="expert-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel" maxlength="16"
                       class="w-full min-h-11 rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white outline-none focus:border-moto-amber/60">
            </div>
            <div>
                <label for="expert-goal" class="mb-1 block text-sm font-medium text-white">Что хотите улучшить <span class="text-red-400">*</span></label>
                <textarea id="expert-goal" name="goal_text" required rows="3" maxlength="2000"
                          class="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white outline-none focus:border-moto-amber/60"></textarea>
            </div>

            @if($programs->isNotEmpty())
                <div>
                    <label for="expert-program" class="mb-1 block text-sm font-medium text-white">Программа (необязательно)</label>
                    <select id="expert-program" name="program_slug"
                            class="w-full min-h-11 rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white outline-none focus:border-moto-amber/60">
                        <option value="">—</option>
                        @foreach($programs as $p)
                            <option value="{{ e($p->slug) }}">{{ e($p->title) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="expert-schedule" class="mb-1 block text-sm font-medium text-white">Удобное время</label>
                <input id="expert-schedule" name="preferred_schedule" type="text" maxlength="500"
                       class="w-full min-h-11 rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white outline-none focus:border-moto-amber/60">
            </div>
            <div>
                <label for="expert-district" class="mb-1 block text-sm font-medium text-white">Район</label>
                <input id="expert-district" name="district" type="text" maxlength="255"
                       class="w-full min-h-11 rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white outline-none focus:border-moto-amber/60">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="expert-car" class="mb-1 block text-sm font-medium text-white">Свой автомобиль</label>
                    <select id="expert-car" name="has_own_car" class="w-full min-h-11 rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white">
                        <option value="">Не указано</option>
                        <option value="yes">Да</option>
                        <option value="no">Нет</option>
                    </select>
                </div>
                <div>
                    <label for="expert-trans" class="mb-1 block text-sm font-medium text-white">Коробка передач</label>
                    <input id="expert-trans" name="transmission" type="text" maxlength="64"
                           class="w-full min-h-11 rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white outline-none focus:border-moto-amber/60">
                </div>
            </div>
            <div>
                <label for="expert-license" class="mb-1 block text-sm font-medium text-white">Есть водительское удостоверение</label>
                <select id="expert-license" name="has_license" class="w-full min-h-11 rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white">
                    <option value="">Не указано</option>
                    <option value="yes">Да</option>
                    <option value="no">Нет</option>
                </select>
            </div>
            <div>
                <label for="expert-comment" class="mb-1 block text-sm font-medium text-white">Комментарий</label>
                <textarea id="expert-comment" name="comment" rows="2" maxlength="2000"
                          class="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white outline-none focus:border-moto-amber/60"></textarea>
            </div>

            <button type="submit" id="expert-inquiry-submit" class="tenant-btn-primary w-full justify-center sm:w-auto">
                Отправить заявку
            </button>
        </form>
        </div>
    </div>
</section>

@if($stickyLabel !== '')
    @once('expert-sticky-bar')
        <div id="expert-sticky-cta" class="expert-sticky-cta" data-target="{{ e($sectionId) }}" aria-hidden="false">
            <div class="expert-sticky-cta__inner">
                <a href="#{{ e($sectionId) }}" class="expert-sticky-cta__btn tenant-btn-primary flex w-full justify-center shadow-lg shadow-black/40">{{ e($stickyLabel) }}</a>
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
@endif

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
