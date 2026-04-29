import './tenant-intl-phone.js';
import './expertLazyMedia.js';

import { flashPublicFieldWrap, pickFirstPublicFormErrorKey } from './shared/publicFormValidation.js';
import {
    normalizeTelegramVisitorInput,
    normalizeVkVisitorInput,
    preferredChannelNeedsAsciiValue,
    preferredContactValueEmptyMessageEn,
    preferredContactValueEmptyMessageRu,
    preferredContactValueInvalidMessageEn,
    preferredContactValueInvalidMessageRu,
    stripToAsciiContactTyping,
} from './shared/visitorContactNormalize.js';
import {
    RB_PUBLIC_FORM_SUCCESS_TITLE,
    rbDispatchPublicFormSuccess,
    rbFocusPublicSuccessRoot,
    rbResolvePublicFormSuccessLead,
} from './shared/publicFormSuccessUi.js';

function expertPublicSuccessTitle(form) {
    return form?.dataset?.rbExpertEnUi === '1' ? 'Thank you!' : RB_PUBLIC_FORM_SUCCESS_TITLE;
}
const EXPERT_PROGRAM_PREFILL_SLUG_KEY = 'rentbase:expert-inquiry-program-slug';
const EXPERT_PROGRAM_PREFILL_TITLE_KEY = 'rentbase:expert-inquiry-program-title';
const EXPERT_GOAL_PREFILL_KEY = 'rentbase:expert-inquiry-goal-text';

function getStickyOffset() {
    return 72;
}

/**
 * Подстановка программы с карточки «Записаться» (select + текст цели), в т.ч. после ленивого монтирования формы.
 */
