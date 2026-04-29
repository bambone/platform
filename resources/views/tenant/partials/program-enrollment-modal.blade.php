@php
    $tenant = tenant();
    if ($tenant === null) {
        return;
    }
    $cta = \App\Tenant\Expert\TenantEnrollmentCtaConfig::forCurrent();
    if ($cta === null || $cta->mode() !== \App\Tenant\Expert\TenantEnrollmentCtaConfig::MODE_MODAL) {
        return;
    }
    $endpoint = route('api.tenant.expert-inquiry.store');
    $successMessage = $cta->modalSuccessMessage();
    $modalTitle = $cta->modalTitle();
    $contactChannelOptions = app(\App\ContactChannels\TenantContactChannelsStore::class)->publicFormPreferredOptions((int) $tenant->id);
    $contactChannelCount = count($contactChannelOptions);
    $programs = \App\Models\TenantServiceProgram::query()
        ->where('tenant_id', (int) $tenant->id)
        ->where('is_visible', true)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get(['slug', 'title', 'id']);
    $expertDomain = $tenant->theme_key === 'advocate_editorial' ? 'legal_services' : 'driving_instruction';
@endphp
{{-- Не добавлять Tailwind `flex` на <dialog>: иначе перебивается UA display:none у закрытого dialog — модалка всегда на экране. --}}
<dialog
    id="rb-program-enrollment-dialog"
    class="rb-program-enrollment-dialog fixed inset-0 z-[60] m-0 h-[100dvh] max-h-[100dvh] w-full max-w-none flex-col items-center justify-end overflow-x-hidden overflow-y-auto border-0 bg-transparent p-0 pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)] backdrop:bg-transparent sm:justify-center sm:p-4 sm:py-6"
    aria-labelledby="rb-program-enrollment-dialog-title"
