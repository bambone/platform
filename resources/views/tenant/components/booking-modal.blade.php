@props(['preferredChannelFormOptions' => []])

<!-- Alpine Booking Modal -->
<div
    x-data="bookingModal({ preferredOptions: @js($preferredChannelFormOptions) })"
    @open-booking-modal.window="openModal($event.detail)"
    x-show="isOpen"
    style="display: none;"
    class="relative z-[100]"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
>
    {{-- Один оверлей с @click.self: календарь Flatpickr в document.body не считается «вне» панели (в отличие от @click.away). --}}
    <div x-show="isOpen"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="closeModal()"
         class="fixed inset-0 z-[99] flex items-end justify-center overflow-hidden bg-black/80 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-sm sm:items-center sm:p-4 sm:pb-4">
        <div class="flex h-full min-h-0 w-full max-w-2xl items-end justify-center lg:max-w-3xl sm:items-center sm:py-6" @click.self="closeModal()">
            <div x-show="isOpen"
                 @click.stop
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
                 x-transition:leave-end="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                 class="tenant-booking-modal-panel relative flex min-h-0 w-full flex-col overflow-hidden rounded-t-2xl border border-white/10 bg-[#141417]/95 text-left shadow-2xl shadow-black/60 ring-1 ring-white/[0.06] backdrop-blur-xl sm:rounded-2xl">

                <div class="flex shrink-0 items-center justify-between border-b border-white/10 bg-gradient-to-r from-white/[0.06] to-transparent px-5 py-4 sm:px-8 sm:py-5">
                    <h3 class="min-w-0 pr-2 text-xl font-bold leading-tight text-white sm:text-2xl" id="modal-title">
                        Бронирование <span class="text-moto-amber" x-text="bike.name"></span>
                    </h3>
                    <button type="button" @click="closeModal()" class="inline-flex min-h-10 min-w-10 shrink-0 touch-manipulation items-center justify-center rounded-xl text-zinc-400 transition-colors hover:bg-white/10 hover:text-white" aria-label="Закрыть">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden" x-show="!isSuccess">
                    <form @submit.prevent="submitForm" class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                        {{-- relative + flex-1 min-h-0: родитель с ограниченной высотой; absolute inset-0 + overflow-y-auto — стабильный скролл (обходит min-height:auto у flex/grid). --}}
                        <div class="relative min-h-0 min-w-0 flex-1 overflow-hidden">
                        <div class="tenant-thin-scrollbar tenant-booking-modal-scroll absolute inset-0 touch-pan-y overflow-x-hidden overflow-y-auto overscroll-contain px-5 py-4 sm:px-8 sm:py-5">
                            <div x-show="errorMessage" x-ref="bookingErrorBanner" class="mb-4 rounded-xl border border-red-500/40 bg-red-500/10 p-3">
                                <div class="flex items-start gap-3">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span class="text-sm text-red-100" x-text="errorMessage"></span>
                                </div>
                            </div>

                            <div class="border-b border-white/[0.06] pb-5">
                                <p class="mb-3 text-sm font-semibold uppercase tracking-wider text-zinc-500">Период аренды</p>
                                <div class="grid grid-cols-2 gap-4 sm:gap-5">
                                    <div>
                                        <label for="booking-modal-start-date" class="mb-1.5 block text-sm font-medium text-zinc-400">Дата начала</label>
                                        <input id="booking-modal-start-date"
                                               x-ref="bookingStartDateInput"
                                               data-fp-anchor="tenant-modal-start"
                                               name="booking_start_date"
                                               type="text"
                                               readonly
                                               required
                                               data-fp-min="{{ date('Y-m-d') }}"
                                               placeholder="__.__.____"
                                               autocomplete="off"
                                               class="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-3 text-base text-white outline-none transition-colors [color-scheme:dark] focus:border-moto-amber/50 focus:ring-1 focus:ring-moto-amber">
                                    </div>
                                    <div>
                                        <label for="booking-modal-end-date" class="mb-1.5 block text-sm font-medium text-zinc-400">Дата возврата</label>
                                        <input id="booking-modal-end-date"
                                               x-ref="bookingEndDateInput"
                                               data-fp-anchor="tenant-modal-end"
                                               name="booking_end_date"
                                               type="text"
                                               readonly
                                               required
                                               data-fp-min="{{ date('Y-m-d') }}"
                                               placeholder="__.__.____"
                                               autocomplete="off"
                                               class="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-3 text-base text-white outline-none transition-colors [color-scheme:dark] focus:border-moto-amber/50 focus:ring-1 focus:ring-moto-amber">
                                    </div>
                                </div>
                                <p class="mt-3 text-xs leading-relaxed text-zinc-500">В календаре оранжевая обводка у дней, на которые уже поступали заявки на эту технику (ещё в обработке). Серым отмечены недоступные дни.</p>
                                <p class="mt-2 text-xs leading-relaxed text-zinc-500">Отправка формы не бронирует мотоцикл автоматически: заявки обрабатываются по очереди, оператор свяжется с вами и подтвердит наличие и условия.</p>
                            </div>

                            <div class="mt-4 space-y-2 rounded-xl border border-amber-500/35 bg-amber-500/[0.07] px-4 py-3"
                                 x-show="hintPendingOnRange">
                                <p class="text-sm font-semibold text-amber-100">На выбранные даты уже есть заявка на эту технику</p>
                                <p class="text-sm leading-relaxed text-amber-100/90">Техника может оказаться занята после подтверждения другой заявки. Ваша заявка встанет в очередь; приоритет и итог решает оператор и свяжется с вами.</p>
                            </div>

                            <div class="mt-4 space-y-2 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3"
                                 x-show="hintSelfOverlap || hintBusyRange || hintRangesText || (hintRangeOk === false && form.start_date && form.end_date)">
                                <p x-show="hintSelfOverlap" class="text-sm leading-relaxed text-amber-200/95">По вашему номеру уже есть бронирование <span class="font-semibold text-moto-amber" x-text="bike.name"></span> на выбранные даты. Повторная заявка возможна.</p>
                                <p x-show="hintBusyRange && !hintSelfOverlap" class="text-sm leading-relaxed text-zinc-300">На выбранные даты эта техника занята. Серые дни в календаре — визуальная подсказка; итог по периоду — по тексту ниже.</p>
                                <p x-show="hintRangeOk === false && form.start_date && form.end_date" class="text-sm font-medium text-red-200/90">Выбранный период сейчас недоступен.</p>
                                <p x-show="hintRangesText" class="text-sm text-zinc-400">Возможные окна: <span class="text-zinc-200" x-text="hintRangesText"></span></p>
                            </div>

                            <div x-show="calculatedDays > 0" x-collapse class="mt-6 rounded-xl border border-moto-amber/20 bg-gradient-to-br from-moto-amber/[0.08] to-white/[0.03] p-5 sm:p-6">
                                <div class="mb-2 flex items-center justify-between text-base">
                                    <span class="text-zinc-400">Стоимость 1 суток:</span>
                                    <span class="font-medium text-white" x-text="formatMoney(bike.price) + ' ₽'"></span>
                                </div>
                                <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-4 text-base">
                                    <span class="text-zinc-400">Количество дней:</span>
                                    <span class="font-medium text-white" x-text="calculatedDays"></span>
                                </div>
                                <div class="flex items-end justify-between gap-3">
                                    <span class="text-lg font-medium text-zinc-200">Итого к оплате:</span>
                                    <span class="text-3xl font-bold tracking-tight text-moto-amber sm:text-4xl" x-text="formatMoney(totalPrice) + ' ₽'"></span>
                                </div>
                            </div>

                            <div class="mt-6 space-y-5">
                                <div>
                                    <label for="booking-modal-customer-name" class="mb-1.5 block text-sm font-medium text-zinc-400">Ваше имя</label>
                                    <input id="booking-modal-customer-name" name="customer_name" type="text" x-model="form.customer_name" required placeholder="Иван Иванов" autocomplete="name"
                                           x-ref="bookingCustomerNameInput"
                                           class="w-full rounded-xl border border-white/10 bg-black/40 px-4 py-3 text-base text-white outline-none transition-colors placeholder:text-zinc-500 focus:border-moto-amber/50 focus:ring-1 focus:ring-moto-amber">
                                </div>
                                <div>
                                    <label for="booking-modal-phone" class="mb-1.5 block text-sm font-medium text-zinc-400">Номер телефона</label>
                                    <input id="booking-modal-phone"
                                           x-ref="bookingPhoneInput"
                                           name="phone"
                                           type="tel"
                                           @input="onPhoneInput()"
                                           @change="onPhoneInput()"
                                           @blur="onPhoneInput()"
                                           maxlength="28"
                                           inputmode="tel"
                                           required
                                           autocomplete="tel"
                                           placeholder="+7 (999) 123-45-67"
                                           class="w-full rounded-xl border border-white/10 bg-black/40 px-4 py-3 text-base text-white outline-none transition-colors placeholder:text-zinc-500 focus:border-moto-amber/50 focus:ring-1 focus:ring-moto-amber">
                                    <p class="mt-2 text-xs leading-snug text-zinc-400 sm:text-sm" x-text="phoneFieldHint()"></p>
                                </div>

                                <fieldset x-show="showPreferredBlock()" x-cloak
                                          class="rounded-xl border border-white/10 bg-black/25 p-4 sm:p-5">
                                    <legend class="mb-1 w-full text-sm font-semibold uppercase tracking-wider text-zinc-500">Как удобнее связаться</legend>
                                    <p class="mb-4 text-xs leading-relaxed text-zinc-500">Телефон обязателен. Можно выбрать удобный мессенджер.</p>
                                    <div class="flex flex-col gap-2">
                                        <template x-for="opt in preferredOptions" :key="opt.id">
                                            <label :for="'booking-modal-pref-'+opt.id"
                                                   class="group flex min-h-[3rem] cursor-pointer touch-manipulation items-center gap-3 rounded-xl border border-white/10 bg-black/35 px-4 py-3 transition-colors hover:border-white/[0.14] has-[:checked]:border-moto-amber/55 has-[:checked]:bg-moto-amber/[0.08] has-[:checked]:shadow-[inset_0_0_0_1px_rgba(232,93,4,0.22)] focus-within:ring-2 focus-within:ring-moto-amber/35 focus-within:ring-offset-2 focus-within:ring-offset-[#141417]">
                                                <input type="radio" name="booking_modal_preferred_channel"
                                                       class="sr-only"
                                                       :id="'booking-modal-pref-'+opt.id"
                                                       :value="opt.id"
                                                       x-model="form.preferred_contact_channel">
                                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-zinc-500/45 bg-[#0c0c0e] transition-colors group-has-[:checked]:border-moto-amber group-has-[:checked]:bg-moto-amber/10" aria-hidden="true">
                                                    <span class="h-2 w-2 rounded-full bg-moto-amber opacity-0 shadow-[0_0_10px_rgba(232,93,4,0.55)] transition group-has-[:checked]:opacity-100"></span>
                                                </span>
                                                <span class="min-w-0 flex-1 text-sm leading-snug text-zinc-300 group-has-[:checked]:font-medium group-has-[:checked]:text-white" x-text="opt.label"></span>
                                            </label>
                                        </template>
                                    </div>
                                    <div x-show="selectedNeedsExtraValue()" x-collapse class="mt-4 border-t border-white/[0.08] pt-4">
                                        <label for="booking-modal-pref-value" class="mb-1.5 block text-sm font-medium text-zinc-400">Контакт в мессенджере *</label>
                                        <input id="booking-modal-pref-value" type="text" x-model="form.preferred_contact_value" autocomplete="off"
                                               x-ref="bookingPrefValueInput"
                                               :placeholder="preferredValuePlaceholder()"
                                               class="w-full rounded-xl border border-white/10 bg-black/40 px-4 py-3 text-base text-white outline-none transition-colors placeholder:text-zinc-500 focus:border-moto-amber/50 focus:ring-1 focus:ring-moto-amber">
                                        <p class="mt-2 text-xs leading-relaxed text-zinc-500" x-show="preferredValueHint()" x-text="preferredValueHint()"></p>
                                    </div>
                                </fieldset>

                                <div>
                                    <label for="booking-modal-comment" class="mb-1.5 block text-sm font-medium text-zinc-400">Комментарий (необязательно)</label>
                                    <textarea id="booking-modal-comment" name="customer_comment" x-model="form.customer_comment" placeholder="Например: нужна доставка в Анапу" rows="2" autocomplete="off"
                                              class="w-full resize-none rounded-xl border border-white/10 bg-black/40 px-4 py-3 text-base text-white outline-none transition-colors placeholder:text-zinc-500 focus:border-moto-amber/50 focus:ring-1 focus:ring-moto-amber"></textarea>
                                </div>
                            </div>
                        </div>
                        </div>

                        <div class="shrink-0 border-t border-white/10 bg-[#121214]/90 px-5 py-5 backdrop-blur-md sm:px-8">
                            <button type="submit" :disabled="isLoading"
                                    class="tenant-btn-primary min-h-[3.25rem] w-full touch-manipulation gap-2 px-6 py-4 text-base disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!isLoading">Оставить заявку</span>
                                <svg x-show="isLoading" class="h-5 w-5 animate-spin text-[#0c0c0c]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-show="isLoading">Отправка...</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="flex flex-col items-center px-4 py-10 text-center sm:px-6 sm:py-12" x-show="isSuccess" x-cloak>
                    <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-full border border-green-500/30 bg-green-500/20 text-green-400 shadow-lg shadow-green-500/10">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h3 class="mb-2 text-2xl font-bold text-white">Заявка принята!</h3>
                    <p class="mx-auto mb-8 max-w-sm text-zinc-400">
                        Наш менеджер свяжется с вами в ближайшее время для подтверждения бронирования.
                    </p>
                    <button type="button" @click="closeModal()" class="tenant-btn-secondary min-h-11 touch-manipulation px-8">
                        Отлично, спасибо
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('bookingModal', (cfg = {}) => ({
        init() {
            this.$watch('isOpen', (open) => {
                const root = document.documentElement;
                const body = document.body;
                if (open) {
                    root.classList.add('overflow-hidden');
                    body.classList.add('overflow-hidden');
                } else {
                    root.classList.remove('overflow-hidden');
                    body.classList.remove('overflow-hidden');
                }
            });
        },

        isOpen: false,
        isLoading: false,
        isSuccess: false,
        errorMessage: '',

        bike: { id: null, name: '', price: 0 },

        preferredOptions: Array.isArray(cfg.preferredOptions) ? cfg.preferredOptions : [],

        form: {
            start_date: '',
            end_date: '',
            customer_name: '',
            phone: '',
            customer_comment: '',
            preferred_contact_channel: 'phone',
            preferred_contact_value: '',
            source: 'site',
        },

        showPreferredBlock() {
            return this.preferredOptions.length > 1;
        },

        selectedNeedsExtraValue() {
            const id = this.form.preferred_contact_channel;
            const o = this.preferredOptions.find((x) => x.id === id);

            return !!(o && o.needs_value);
        },

        calculatedDays: 0,
        totalPrice: 0,

        hintsSeq: 0,
        hintsAbort: null,
        hintsTimer: null,
        hintsPhoneTimer: null,
        hintBusyRange: false,
        hintSelfOverlap: false,
        hintPendingOnRange: false,
        hintRangeOk: null,
        hintRangesText: '',

        formatMoney(amount) {
            return new Intl.NumberFormat('ru-RU').format(amount);
        },

        phoneFieldHint() {
            if (typeof window.TenantIntlPhone?.phoneHelperHint === 'function') {
                return window.TenantIntlPhone.phoneHelperHint(this.form.phone);
            }

            return 'Введите номер в международном формате. Для России можно начинать с 8 или +7.';
        },

        _fieldFlashTimer: null,
        _flashedEls: [],

        fieldFlashClear() {
            (this._flashedEls || []).forEach((el) => el.classList.remove('rb-public-field-error-flash'));
            this._flashedEls = [];
        },

        /**
         * Прокрутка к проблемному полю и красная «вспышка» (см. .rb-public-field-error-flash в shared CSS).
         *
         * @param  {Element|Element[]|null|undefined}  els
         */
        flashFieldGroup(els) {
            this.fieldFlashClear();
            const list = (Array.isArray(els) ? els : [els]).filter((e) => e && e.nodeType === 1);
            if (list.length === 0) {
                return;
            }
            this._flashedEls = list;
            this.$nextTick(() => {
                list[0].scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                list.forEach((el) => el.classList.add('rb-public-field-error-flash'));
                clearTimeout(this._fieldFlashTimer);
                this._fieldFlashTimer = setTimeout(() => this.fieldFlashClear(), 2000);
            });
        },

        preferredValueHint() {
            const id = this.form.preferred_contact_channel;
            const o = this.preferredOptions.find((x) => x.id === id);

            return (o && o.value_hint) ? o.value_hint : '';
        },

        preferredValuePlaceholder() {
            const id = this.form.preferred_contact_channel;
            const o = this.preferredOptions.find((x) => x.id === id);

            return (o && o.value_placeholder) ? o.value_placeholder : '';
        },

        onPhoneInput() {
            const el = this.$refs.bookingPhoneInput;
            if (! el || typeof window.TenantIntlPhone?.handleInput !== 'function') {
                return;
            }
            window.TenantIntlPhone.handleInput(el, (norm) => {
                this.form.phone = norm;
                this.scheduleHintsAfterPhone();
            });
        },

        syncPhoneFieldFromState() {
            this.$nextTick(() => {
                const el = this.$refs.bookingPhoneInput;
                if (el && typeof window.TenantIntlPhone?.syncInputDisplay === 'function') {
                    window.TenantIntlPhone.syncInputDisplay(el, this.form.phone);
                }
            });
        },

        scheduleHintsFetch() {
            clearTimeout(this.hintsTimer);
            this.hintsTimer = setTimeout(() => this.runHintsFetch(), 140);
        },

        scheduleHintsAfterPhone() {
            clearTimeout(this.hintsPhoneTimer);
            this.hintsPhoneTimer = setTimeout(() => this.runHintsFetch(), 450);
        },

        runHintsFetch() {
            if (! this.isOpen || ! this.bike?.id) {
                return;
            }
            const seq = ++this.hintsSeq;
            if (this.hintsAbort) {
                try {
                    this.hintsAbort.abort();
                } catch (e) {}
            }
            this.hintsAbort = new AbortController();
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (! csrf) {
                return;
            }

            const pad = (n) => String(n).padStart(2, '0');
            const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            const today = new Date();
            const rangeFrom = ymd(today);
            const rt = new Date(today);
            rt.setDate(rt.getDate() + 60);
            const rangeTo = ymd(rt);

            const hasFullRange = !!(this.form.start_date && this.form.end_date);
            const body = {
                motorcycle_id: this.bike.id,
                range_from: rangeFrom,
                range_to: rangeTo,
                selected_start: hasFullRange ? this.form.start_date : null,
                selected_end: hasFullRange ? this.form.end_date : null,
                phone: this.form.phone || null,
            };

            fetch('/api/tenant/booking/motorcycle-calendar-hints', {
                method: 'POST',
                signal: this.hintsAbort.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify(body),
            })
                .then((res) => res.json().then((data) => ({ res, data })))
                .then(({ res, data }) => {
                    if (seq !== this.hintsSeq) {
                        return;
                    }
                    if (! res.ok) {
                        window.TenantDatePickers?.clearModalDisableDates?.();

                        return;
                    }
                    window.TenantDatePickers?.setModalDisableDates?.(data.disabled_dates || []);
                    window.TenantDatePickers?.setModalPendingRequestHighlights?.(data.pending_request_dates || []);
                    this.hintRangeOk = data.is_range_available;
                    this.hintBusyRange = data.is_range_available === false;
                    this.hintSelfOverlap = !! data.already_booked_by_phone;
                    this.hintPendingOnRange = !! data.pending_requests_on_selected_range;
                    const ranges = data.available_ranges || [];
                    if (ranges.length === 0) {
                        this.hintRangesText = '';
                    } else {
                        this.hintRangesText = ranges
                            .slice(0, 5)
                            .map((r) => {
                                const ds = r.start.split('-').reverse().join('.');
                                const de = r.end.split('-').reverse().join('.');

                                return ds + ' — ' + de;
                            })
                            .join('; ');
                    }
                })
                .catch((e) => {
                    if (e.name === 'AbortError') {
                        return;
                    }
                    if (seq === this.hintsSeq) {
                        window.TenantDatePickers?.clearModalDisableDates?.();
                    }
                });
        },

        openModal(bikeData) {
            this.bike = {
                id: bikeData.id,
                name: bikeData.name,
                price: bikeData.price,
            };
            this.resetForm();
            const store = Alpine.store('tenantBooking');
            let start = bikeData.start || store.filters.start_date || '';
            let end = bikeData.end || store.filters.end_date || '';
            if (end && ! start) {
                end = '';
            }
            if (start && end && end < start) {
                end = '';
            }
            if (start) {
                this.form.start_date = start;
            }
            if (end) {
                this.form.end_date = end;
            }
            this.calculatePrice();
            this.isOpen = true;
            this.$nextTick(() => {
                if (typeof window.TenantDatePickers?.initModal === 'function') {
                    window.TenantDatePickers.initModal(this);
                }
                this.syncPhoneFieldFromState();
            });
        },

        closeModal() {
            this.isOpen = false;
            window.TenantDatePickers?.destroyModal?.();
            setTimeout(() => {
                this.resetForm();
            }, 300);
        },

        resetForm() {
            this.fieldFlashClear();
            clearTimeout(this._fieldFlashTimer);
            window.TenantDatePickers?.destroyModal?.();
            this.form.start_date = '';
            this.form.end_date = '';
            this.form.customer_name = '';
            this.form.phone = '';
            this.form.customer_comment = '';
            this.form.preferred_contact_channel = 'phone';
            this.form.preferred_contact_value = '';
            this.calculatedDays = 0;
            this.totalPrice = 0;
            this.isSuccess = false;
            this.errorMessage = '';
            this.hintsSeq += 1;
            this.hintBusyRange = false;
            this.hintSelfOverlap = false;
            this.hintPendingOnRange = false;
            this.hintRangeOk = null;
            this.hintRangesText = '';
            window.TenantDatePickers?.clearModalDisableDates?.();
            this.syncPhoneFieldFromState();
        },

        calculatePrice() {
            if (! this.form.start_date || ! this.form.end_date) {
                return;
            }

            const start = new Date(this.form.start_date);
            const end = new Date(this.form.end_date);

            if (end < start) {
                this.calculatedDays = 0;
                this.totalPrice = 0;

                return;
            }

            const MS_PER_DAY = 1000 * 60 * 60 * 24;
            const utc1 = Date.UTC(start.getFullYear(), start.getMonth(), start.getDate());
            const utc2 = Date.UTC(end.getFullYear(), end.getMonth(), end.getDate());

            this.calculatedDays = Math.floor((utc2 - utc1) / MS_PER_DAY) + 1;
            this.totalPrice = this.calculatedDays * this.bike.price;
        },

        async submitForm() {
            this.errorMessage = '';
            this.fieldFlashClear();
            this.calculatePrice();

            const dateInputs = [this.$refs.bookingStartDateInput, this.$refs.bookingEndDateInput].filter(Boolean);

            if (! this.form.start_date || ! this.form.end_date) {
                this.errorMessage = 'Укажите даты начала и возврата.';
                this.flashFieldGroup(dateInputs);

                return;
            }

            if (this.calculatedDays <= 0) {
                this.errorMessage = 'Дата возврата не может быть раньше даты выдачи.';
                this.flashFieldGroup(dateInputs);

                return;
            }

            if (! (this.form.customer_name || '').trim()) {
                this.errorMessage = 'Укажите ваше имя.';
                this.flashFieldGroup(this.$refs.bookingCustomerNameInput);

                return;
            }

            const phoneEl = this.$refs.bookingPhoneInput;
            const phoneRaw = (phoneEl && phoneEl.value) ? phoneEl.value : this.form.phone;
            if (typeof window.TenantIntlPhone?.normalizePhone === 'function') {
                this.form.phone = window.TenantIntlPhone.normalizePhone(phoneRaw);
                window.TenantIntlPhone.syncInputDisplay?.(phoneEl, this.form.phone);
            }
            if (typeof window.TenantIntlPhone?.validatePhone !== 'function' || ! window.TenantIntlPhone.validatePhone(this.form.phone)) {
                this.errorMessage = 'Укажите корректный номер телефона с кодом страны (для России — 10 цифр после +7 или ввод с 8).';
                this.flashFieldGroup(this.$refs.bookingPhoneInput);

                return;
            }

            if (this.selectedNeedsExtraValue()) {
                const v = (this.form.preferred_contact_value || '').trim();
                if (! v) {
                    this.errorMessage = 'Укажите контакт для выбранного способа связи (см. подсказку под полем).';
                    this.flashFieldGroup(this.$refs.bookingPrefValueInput);

                    return;
                }
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (! csrf) {
                this.errorMessage = 'Не удалось отправить заявку. Обновите страницу и попробуйте снова.';
                this.$nextTick(() => this.flashFieldGroup(this.$refs.bookingErrorBanner));

                return;
            }

            this.isLoading = true;

            try {
                const response = await fetch('/api/leads', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        motorcycle_id: this.bike.id,
                        name: this.form.customer_name,
                        phone: this.form.phone,
                        comment: this.form.customer_comment,
                        rental_date_from: this.form.start_date,
                        rental_date_to: this.form.end_date,
                        source: 'booking_form',
                        preferred_contact_channel: this.form.preferred_contact_channel,
                        preferred_contact_value: this.selectedNeedsExtraValue() ? (this.form.preferred_contact_value || '').trim() : null,
                    }),
                });

                const data = await response.json();

                if (! response.ok) {
                    throw new Error(data.message || 'Ошибка валидации или даты уже заняты.');
                }

                this.isSuccess = true;
                window.TenantDatePickers?.destroyModal?.();
            } catch (error) {
                this.errorMessage = error.message;
                this.$nextTick(() => this.flashFieldGroup(this.$refs.bookingErrorBanner));
            } finally {
                this.isLoading = false;
            }
        },
    }));
});
</script>