function applyExpertProgramPrefill(form, slug, title) {
    if (!form) {
        return;
    }
    const s = String(slug ?? '').trim();
    const t = String(title ?? '').trim();
    if (!s && !t) {
        return;
    }
    const sel = form.querySelector('[data-rb-expert-program]');
    if (sel && sel.tagName === 'SELECT' && s) {
        const match = Array.from(sel.options).some((o) => o.value === s);
        if (match) {
            sel.value = s;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    const goal = form.querySelector('[name="goal_text"]');
    if (goal && t && goal.value.trim() === '') {
        const enUi = form.dataset.rbExpertEnUi === '1';
        goal.value = enUi ? `Program enrollment: «${t}»` : `Запись на программу: «${t}»`;
    }
}

function tryApplyExpertGoalPrefillFromStorage(form) {
    if (!form) {
        return;
    }
    let g = '';
    try {
        g = (sessionStorage.getItem(EXPERT_GOAL_PREFILL_KEY) || '').trim();
    } catch {
        return;
    }
    if (!g) {
        return;
    }
    try {
        sessionStorage.removeItem(EXPERT_GOAL_PREFILL_KEY);
    } catch {
        /* ignore */
    }
    const goal = form.querySelector('[name="goal_text"]');
    if (goal && goal.value.trim() === '') {
        goal.value = g;
    }
}

function tryApplyExpertProgramPrefillFromStorage(form) {
    if (!form) {
        return;
    }
    let slug = '';
    let title = '';
    try {
        slug = (sessionStorage.getItem(EXPERT_PROGRAM_PREFILL_SLUG_KEY) || '').trim();
        title = (sessionStorage.getItem(EXPERT_PROGRAM_PREFILL_TITLE_KEY) || '').trim();
    } catch {
        return;
    }
    if (!slug && !title) {
        return;
    }
    try {
        sessionStorage.removeItem(EXPERT_PROGRAM_PREFILL_SLUG_KEY);
        sessionStorage.removeItem(EXPERT_PROGRAM_PREFILL_TITLE_KEY);
    } catch {
        /* ignore */
    }
    applyExpertProgramPrefill(form, slug, title);
}

function expertErrorKeyPriority(keys) {
    const first = pickFirstPublicFormErrorKey(keys);
    if (first) {
        return first;
    }
    const order = [
        'program_slug',
        'preferred_schedule',
        'district',
        'comment',
        'has_own_car',
        'transmission',
        'has_license',
        'privacy_accepted',
    ];
    const set = new Set(keys);
    for (const k of order) {
        if (set.has(k)) {
            return k;
        }
    }

    return keys[0] ?? null;
}

function appendAriaDescribedBy(el, id) {
    if (!el || !id) {
        return;
    }
    const cur = (el.getAttribute('aria-describedby') || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    if (!cur.includes(id)) {
        cur.push(id);
    }
    el.setAttribute('aria-describedby', cur.join(' '));
}

function removeAriaDescribedById(el, id) {
    if (!el || !id) {
        return;
    }
    const cur = (el.getAttribute('aria-describedby') || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    const next = cur.filter((x) => x !== id);
    if (next.length) {
        el.setAttribute('aria-describedby', next.join(' '));
    } else {
        el.removeAttribute('aria-describedby');
    }
}

function clearInlineErrors(form) {
    form.querySelectorAll('[data-rb-public-field]').forEach((wrap) => {
        const fieldName = wrap.getAttribute('data-rb-public-field') || '';
        const errId = fieldName ? `rb-inline-err-${fieldName}` : '';
        wrap.querySelectorAll('.expert-field-error').forEach((n) => n.remove());
        wrap.querySelectorAll('.expert-form-input, input, textarea, select').forEach((el) => {
            el.classList.remove('expert-form-input--error');
            el.removeAttribute('aria-invalid');
            if (errId) {
                removeAriaDescribedById(el, errId);
            }
        });
        wrap.classList.remove('expert-public-field-wrap--error');
    });
}

function setFieldError(form, fieldName, message) {
    const wrap = form.querySelector(`[data-rb-public-field="${fieldName}"]`);
    if (!wrap) {
        return;
    }
    wrap.classList.add('expert-public-field-wrap--error');
    const errId = `rb-inline-err-${fieldName}`;
    const control = wrap.querySelector('input:not([type="hidden"]):not([type="radio"]), textarea, select');
    if (control && control.type !== 'radio') {
        control.classList.add('expert-form-input--error');
        control.setAttribute('aria-invalid', 'true');
        appendAriaDescribedBy(control, errId);
    }
    const p = document.createElement('p');
    p.className = 'expert-field-error mt-1.5 text-[13px] leading-snug text-red-400/95';
    p.id = errId;
    p.setAttribute('role', 'alert');
    p.textContent = message;
    wrap.appendChild(p);
}

function initPhoneUi(form) {
    const T = window.TenantIntlPhone;
    const phoneInput = form?.querySelector('[data-rb-expert-phone]');
    const hintEl = form?.querySelector('[data-rb-expert-phone-hint]');
    if (!form || !phoneInput || !T || typeof T.attachPublicTelField !== 'function') {
        return;
    }
    T.attachPublicTelField(phoneInput, { hintEl });
}

function getSelectedPreferredChannel(form) {
    const checked = form.querySelector('input[name="preferred_contact_channel"]:checked');
    if (checked) {
        return checked.value;
    }
    const hidden = form.querySelector('input[name="preferred_contact_channel"][type="hidden"]');

    return hidden ? hidden.value : 'phone';
}

function initPreferredChannelSync(form, meta) {
    const byId = new Map(meta.map((row) => [row.id, row]));
    const valueWrap = form.querySelector('[data-rb-expert-pref-wrap]');
    const valueInput = form.querySelector('[data-rb-expert-pref-input]');
    const valueHint = form.querySelector('[data-rb-expert-pref-hint]');
    const valueLabel = form.querySelector('[data-rb-expert-pref-label]');
    const enUi = form.dataset.rbExpertEnUi === '1';
    let prev = null;

    const sync = () => {
        const id = getSelectedPreferredChannel(form);
        if (prev !== null && prev !== id && valueInput) {
            valueInput.value = '';
        }
        prev = id;
        const row = byId.get(id);
        if (!row || !valueWrap || !valueInput) {
            return;
        }
        const needValue = Boolean(row.needs_value);
        valueWrap.classList.toggle('hidden', !needValue);
        valueInput.toggleAttribute('required', needValue);
        if (valueLabel) {
            const lb = String(row.value_label ?? '').trim();
            valueLabel.textContent = lb || (enUi ? 'Contact detail' : 'Контакт для связи');
        }
        const ph = String(row.value_placeholder ?? '').trim();
        if (ph) {
            valueInput.setAttribute('placeholder', ph);
        } else {
            valueInput.removeAttribute('placeholder');
        }
        if (id === 'telegram' || id === 'vk') {
            valueInput.setAttribute('lang', 'en');
            valueInput.setAttribute('spellcheck', 'false');
            valueInput.setAttribute('autocapitalize', 'off');
        } else {
            valueInput.removeAttribute('lang');
            valueInput.removeAttribute('spellcheck');
            valueInput.removeAttribute('autocapitalize');
        }
        if (valueHint) {
            const ht = String(row.value_hint ?? '').trim();
            valueHint.textContent = ht;
            valueHint.classList.toggle('hidden', ht === '');
        }
        if (needValue && preferredChannelNeedsAsciiValue(id) && valueInput.value) {
            const stripped = stripToAsciiContactTyping(valueInput.value);
            if (stripped !== valueInput.value) {
                valueInput.value = stripped;
            }
        }
    };

    form.addEventListener('change', (e) => {
        const t = e.target;
        if (t && t.matches('input[name="preferred_contact_channel"]')) {
            sync();
        }
    });

    sync();

    return sync;
}

/** Допустимый интервал времени: 07:00–22:00, минуты кратны 5 (как у step=300). */
const SCHEDULE_MIN = '07:00';
const SCHEDULE_MAX = '22:00';
const SCHEDULE_STEP_MIN = 5;

function parseTimeToMinutes(value) {
    const m = /^(\d{1,2}):(\d{2})$/.exec(String(value).trim());
    if (!m) {
        return null;
    }
    const h = parseInt(m[1], 10);
    const min = parseInt(m[2], 10);
    if (h > 23 || min > 59) {
        return null;
    }

    return h * 60 + min;
}

function minutesToTimeString(total) {
    const h = Math.floor(total / 60);
    const min = total % 60;

    return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
}

/**
 * Приводит время к [07:00, 22:00] и к шагу 5 минут (ручной ввод и вставка).
 */
function clampAndSnapScheduleTime(value) {
    if (value === '' || value === null || value === undefined) {
        return '';
    }
    const minM = parseTimeToMinutes(SCHEDULE_MIN);
    const maxM = parseTimeToMinutes(SCHEDULE_MAX);
    if (minM === null || maxM === null) {
        return '';
    }
    let t = parseTimeToMinutes(value);
    if (t === null) {
        return '';
    }
    t = Math.min(Math.max(t, minM), maxM);
    const rel = t - minM;
    const snapped = minM + Math.round(rel / SCHEDULE_STEP_MIN) * SCHEDULE_STEP_MIN;
    const clamped = Math.min(Math.max(snapped, minM), maxM);

    return minutesToTimeString(clamped);
}

function syncPreferredScheduleHidden(form) {
    const from = form.querySelector('[data-rb-expert-schedule-from]');
    const to = form.querySelector('[data-rb-expert-schedule-to]');
    const hidden = form.querySelector('[data-rb-expert-schedule-value]');
    if (!from || !to || !hidden) {
        return;
    }
    if (from.value === '' && to.value === '') {
        hidden.value = '';

        return;
    }
    if (from.value !== '' && to.value !== '') {
        hidden.value = `${from.value} \u2013 ${to.value}`;

        return;
    }
    hidden.value = '';
}

function initPreferredScheduleInterval(form) {
    const from = form.querySelector('[data-rb-expert-schedule-from]');
    const to = form.querySelector('[data-rb-expert-schedule-to]');
    const hidden = form.querySelector('[data-rb-expert-schedule-value]');
    if (!from || !to || !hidden || form.dataset.rbPreferredScheduleBound === '1') {
        return;
    }
    form.dataset.rbPreferredScheduleBound = '1';
    const sync = () => syncPreferredScheduleHidden(form);
    const normalize = (el) => {
        if (!el || el.value === '') {
            return;
        }
        const next = clampAndSnapScheduleTime(el.value);
        if (next !== el.value) {
            el.value = next;
        }
    };
    from.addEventListener('input', sync);
    from.addEventListener('change', () => {
        normalize(from);
        sync();
    });
    from.addEventListener('blur', () => {
        normalize(from);
        sync();
    });
    to.addEventListener('input', sync);
    to.addEventListener('change', () => {
        normalize(to);
        sync();
    });
    to.addEventListener('blur', () => {
        normalize(to);
        sync();
    });
    sync();
}

/**
 * Клик по заголовку/подсказке «Удобное время» и клик по области группы — фокус на поле и нативный выбор времени (showPicker).
 * Подсветка блока — через focus-within на оболочке в Blade.
 */
function initExpertScheduleFocusUx(form) {
    if (form.dataset.rbExpertScheduleUx === '1') {
        return;
    }
    const from = form.querySelector('[data-rb-expert-schedule-from]');
    const to = form.querySelector('[data-rb-expert-schedule-to]');
    if (!from || !to) {
        return;
    }
    form.dataset.rbExpertScheduleUx = '1';

    const pickTarget = () => {
        if (from.value === '') {
            return from;
        }
        if (to.value === '') {
            return to;
        }

        return from;
    };

    const tryShowPicker = (el) => {
        if (!el || typeof el.showPicker !== 'function') {
            return;
        }
        try {
            el.showPicker();
        } catch (_) {}
    };

    const openTimePicker = () => {
        const el = pickTarget();
        el.focus();
        tryShowPicker(el);
    };

    /** Указатель по полю — сразу нативный выбор времени (Chrome/Edge и др.); с клавиатуры по-прежнему можно вводить вручную. */
    [from, to].forEach((el) => {
        el.addEventListener('pointerdown', (e) => {
            if (e.pointerType === 'mouse' || e.pointerType === 'pen' || e.pointerType === 'touch') {
                tryShowPicker(el);
            }
        });
    });

    form.querySelectorAll('[data-expert-schedule-activator]').forEach((node) => {
        node.addEventListener('click', (e) => {
            e.preventDefault();
            openTimePicker();
        });
        node.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openTimePicker();
            }
        });
    });

    const group = form.querySelector('[data-expert-schedule-time-group]');
    if (group) {
        group.addEventListener('click', (e) => {
            const t = e.target;
            if (!t || typeof t.closest !== 'function') {
                return;
            }
            if (t.matches('input[type="time"]')) {
                return;
            }
            const lab = t.closest('label');
            if (lab) {
                const fid = lab.getAttribute('for');
                if (fid) {
                    const linked = document.getElementById(fid);
                    if (linked && linked.matches('input[type="time"]')) {
                        linked.focus();
                        tryShowPicker(linked);
                    }
                }
                return;
            }
            openTimePicker();
        });
    }
}

function initPreferredValueAsciiGuard(form) {
    const el = form.querySelector('[data-rb-expert-pref-input]');
    if (!el || el.dataset.rbAsciiPrefGuard === '1') {
        return;
    }
    el.dataset.rbAsciiPrefGuard = '1';

    const applyStrip = () => {
        if (!preferredChannelNeedsAsciiValue(getSelectedPreferredChannel(form))) {
            return;
        }
        const v = el.value;
        const next = stripToAsciiContactTyping(v);
        if (next === v) {
            return;
        }
        const car = el.selectionStart ?? next.length;
        el.value = next;
        const delta = v.length - next.length;
        const pos = Math.max(0, Math.min(next.length, car - delta));
        try {
            el.setSelectionRange(pos, pos);
        } catch (_) {}
    };

    el.addEventListener('beforeinput', (e) => {
        if (!preferredChannelNeedsAsciiValue(getSelectedPreferredChannel(form))) {
            return;
        }
        if (e.isComposing) {
            return;
        }
        if (e.inputType === 'insertText' && e.data && /[^\x20-\x7E]/.test(e.data)) {
            e.preventDefault();
        }
    });

    el.addEventListener('paste', (e) => {
        if (!preferredChannelNeedsAsciiValue(getSelectedPreferredChannel(form))) {
            return;
        }
        const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
        if (!/[^\x20-\x7E]/.test(text)) {
            return;
        }
        e.preventDefault();
        const cleaned = stripToAsciiContactTyping(text);
        const start = el.selectionStart ?? 0;
        const end = el.selectionEnd ?? 0;
        const cur = el.value;
        el.value = cur.slice(0, start) + cleaned + cur.slice(end);
        const pos = start + cleaned.length;
        try {
            el.setSelectionRange(pos, pos);
        } catch (_) {}
    });

    el.addEventListener('compositionend', applyStrip);
    el.addEventListener('input', applyStrip);
}

function validateClientSide(form, meta) {
    const T = window.TenantIntlPhone;
    const byId = new Map(meta.map((row) => [row.id, row]));
    clearInlineErrors(form);

    const name = form.querySelector('[name="name"]');
    const goal = form.querySelector('[name="goal_text"]');
    const phoneInput = form.querySelector('[data-rb-expert-phone]');
    const emailInput = form.querySelector('[name="contact_email"]');
    const prefId = getSelectedPreferredChannel(form);
    const row = byId.get(prefId);
    const valueInput = form.querySelector('[data-rb-expert-pref-input]');
    const enUi = form.dataset.rbExpertEnUi === '1';
    const phoneOptional = form.dataset.rbExpertPhoneOptional === '1';

    let ok = true;
    const err = (key, ru, en) => {
        setFieldError(form, key, enUi ? en : ru);
        ok = false;
    };
    if (name && name.value.trim() === '') {
        err('name', 'Укажите имя.', 'Enter your name.');
    }
    if (goal && goal.value.trim() === '') {
        err('goal_text', 'Опишите суть вопроса.', 'Briefly describe what you need.');
    }
    if (phoneInput) {
        let phoneOk = false;
        let norm = '';
        if (T && typeof T.normalizePhone === 'function' && typeof T.validatePhone === 'function') {
            norm = T.normalizePhone(phoneInput.value);
            phoneOk = T.validatePhone(norm);
        } else {
            norm = (phoneInput.value || '').trim();
            phoneOk = norm !== '';
        }
        const emailTrim = emailInput ? String(emailInput.value || '').trim() : '';
        const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailTrim);

        if (phoneOptional) {
            if (!(phoneOk || emailOk)) {
                err('phone', 'Укажите телефон или email.', 'Enter a valid phone number or a work email.');
            }
        } else if (T) {
            if (!phoneOk) {
                err(
                    'phone',
                    'Укажите корректный телефон в международном формате (для РФ: +7 …).',
                    'Enter a valid international phone (E.164).',
                );
            }
        } else if (phoneInput.value.trim() === '') {
            err('phone', 'Укажите телефон.', 'Enter a phone number.');
        }
    }
    const scheduleSimple = form.querySelector('[data-rb-expert-schedule-simple]');
    const scheduleFrom = form.querySelector('[data-rb-expert-schedule-from]');
    const scheduleTo = form.querySelector('[data-rb-expert-schedule-to]');
    if (scheduleSimple) {
        const sv = scheduleSimple.value.trim();
        if (sv.length > 120) {
            setFieldError(form, 'preferred_schedule', 'Удобное время для связи — не длиннее 120 символов.');
            ok = false;
        }
    } else if (scheduleFrom && scheduleTo) {
        const sf = scheduleFrom.value;
        const st = scheduleTo.value;
        if ((sf !== '' && st === '') || (sf === '' && st !== '')) {
            setFieldError(
                form,
                'preferred_schedule',
                'Укажите и время «С», и «До», или оставьте оба поля пустыми.',
            );
            ok = false;
        } else if (sf !== '' && st !== '') {
            const minM = parseTimeToMinutes(SCHEDULE_MIN);
            const maxM = parseTimeToMinutes(SCHEDULE_MAX);
            const a = parseTimeToMinutes(sf);
            const b = parseTimeToMinutes(st);
            if (
                minM === null ||
                maxM === null ||
                a === null ||
                b === null ||
                a < minM ||
                a > maxM ||
                b < minM ||
                b > maxM
            ) {
                setFieldError(
                    form,
                    'preferred_schedule',
                    `Укажите время с ${SCHEDULE_MIN} до ${SCHEDULE_MAX} с шагом ${SCHEDULE_STEP_MIN} минут.`,
                );
                ok = false;
            }
        }
    }

    if (row?.needs_value && valueInput) {
        const pv = valueInput.value.trim();
        const emptyRu = preferredContactValueEmptyMessageRu(prefId);
        const emptyEn = preferredContactValueEmptyMessageEn(prefId);
        if (pv === '') {
            setFieldError(form, 'preferred_contact_value', enUi ? emptyEn : emptyRu);
            ok = false;
        } else if (prefId === 'telegram' && normalizeTelegramVisitorInput(pv) === null) {
            setFieldError(
                form,
                'preferred_contact_value',
                enUi ? preferredContactValueInvalidMessageEn('telegram') : preferredContactValueInvalidMessageRu('telegram'),
            );
            ok = false;
        } else if (prefId === 'vk' && normalizeVkVisitorInput(pv) === null) {
            setFieldError(
                form,
                'preferred_contact_value',
                enUi ? preferredContactValueInvalidMessageEn('vk') : preferredContactValueInvalidMessageRu('vk'),
            );
            ok = false;
        }
    }

    const consent = form.querySelector('input[name="privacy_accepted"][type="checkbox"]');
    if (consent && !consent.checked) {
        setFieldError(
            form,
            'privacy_accepted',
            enUi ? 'You must accept the privacy policy to continue.' : 'Нужно согласие на обработку персональных данных.',
        );
        ok = false;
    }

    if (!ok) {
        const wraps = [...form.querySelectorAll('[data-rb-public-field]')];
        const firstBad = wraps.find((w) => w.querySelector('.expert-field-error'));
        if (firstBad) {
            const fname = firstBad.getAttribute('data-rb-public-field');
            if (fname) {
                flashPublicFieldWrap(form, fname, { getStickyOffset });
            }
            const focusEl = firstBad.querySelector(
                'input:not([type="hidden"]):not([type="radio"]), textarea, select',
            );
            if (focusEl && typeof focusEl.focus === 'function') {
                requestAnimationFrame(() => {
                    try {
                        focusEl.focus({ preventScroll: true });
                    } catch {
                        focusEl.focus();
                    }
                });
            }
        }
    }

    return ok;
}