>
    {{-- Слой клика по затемнению: без transform на <dialog>, анимация только у панели — нет «прыжка» от translate(-50%,-50%). --}}
    <div class="pointer-events-auto absolute inset-0 z-0" aria-hidden="true" data-rb-enrollment-dialog-backdrop-hit></div>
    <div class="rb-program-enrollment-dialog__panel pointer-events-auto relative z-10 flex max-h-[min(100dvh,calc(100dvh-env(safe-area-inset-bottom)))] w-full min-h-0 flex-col overflow-hidden rounded-t-[1.35rem] border border-white/10 bg-gradient-to-b from-[#0c0f17] to-[#050608] shadow-[0_28px_64px_-20px_rgba(0,0,0,0.72)] sm:max-h-[min(92dvh,calc(100vh-2rem))] sm:w-[min(100%,28rem)] sm:rounded-2xl sm:ring-1 sm:ring-white/10">
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-white/10 px-4 py-3 sm:px-5 sm:py-4">
            <h2 id="rb-program-enrollment-dialog-title" class="text-lg font-bold leading-tight text-white/95 sm:text-xl">{{ $modalTitle }}</h2>
            <button
                type="button"
                class="flex h-11 min-w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white/80 transition hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber"
                data-rb-enrollment-dialog-close
                aria-label="Закрыть"
            >&times;</button>
        </div>
        <div class="tenant-booking-modal-scroll tenant-thin-scrollbar min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
            <div data-rb-expert-inquiry-root class="min-h-0">
            <div data-rb-expert-inquiry-alert class="mb-4 hidden rounded-xl border border-red-500/35 bg-red-500/10 px-4 py-3 text-sm text-red-100" role="alert" aria-live="assertive"></div>
            <div
                data-rb-expert-inquiry-success
                class="mb-4 hidden rounded-xl border border-emerald-500/30 bg-gradient-to-b from-emerald-950/35 to-[#0a0c12] px-5 py-8 text-center outline-none"
                tabindex="-1"
                role="status"
                aria-live="polite"
            >
                <div class="mx-auto max-w-sm text-balance">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-emerald-500/40 bg-emerald-500/15 text-emerald-400" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <p class="mb-2 text-xl font-bold text-white" data-rb-public-form-success-title>Спасибо!</p>
                    <p class="text-[15px] leading-relaxed text-silver/85" data-rb-public-form-success-lead></p>
                    <button type="button" data-rb-expert-inquiry-success-close class="tenant-btn-primary mt-6 min-h-11 w-full rounded-xl px-6 py-3 text-[15px] font-bold shadow-lg">
                        Закрыть
                    </button>
                </div>
            </div>
            <form
                class="space-y-4"
                novalidate
                data-expert-inquiry-form
                data-expert-inquiry-endpoint="{{ e($endpoint) }}"
                data-expert-inquiry-default-success="{{ e($successMessage) }}"
            >
                <script type="application/json" data-rb-expert-channel-meta>@json($contactChannelOptions)</script>
                @csrf
                <input type="hidden" name="expert_domain" value="{{ e($expertDomain) }}">
                <input type="hidden" name="page_url" value="{{ url()->current() }}">
                <input type="hidden" name="source_type" value="">
                <input type="hidden" name="source_context" value="">
                <input type="hidden" name="source_page" value="" data-rb-enrollment-source-page>
                <input type="hidden" name="program_id" value="" data-rb-enrollment-program-id>
                <input type="hidden" name="utm_source" value="" data-rb-enrollment-utm="utm_source">
                <input type="hidden" name="utm_medium" value="" data-rb-enrollment-utm="utm_medium">
                <input type="hidden" name="utm_campaign" value="" data-rb-enrollment-utm="utm_campaign">
                <input type="hidden" name="utm_content" value="" data-rb-enrollment-utm="utm_content">
                <input type="hidden" name="utm_term" value="" data-rb-enrollment-utm="utm_term">
                <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                    <label for="rb-enrollment-hp-website">Website</label>
                    <input id="rb-enrollment-hp-website" type="text" name="website" tabindex="-1" autocomplete="off" value="">
                </div>

                <div data-rb-public-field="name" class="expert-public-field-wrap min-w-0">
                    <label for="rb-enrollment-name" class="mb-2 block text-sm font-semibold text-white/90">Имя <span class="text-moto-amber">*</span></label>
                    <input id="rb-enrollment-name" name="name" type="text" required autocomplete="name" maxlength="255"
                           class="expert-form-input w-full min-h-[3rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none focus:border-moto-amber/50">
                </div>
                <div data-rb-public-field="phone" class="expert-public-field-wrap min-w-0">
                    <label for="rb-enrollment-phone" class="mb-2 block text-sm font-semibold text-white/90">Телефон <span class="text-moto-amber">*</span></label>
                    <input id="rb-enrollment-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel"
                           data-rb-expert-phone
                           data-rb-intl-phone="1"
                           aria-describedby="rb-enrollment-phone-hint"
                           maxlength="28"
                           placeholder="+7 (999) 123-45-67"
                           class="expert-form-input w-full min-h-[3rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none focus:border-moto-amber/50">
                    <p id="rb-enrollment-phone-hint" data-rb-expert-phone-hint class="mt-2 text-[12px] leading-snug text-silver/80 sm:text-[13px]"></p>
                </div>

                @if ($contactChannelCount <= 1)
                    @php $onlyId = $contactChannelOptions[0]['id'] ?? 'phone'; @endphp
                    <input type="hidden" name="preferred_contact_channel" value="{{ e($onlyId) }}">
                @else
                    <div data-rb-public-field="preferred_contact_channel" class="expert-public-field-wrap min-w-0">
                        <span class="mb-2 block text-sm font-semibold text-white/90">Как с вами связаться?</span>
                        <div class="flex flex-col gap-2">
                            @foreach ($contactChannelOptions as $idx => $opt)
                                @php $cid = $opt['id'] ?? ''; @endphp
                                @if ($cid !== '')
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/[0.08] bg-white/[0.02] p-3 has-[:checked]:border-moto-amber/45">
                                        <input type="radio" name="preferred_contact_channel" value="{{ e($cid) }}" class="mt-1" @checked($idx === 0)>
                                        <span class="text-[14px] text-white/90">{{ $opt['label'] ?? $cid }}</span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div id="rb-enrollment-pref-wrap" data-rb-expert-pref-wrap data-rb-public-field="preferred_contact_value" class="expert-public-field-wrap hidden min-w-0">
                        <label for="rb-enrollment-pref-value" data-rb-expert-pref-label class="mb-2 block text-sm font-semibold text-white/90">Контакт для связи</label>
                        <input id="rb-enrollment-pref-value" data-rb-expert-pref-input name="preferred_contact_value" type="text" maxlength="500"
                               class="expert-form-input w-full min-h-[3rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none focus:border-moto-amber/50">
                        <p id="rb-enrollment-pref-hint" data-rb-expert-pref-hint class="mt-2 hidden text-[12px] text-silver/60"></p>
                    </div>
                @endif

                <div data-rb-public-field="goal_text" class="expert-public-field-wrap min-w-0">
                    <label for="rb-enrollment-goal" class="mb-2 block text-sm font-semibold text-white/90">Цель обращения <span class="text-moto-amber">*</span></label>
                    <textarea id="rb-enrollment-goal" name="goal_text" required rows="3" maxlength="2000"
                              class="expert-form-input w-full min-h-[5rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none focus:border-moto-amber/50"></textarea>
                </div>

                @if($programs->isNotEmpty())
                    <div data-rb-public-field="program_slug" class="expert-public-field-wrap min-w-0">
                        <label for="rb-enrollment-program" class="mb-2 block text-sm font-semibold text-white/90">Программа</label>
                        <select id="rb-enrollment-program" data-rb-expert-program name="program_slug"
                                class="expert-form-input w-full min-h-[3rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none focus:border-moto-amber/50 appearance-none">
                            <option value="" class="bg-black text-white">—</option>
                            @foreach($programs as $p)
                                <option value="{{ e($p->slug) }}" data-rb-program-db-id="{{ (int) $p->id }}" class="bg-black text-white">{{ e($p->title) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div data-rb-public-field="comment" class="expert-public-field-wrap min-w-0">
                    <label for="rb-enrollment-comment" class="mb-2 block text-sm font-semibold text-white/90">Комментарий</label>
                    <textarea id="rb-enrollment-comment" name="comment" rows="2" maxlength="2000"
                              class="expert-form-input w-full min-h-[4rem] rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 text-[15px] text-white outline-none focus:border-moto-amber/50"></textarea>
                </div>

                <div data-rb-public-field="privacy_accepted" class="expert-public-field-wrap min-w-0">
                    <label class="flex cursor-pointer items-start gap-3 text-[14px] leading-snug text-silver/90">
                        <input type="checkbox" name="privacy_accepted" value="1" required class="mt-1 h-4 w-4 shrink-0 rounded border-white/20 text-moto-amber focus:ring-moto-amber/40">
                        <span>Согласие на обработку персональных данных <span class="text-moto-amber">*</span></span>
                    </label>
                </div>

                <button type="submit" data-rb-expert-inquiry-submit class="tenant-btn-primary mt-2 flex min-h-[3.25rem] w-full items-center justify-center rounded-xl px-6 text-base font-bold shadow-lg">
                    Отправить заявку
                </button>
            </form>
            </div>
        </div>
    </div>
</dialog>
<style>
    /* Закрытый dialog обязан быть скрыт (Tailwind-классы не должны задавать display без [open]). */
    .rb-program-enrollment-dialog:not([open]) {
        display: none !important;
    }

    /* Центрирование через flex, не через transform на dialog — иначе конфликт с keyframes и визуальный «скачок». */
    .rb-program-enrollment-dialog[open] {
        display: flex;
    }

    .rb-program-enrollment-dialog::backdrop {
        background: rgba(8, 10, 14, 0.58);
    }

    @supports (backdrop-filter: blur(4px)) {
        .rb-program-enrollment-dialog::backdrop {
            background: rgba(8, 10, 14, 0.45);
            backdrop-filter: blur(4px);
        }
    }

    .rb-program-enrollment-dialog[open]::backdrop {
        animation: rb-enrollment-backdrop-in 0.28s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes rb-enrollment-backdrop-in {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .rb-program-enrollment-dialog[open] .rb-program-enrollment-dialog__panel {
        animation: rb-enrollment-panel-in 0.34s cubic-bezier(0.16, 1, 0.3, 1) both;
        transform-origin: 50% 100%;
    }

    @media (min-width: 640px) {
        .rb-program-enrollment-dialog[open] .rb-program-enrollment-dialog__panel {
            animation: rb-enrollment-panel-in-sm 0.34s cubic-bezier(0.16, 1, 0.3, 1) both;
            transform-origin: 50% 50%;
        }
    }

    @keyframes rb-enrollment-panel-in {
        from {
            opacity: 0;
            transform: translateY(18px) scale(0.985);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @keyframes rb-enrollment-panel-in-sm {
        from {
            opacity: 0;
            transform: translateY(14px) scale(0.99);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .rb-program-enrollment-dialog.rb-enrollment-dialog--closing[open]::backdrop {
        animation: rb-enrollment-backdrop-out 0.24s cubic-bezier(0.4, 0, 1, 1) forwards;
    }

    @keyframes rb-enrollment-backdrop-out {
        from {
            opacity: 1;
        }
        to {
            opacity: 0;
        }
    }

    .rb-program-enrollment-dialog.rb-enrollment-dialog--closing[open] .rb-program-enrollment-dialog__panel {
        animation: rb-enrollment-panel-out 0.24s cubic-bezier(0.4, 0, 1, 1) forwards;
    }

    @media (min-width: 640px) {
        .rb-program-enrollment-dialog.rb-enrollment-dialog--closing[open] .rb-program-enrollment-dialog__panel {
            animation: rb-enrollment-panel-out-sm 0.24s cubic-bezier(0.4, 0, 1, 1) forwards;
        }
    }

    @keyframes rb-enrollment-panel-out {
        from {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        to {
            opacity: 0;
            transform: translateY(12px) scale(0.985);
        }
    }

    @keyframes rb-enrollment-panel-out-sm {
        from {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        to {
            opacity: 0;
            transform: translateY(10px) scale(0.99);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .rb-program-enrollment-dialog[open]::backdrop {
            animation-duration: 0.2s;
        }

        .rb-program-enrollment-dialog[open] .rb-program-enrollment-dialog__panel {
            animation: rb-enrollment-panel-in-a11y 0.18s ease forwards;
        }

        @keyframes rb-enrollment-panel-in-a11y {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .rb-program-enrollment-dialog.rb-enrollment-dialog--closing[open] .rb-program-enrollment-dialog__panel {
            animation: rb-enrollment-panel-out-a11y 0.14s ease forwards;
        }

        @keyframes rb-enrollment-panel-out-a11y {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
    }
</style>
