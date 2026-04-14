import './tenant-intl-phone.js';
import './expertLazyMedia.js';

import { flashPublicFieldWrap, pickFirstPublicFormErrorKey } from './shared/publicFormValidation.js';
import {
    normalizeTelegramVisitorInput,
    normalizeVkVisitorInput,
    preferredChannelNeedsAsciiValue,
    stripToAsciiContactTyping,
} from './shared/visitorContactNormalize.js';

function getStickyOffset() {
    return 72;
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
    ];
    const set = new Set(keys);
    for (const k of order) {
        if (set.has(k)) {
            return k;
        }
    }

    return keys[0] ?? null;
}

function clearInlineErrors(form) {
    form.querySelectorAll('[data-rb-public-field]').forEach((wrap) => {
        wrap.querySelectorAll('.expert-field-error').forEach((n) => n.remove());
        wrap.querySelectorAll('.expert-form-input, input, textarea, select').forEach((el) => {
            el.classList.remove('expert-form-input--error');
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
    const control = wrap.querySelector('input, textarea, select');
    if (control && control.type !== 'radio' && control.type !== 'hidden') {
        control.classList.add('expert-form-input--error');
    }
    const p = document.createElement('p');
    p.className = 'expert-field-error mt-1.5 text-[13px] leading-snug text-red-400/95';
    p.textContent = message;
    wrap.appendChild(p);
}

function initPhoneUi(form) {
    const T = window.TenantIntlPhone;
    const phoneInput = form?.querySelector('#expert-phone');
    const hintEl = form?.querySelector('#expert-phone-hint');
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
    const valueWrap = form.querySelector('#expert-pref-value-wrap');
    const valueInput = form.querySelector('#expert-pref-value');
    const valueHint = form.querySelector('#expert-pref-value-hint');
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
    const from = form.querySelector('#expert-schedule-from');
    const to = form.querySelector('#expert-schedule-to');
    const hidden = form.querySelector('#expert-schedule-value');
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
    const from = form.querySelector('#expert-schedule-from');
    const to = form.querySelector('#expert-schedule-to');
    const hidden = form.querySelector('#expert-schedule-value');
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
    const from = form.querySelector('#expert-schedule-from');
    const to = form.querySelector('#expert-schedule-to');
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
    const el = form.querySelector('#expert-pref-value');
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
    const phoneInput = form.querySelector('#expert-phone');
    const prefId = getSelectedPreferredChannel(form);
    const row = byId.get(prefId);
    const valueInput = form.querySelector('#expert-pref-value');

    let ok = true;
    if (name && name.value.trim() === '') {
        setFieldError(form, 'name', 'Укажите имя.');
        ok = false;
    }
    if (goal && goal.value.trim() === '') {
        setFieldError(form, 'goal_text', 'Опишите, что хотите улучшить.');
        ok = false;
    }
    if (phoneInput) {
        if (T) {
            const norm = T.normalizePhone(phoneInput.value);
            if (!T.validatePhone(norm)) {
                setFieldError(form, 'phone', 'Укажите корректный телефон в международном формате (для РФ: +7 …).');
                ok = false;
            }
        } else if (phoneInput.value.trim() === '') {
            setFieldError(form, 'phone', 'Укажите телефон.');
            ok = false;
        }
    }
    const scheduleFrom = form.querySelector('#expert-schedule-from');
    const scheduleTo = form.querySelector('#expert-schedule-to');
    if (scheduleFrom && scheduleTo) {
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
        if (pv === '') {
            setFieldError(form, 'preferred_contact_value', 'Заполните контакт для выбранного способа связи.');
            ok = false;
        } else if (prefId === 'telegram' && normalizeTelegramVisitorInput(pv) === null) {
            setFieldError(
                form,
                'preferred_contact_value',
                'Telegram: латинский username (5–32 символа: буквы, цифры, _) или ссылка https://t.me/…',
            );
            ok = false;
        } else if (prefId === 'vk' && normalizeVkVisitorInput(pv) === null) {
            setFieldError(
                form,
                'preferred_contact_value',
                'ВКонтакте: ссылка на профиль (https://vk.com/…) или латинский id/ник после vk.com/',
            );
            ok = false;
        }
    }

    if (!ok) {
        const wraps = [...form.querySelectorAll('[data-rb-public-field]')];
        const firstBad = wraps.find((w) => w.querySelector('.expert-field-error'));
        if (firstBad) {
            const fname = firstBad.getAttribute('data-rb-public-field');
            if (fname) {
                flashPublicFieldWrap(form, fname, { getStickyOffset });
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
            });
        });
    }
}

function bootExpertInquiryForm() {
    const form = document.getElementById('expert-inquiry-form');
    const metaEl = document.getElementById('expert-inquiry-channel-meta');
    if (!form || form.dataset.expertInquiryBound === '1') {
        return;
    }
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
    initPreferredValueAsciiGuard(form);
    initPreferredScheduleInterval(form);
    initExpertScheduleFocusUx(form);

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
        clearInlineErrors(form);
        syncPreferredScheduleHidden(form);

        if (!validateClientSide(form, meta)) {
            return;
        }

        syncPreferredScheduleHidden(form);
        const fd = new FormData(form);
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (submitBtn) {
            submitBtn.disabled = true;
        }
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
            if (alertEl) {
                alertEl.textContent =
                    typeof body.message === 'string' && body.message !== ''
                        ? body.message
                        : defaultSuccessMessage;
                alertEl.classList.remove('hidden');
            }
            form.reset();
            syncChannel();
            const phoneInput = form.querySelector('#expert-phone');
            if (window.TenantIntlPhone && phoneInput) {
                window.TenantIntlPhone.syncInputDisplay(phoneInput, '');
            }
            const hintEl = form.querySelector('#expert-phone-hint');
            if (hintEl && window.TenantIntlPhone) {
                hintEl.textContent = window.TenantIntlPhone.phoneHelperHint('');
            }
        } catch {
            if (alertEl) {
                alertEl.textContent = 'Сеть недоступна. Попробуйте позже.';
                alertEl.classList.remove('hidden');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootExpertInquiryForm);
} else {
    bootExpertInquiryForm();
}

/** Ленивые секции главной expert_auto (см. themes/expert_auto/pages/home.blade.php). */
document.addEventListener('rentbase:tenant-dom-mounted', () => {
    bootExpertInquiryForm();
});