function applyServerErrors(form, errors) {
    clearInlineErrors(form);
    const keys = Object.keys(errors || {});
    if (keys.length === 0) {
        return;
    }

    for (const key of keys) {
        const msgs = errors[key];
        const msg = Array.isArray(msgs) ? msgs[0] : String(msgs);
        if (msg) {
            setFieldError(form, key, msg);
        }
    }

    const first = expertErrorKeyPriority(keys);
    if (first) {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                flashPublicFieldWrap(form, first, { getStickyOffset });
                const wrap = form.querySelector(`[data-rb-public-field="${first}"]`);
                const focusEl = wrap?.querySelector(
                    'input:not([type="hidden"]):not([type="radio"]), textarea, select',
                );
                if (focusEl && typeof focusEl.focus === 'function') {
                    try {
                        focusEl.focus({ preventScroll: true });
                    } catch {
                        focusEl.focus();
                    }
                }
            });
        });
    }
}

function fillEnrollmentModalContext(form) {
    const sp = form.querySelector('[data-rb-enrollment-source-page]');
    if (sp) {
        sp.value = window.location.pathname || '';
    }
    try {
        const params = new URLSearchParams(window.location.search);
        form.querySelectorAll('[data-rb-enrollment-utm]').forEach((el) => {
            const k = el.getAttribute('data-rb-enrollment-utm');
            if (k) {
                el.value = params.get(k) || '';
            }
        });
    } catch {
        /* ignore */
    }
}

function syncEnrollmentProgramIdFromSelect(form) {
    const sel = form.querySelector('[data-rb-expert-program]');
    const hid = form.querySelector('[data-rb-enrollment-program-id]');
    if (!sel || !hid) {
        return;
    }
    const opt = sel.selectedOptions[0];
    const pid = opt?.getAttribute('data-rb-program-db-id') || '';
    hid.value = pid;
}

/**
 * Закрытие модалки с лёгкой анимацией (без мгновенного снятия top layer).
 *
 * @param {HTMLDialogElement} dialog
 */
function scheduleCloseEnrollmentDialog(dialog) {
    if (!dialog || !dialog.open) {
        return;
    }
    /**
     * Повторный запрос закрытия (Escape, клик по фону) во время анимации или при «зависшем» флаге —
     * принудительно снимаем состояние и закрываем, иначе cancel+preventDefault оставляет модалку открытой.
     */
    if (dialog.dataset.rbEnrollmentClosing === '1') {
        dialog.classList.remove('rb-enrollment-dialog--closing');
        dialog.dataset.rbEnrollmentClosing = '';
        try {
            dialog.close();
        } catch {
            /* ignore */
        }

        return;
    }
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        dialog.classList.remove('rb-enrollment-dialog--closing');
        dialog.dataset.rbEnrollmentClosing = '';
        dialog.close();

        return;
    }
    dialog.dataset.rbEnrollmentClosing = '1';
    const panel = dialog.querySelector('.rb-program-enrollment-dialog__panel');
    const finish = () => {
        dialog.classList.remove('rb-enrollment-dialog--closing');
        dialog.dataset.rbEnrollmentClosing = '';
        try {
            dialog.close();
        } catch {
            /* ignore */
        }
    };
    dialog.classList.add('rb-enrollment-dialog--closing');
    if (!panel) {
        finish();

        return;
    }
    let done = false;
    const onEnd = (e) => {
        if (e.target !== panel) {
            return;
        }
        if (done) {
            return;
        }
        done = true;
        panel.removeEventListener('animationend', onEnd);
        finish();
    };
    panel.addEventListener('animationend', onEnd);
    window.setTimeout(() => {
        if (done) {
            return;
        }
        if (!dialog.open) {
            dialog.classList.remove('rb-enrollment-dialog--closing');
            dialog.dataset.rbEnrollmentClosing = '';

            return;
        }
        done = true;
        panel.removeEventListener('animationend', onEnd);
        finish();
    }, 450);
}

/**
 * @param {{ sourceType?: string, sourceContext?: string, programSlug?: string, programTitle?: string, goalPrefill?: string }} opts
 */
function openEnrollmentModal(opts = {}) {
    const sourceType = opts.sourceType || 'program_enrollment';
    const sourceContext = String(opts.sourceContext || '').trim();
    const programSlug = String(opts.programSlug || '').trim();
    const programTitle = String(opts.programTitle || '').trim();
    const goalPrefill = String(opts.goalPrefill || '').trim();

    const dialog = document.getElementById('rb-program-enrollment-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }
    const form = dialog.querySelector('form[data-expert-inquiry-form]');
    if (!form || form.dataset.expertInquiryBound !== '1') {
        return;
    }
    fillEnrollmentModalContext(form);
    const stEl = form.querySelector('input[name="source_type"]');
    if (stEl) {
        stEl.value = sourceType;
    }
    const scEl = form.querySelector('input[name="source_context"]');
    if (scEl) {
        scEl.value = sourceContext;
    }

    if (sourceType === 'enrollment_cta') {
        const sel = form.querySelector('[data-rb-expert-program]');
        if (sel) {
            sel.value = '';
        }
        const pidEl = form.querySelector('[data-rb-enrollment-program-id]');
        if (pidEl) {
            pidEl.value = '';
        }
        applyExpertProgramPrefill(form, '', '');
        const goal = form.querySelector('[name="goal_text"]');
        if (goal) {
            goal.value = goalPrefill;
        }
    } else {
        applyExpertProgramPrefill(form, programSlug, programTitle);
        syncEnrollmentProgramIdFromSelect(form);
    }

    dialog._rbOpenerEl = document.activeElement;
    dialog.classList.remove('rb-enrollment-dialog--closing');
    dialog.dataset.rbEnrollmentClosing = '';
    dialog.showModal();
    requestAnimationFrame(() => {
        const A11y = window.RentBaseTenantA11y;
        const nameInput = form.querySelector('input[name="name"]');
        if (nameInput && typeof nameInput.focus === 'function') {
            try {
                nameInput.focus({ preventScroll: true });
            } catch {
                nameInput.focus();
            }

            return;
        }
        const el = A11y ? A11y.firstFocusable(dialog) : null;
        if (el && typeof el.focus === 'function') {
            try {
                el.focus({ preventScroll: true });
            } catch {
                el.focus();
            }
        }
    });
}

function bindProgramEnrollmentDialogUi() {
    const dialog = document.getElementById('rb-program-enrollment-dialog');
    if (!dialog || dialog.dataset.rbEnrollmentDialogUiBound === '1') {
        return;
    }
    dialog.dataset.rbEnrollmentDialogUiBound = '1';
    dialog.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab') {
            return;
        }
        const A11y = window.RentBaseTenantA11y;
        if (!A11y || !dialog.open) {
            return;
        }
        A11y.trapTabWithin(dialog, e);
    }, true);
    dialog.addEventListener('cancel', (e) => {
        e.preventDefault();
        scheduleCloseEnrollmentDialog(dialog);
    });
    dialog.addEventListener('close', () => {
        dialog.classList.remove('rb-enrollment-dialog--closing');
        dialog.dataset.rbEnrollmentClosing = '';
        const opener = dialog._rbOpenerEl;
        dialog._rbOpenerEl = null;
        requestAnimationFrame(() => {
            if (opener && typeof opener.focus === 'function' && document.body.contains(opener)) {
                try {
                    opener.focus({ preventScroll: true });
                } catch {
                    opener.focus();
                }
            }
        });
        const root = dialog.querySelector('[data-rb-expert-inquiry-root]');
        const form = root?.querySelector('form[data-expert-inquiry-form]');
        if (!form || form.dataset.expertInquiryBound !== '1') {
            return;
        }
        const success = root.querySelector('[data-rb-expert-inquiry-success]');
        const alertBox = root.querySelector('[data-rb-expert-inquiry-alert]');
        if (success) {
            success.classList.add('hidden');
        }
        if (alertBox) {
            alertBox.classList.add('hidden');
            alertBox.textContent = '';
        }
        form.classList.remove('hidden');
        clearInlineErrors(form);
        const btn = form.querySelector('[data-rb-expert-inquiry-submit]');
        if (btn) {
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
            btn.innerHTML = form.dataset.rbExpertSubmitHtmlSnapshot || btn.innerHTML;
        }
        if (typeof form._rbExpertSyncChannel === 'function') {
            form._rbExpertSyncChannel();
        }
    });
    dialog.querySelectorAll('[data-rb-enrollment-dialog-close]').forEach((btn) => {
        btn.addEventListener('click', () => {
            scheduleCloseEnrollmentDialog(dialog);
        });
    });
    dialog.addEventListener('click', (e) => {
        const t = e.target;
        if (t && typeof t.closest === 'function' && t.closest('[data-rb-expert-inquiry-success-close]')) {
            e.preventDefault();
            scheduleCloseEnrollmentDialog(dialog);

            return;
        }
        if (t && typeof t.closest === 'function' && t.closest('.rb-program-enrollment-dialog__panel')) {
            return;
        }
        if (t && typeof t.closest === 'function' && t.closest('[data-rb-enrollment-dialog-backdrop-hit]')) {
            scheduleCloseEnrollmentDialog(dialog);
        }
    });
}

function tryApplyProgramQueryParamToMainForm() {
    let slug = '';
    try {
        slug = (new URLSearchParams(window.location.search).get('program') || '').trim();
    } catch {
        return;
    }
    if (!slug) {
        return;
    }
    const mainForm = document.querySelector('form[data-expert-inquiry-main]');
    if (!mainForm || mainForm.dataset.expertInquiryBound !== '1') {
        return;
    }
    applyExpertProgramPrefill(mainForm, slug, '');
}

function initExpertInquiryForm(form) {
    if (!form || form.dataset.expertInquiryBound === '1') {
        return;
    }
    const metaEl = form.querySelector('[data-rb-expert-channel-meta]');
    form.dataset.expertInquiryBound = '1';

    let meta = [];
    try {
        meta = JSON.parse(metaEl?.textContent || '[]');
    } catch {
        meta = [];
    }
    if (!Array.isArray(meta)) {
        meta = [];
    }

    initPhoneUi(form);
    let syncChannel = () => {};
    if (meta.length > 0) {
        syncChannel = initPreferredChannelSync(form, meta);
    }
    form._rbExpertSyncChannel = syncChannel;
    initPreferredValueAsciiGuard(form);
    initPreferredScheduleInterval(form);
    initExpertScheduleFocusUx(form);

    const progSel = form.querySelector('[data-rb-expert-program]');
    if (progSel && form.querySelector('[data-rb-enrollment-program-id]')) {
        progSel.addEventListener('change', () => syncEnrollmentProgramIdFromSelect(form));
        syncEnrollmentProgramIdFromSelect(form);
    }

    const endpoint = form.getAttribute('data-expert-inquiry-endpoint') || '';
    const defaultSuccessMessage = form.getAttribute('data-expert-inquiry-default-success') || '';
    const root = form.closest('[data-rb-expert-inquiry-root]');
    const alertEl = root?.querySelector('[data-rb-expert-inquiry-alert]') ?? null;
    const successPanel = root?.querySelector('[data-rb-expert-inquiry-success]') ?? null;
    const submitBtn = form.querySelector('[data-rb-expert-inquiry-submit]');
    if (submitBtn && !form.dataset.rbExpertSubmitHtmlSnapshot) {
        form.dataset.rbExpertSubmitHtmlSnapshot = submitBtn.innerHTML;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (alertEl) {
            alertEl.classList.add('hidden');
            alertEl.textContent = '';
        }
        if (successPanel) {
            successPanel.classList.add('hidden');
        }
        form.classList.remove('hidden');
        clearInlineErrors(form);
        syncPreferredScheduleHidden(form);

        if (!validateClientSide(form, meta)) {
            return;
        }

        syncPreferredScheduleHidden(form);
        const fd = new FormData(form);
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const submitBtnDefaultHtml = submitBtn?.innerHTML ?? '';
        const submittingLabel = form.dataset.rbExpertEnUi === '1' ? 'Sending…' : 'Отправка…';
        const networkUnavailable = form.dataset.rbExpertEnUi === '1'
            ? 'Network unavailable. Try again shortly.'
            : 'Сеть недоступна. Попробуйте позже.';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.innerHTML = submittingLabel;
        }
        let submissionOk = false;
        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token || '',
                    Accept: 'application/json',
                },
                body: fd,
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok) {
                if (res.status === 422 && body.errors && typeof body.errors === 'object') {
                    applyServerErrors(form, body.errors);
                }
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
            submissionOk = true;
            const lead = rbResolvePublicFormSuccessLead(body.message, defaultSuccessMessage);
            const pubTitle = expertPublicSuccessTitle(form);
            const titleEl = successPanel?.querySelector('[data-rb-public-form-success-title]');
            const leadEl = successPanel?.querySelector('[data-rb-public-form-success-lead]');
            if (successPanel && titleEl && leadEl) {
                titleEl.textContent = pubTitle;
                leadEl.textContent = lead;
                successPanel.classList.remove('hidden');
                form.classList.add('hidden');
                if (alertEl) {
                    alertEl.classList.add('hidden');
                }
                rbFocusPublicSuccessRoot(successPanel);
                rbDispatchPublicFormSuccess({
                    kind: form.closest('#rb-program-enrollment-dialog') ? 'expert_enrollment_modal' : 'expert_inquiry',
                    endpoint,
                });
            } else if (alertEl) {
                alertEl.textContent = `${pubTitle} ${lead}`;
                alertEl.classList.remove('hidden');
            }
            form.reset();
            syncChannel();
            const phoneInput = form.querySelector('[data-rb-expert-phone]');
            if (window.TenantIntlPhone && phoneInput) {
                window.TenantIntlPhone.syncInputDisplay(phoneInput, '');
            }
            const hintEl = form.querySelector('[data-rb-expert-phone-hint]');
            if (hintEl && window.TenantIntlPhone) {
                hintEl.textContent = window.TenantIntlPhone.phoneHelperHint('');
            }
        } catch {
            if (alertEl) {
                alertEl.textContent = networkUnavailable;
                alertEl.classList.remove('hidden');
            }
        } finally {
            if (submitBtn && !submissionOk) {
                submitBtn.disabled = false;
                submitBtn.removeAttribute('aria-busy');
                submitBtn.innerHTML = submitBtnDefaultHtml;
            }
        }
    });

    tryApplyExpertProgramPrefillFromStorage(form);
    tryApplyExpertGoalPrefillFromStorage(form);
}

function bootExpertInquiryForm() {
    document.querySelectorAll('form[data-expert-inquiry-form]').forEach((form) => {
        initExpertInquiryForm(form);
    });
    bindProgramEnrollmentDialogUi();
    tryApplyProgramQueryParamToMainForm();
}

function bindExpertProgramCardPrefill() {
    if (document.documentElement.dataset.rbExpertProgramPrefillBound === '1') {
        return;
    }
    document.documentElement.dataset.rbExpertProgramPrefillBound = '1';
    document.addEventListener(
        'click',
        (e) => {
            const t = e.target;
            if (!t || typeof t.closest !== 'function') {
                return;
            }
            const genericBtn = t.closest('[data-rb-enrollment-generic-cta]');
            if (genericBtn) {
                e.preventDefault();
                openEnrollmentModal({
                    sourceType: 'enrollment_cta',
                    sourceContext: (genericBtn.getAttribute('data-rb-enrollment-source-context') || '').trim(),
                    goalPrefill: (genericBtn.getAttribute('data-rb-enrollment-goal-prefill') || '').trim(),
                });
                return;
            }
            const modalBtn = t.closest('[data-rb-program-enrollment-cta]');
            if (modalBtn) {
                e.preventDefault();
                const slug = (modalBtn.getAttribute('data-expert-prefill-program') || '').trim();
                const title = (modalBtn.getAttribute('data-expert-prefill-program-title') || '').trim();
                openEnrollmentModal({
                    sourceType: 'program_enrollment',
                    sourceContext: 'programs_program_card_cta',
                    programSlug: slug,
                    programTitle: title,
                });
                return;
            }
            const scrollGoalA = t.closest('a[data-rb-enrollment-scroll-goal]');
            if (scrollGoalA) {
                const g = (scrollGoalA.getAttribute('data-rb-enrollment-scroll-goal') || '').trim();
                if (g) {
                    try {
                        sessionStorage.setItem(EXPERT_GOAL_PREFILL_KEY, g);
                    } catch {
                        /* ignore */
                    }
                    queueMicrotask(() => {
                        const mainForm = document.querySelector('form[data-expert-inquiry-main]');
                        if (mainForm && mainForm.dataset.expertInquiryBound === '1') {
                            tryApplyExpertGoalPrefillFromStorage(mainForm);
                        }
                    });
                }
            }
            const a = t.closest('a[data-expert-prefill-program], a[data-expert-prefill-program-title]');
            if (!a) {
                return;
            }
            const slug = (a.getAttribute('data-expert-prefill-program') || '').trim();
            const title = (a.getAttribute('data-expert-prefill-program-title') || '').trim();
            if (!slug && !title) {
                return;
            }
            try {
                if (slug) {
                    sessionStorage.setItem(EXPERT_PROGRAM_PREFILL_SLUG_KEY, slug);
                } else {
                    sessionStorage.removeItem(EXPERT_PROGRAM_PREFILL_SLUG_KEY);
                }
                if (title) {
                    sessionStorage.setItem(EXPERT_PROGRAM_PREFILL_TITLE_KEY, title);
                } else {
                    sessionStorage.removeItem(EXPERT_PROGRAM_PREFILL_TITLE_KEY);
                }
            } catch {
                /* ignore */
            }
            queueMicrotask(() => {
                const mainForm = document.querySelector('form[data-expert-inquiry-main]');
                if (mainForm && mainForm.dataset.expertInquiryBound === '1') {
                    tryApplyExpertProgramPrefillFromStorage(mainForm);
                    tryApplyExpertGoalPrefillFromStorage(mainForm);
                }
            });
        },
        true,
    );
}

bindExpertProgramCardPrefill();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootExpertInquiryForm);
} else {
    bootExpertInquiryForm();
}

/** Ленивые секции главной expert_auto (см. themes/expert_auto/pages/home.blade.php). */
document.addEventListener('rentbase:tenant-dom-mounted', () => {
    bootExpertInquiryForm();
});
