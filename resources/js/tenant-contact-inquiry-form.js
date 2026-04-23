/**
 * Публичная форма «Контакты» (AF-003): POST /api/tenant/contact-inquiry → CRM pipeline.
 */
import './tenant-intl-phone.js';

import { flashPublicFieldWrap, pickFirstPublicFormErrorKey } from './shared/publicFormValidation.js';
import {
    normalizeTelegramVisitorInput,
    normalizeVkVisitorInput,
    preferredChannelNeedsAsciiValue,
    preferredContactValueEmptyMessageRu,
    preferredContactValueInvalidMessageRu,
    stripToAsciiContactTyping,
} from './shared/visitorContactNormalize.js';
import {
    RB_PUBLIC_FORM_SUCCESS_TITLE,
    rbDispatchPublicFormSuccess,
    rbFocusPublicSuccessRoot,
    rbResolvePublicFormSuccessLead,
} from './shared/publicFormSuccessUi.js';

function getStickyOffset() {
    return 72;
}

function clearInlineErrors(form) {
    form.querySelectorAll('[data-rb-public-field]').forEach((wrap) => {
        const fieldName = wrap.getAttribute('data-rb-public-field') || '';
        const errId = fieldName ? `rb-ci-inline-err-${fieldName}` : '';
        wrap.querySelectorAll('.rb-ci-field-error').forEach((n) => n.remove());
        wrap.querySelectorAll('input, textarea, select').forEach((el) => {
            el.classList.remove('rb-ci-input--error');
            el.removeAttribute('aria-invalid');
            if (errId && el.getAttribute('aria-describedby')?.includes(errId)) {
                const cur = (el.getAttribute('aria-describedby') || '')
                    .split(/\s+/)
                    .filter((x) => x && x !== errId);
                if (cur.length) {
                    el.setAttribute('aria-describedby', cur.join(' '));
                } else {
                    el.removeAttribute('aria-describedby');
                }
            }
        });
        wrap.classList.remove('rb-ci-field-wrap--error');
    });
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

function setFieldError(form, fieldName, message) {
    const wrap = form.querySelector(`[data-rb-public-field="${fieldName}"]`);
    if (!wrap) {
        return;
    }
    const errClass =
        form.getAttribute('data-rb-contact-inquiry-field-error-class') || 'text-red-400';
    wrap.classList.add('rb-ci-field-wrap--error');
    const errId = `rb-ci-inline-err-${fieldName}`;
    const control = wrap.querySelector(
        'input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"]), textarea, select',
    );
    if (control && control.type !== 'radio') {
        control.classList.add('rb-ci-input--error');
        control.setAttribute('aria-invalid', 'true');
        appendAriaDescribedBy(control, errId);
    }
    const p = document.createElement('p');
    p.className = `rb-ci-field-error mt-1.5 text-[13px] leading-snug ${errClass}`;
    p.id = errId;
    p.setAttribute('role', 'alert');
    p.textContent = message;
    wrap.appendChild(p);
}

function initPhoneUi(form) {
    const T = window.TenantIntlPhone;
    const phoneInput = form.querySelector('[data-rb-contact-inquiry-phone]');
    const hintEl = form.querySelector('[data-rb-contact-inquiry-hint]');
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
    const valueWrap = form.querySelector('[data-rb-pref-value-wrap]');
    const valueInput = form.querySelector('[data-rb-pref-value-input]');
    const valueHint = form.querySelector('[data-rb-pref-value-hint]');
    const valueLabel = form.querySelector('[data-rb-pref-value-label]');
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
            valueLabel.textContent = lb || 'Контакт для связи';
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

    const onPreferredChange = (e) => {
        const t = e.target;
        if (t && t.matches('input[name="preferred_contact_channel"]')) {
            sync();
        }
    };
    form.addEventListener('change', onPreferredChange);
    form.addEventListener('input', onPreferredChange);

    sync();

    return sync;
}

function initPreferredValueAsciiGuard(form) {
    const el = form.querySelector('[data-rb-pref-value-input]');
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

function validateClientSide(form, meta, consentRequired) {
    const T = window.TenantIntlPhone;
    const byId = new Map(meta.map((row) => [row.id, row]));
    clearInlineErrors(form);

    const name = form.querySelector('[name="name"]');
    const message = form.querySelector('[name="message"]');
    const phoneInput = form.querySelector('[data-rb-contact-inquiry-phone]');
    const prefId = getSelectedPreferredChannel(form);
    const row = byId.get(prefId);
    const valueInput = form.querySelector('[data-rb-pref-value-input]');
    const email = form.querySelector('[name="email"]');
    const consent = form.querySelector('[name="consent_accepted"]');

    let ok = true;
    if (name && name.value.trim() === '') {
        setFieldError(form, 'name', 'Укажите имя.');
        ok = false;
    }
    if (message && message.value.trim() === '') {
        setFieldError(form, 'message', 'Напишите сообщение.');
        ok = false;
    } else if (message && message.value.trim().length < 3) {
        setFieldError(form, 'message', 'Сообщение слишком короткое.');
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
    if (email && email.offsetParent !== null && email.value.trim() !== '') {
        const em = email.value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) {
            setFieldError(form, 'email', 'Укажите корректный email.');
            ok = false;
        }
    }
    if (consentRequired && consent && !consent.checked) {
        setFieldError(form, 'consent_accepted', 'Нужно согласие на обработку данных.');
        ok = false;
    }

    if (row?.needs_value && valueInput) {
        const pv = valueInput.value.trim();
        if (pv === '') {
            setFieldError(form, 'preferred_contact_value', preferredContactValueEmptyMessageRu(prefId));
            ok = false;
        } else if (prefId === 'telegram' && normalizeTelegramVisitorInput(pv) === null) {
            setFieldError(form, 'preferred_contact_value', preferredContactValueInvalidMessageRu('telegram'));
            ok = false;
        } else if (prefId === 'vk' && normalizeVkVisitorInput(pv) === null) {
            setFieldError(form, 'preferred_contact_value', preferredContactValueInvalidMessageRu('vk'));
            ok = false;
        }
    }

    const serviceSelect = form.querySelector('select[name="inquiry_service_slug"]');
    if (serviceSelect && (serviceSelect.value === '' || serviceSelect.value === null)) {
        setFieldError(form, 'inquiry_service_slug', 'Выберите услугу.');
        ok = false;
    }

    if (!ok) {
        const wraps = [...form.querySelectorAll('[data-rb-public-field]')];
        const firstBad = wraps.find((w) => w.querySelector('.rb-ci-field-error'));
        if (firstBad) {
            const fname = firstBad.getAttribute('data-rb-public-field');
            if (fname) {
                flashPublicFieldWrap(form, fname, { getStickyOffset });
            }
            const focusEl = firstBad.querySelector(
                'input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"]), textarea, select',
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

    const first = pickFirstPublicFormErrorKey(keys);
    if (first) {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                flashPublicFieldWrap(form, first, { getStickyOffset });
                const wrap = form.querySelector(`[data-rb-public-field="${first}"]`);
                const focusEl = wrap?.querySelector(
                    'input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"]), textarea, select',
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

function appendUtmToFormData(fd) {
    try {
        const u = new URL(window.location.href);
        for (const k of ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']) {
            const v = u.searchParams.get(k);
            if (v) {
                fd.set(k, v);
            }
        }
    } catch {
        /* ignore */
    }
}

function bootContactInquiryForm(form) {
    if (!form || form.dataset.rbContactInquiryBound === '1') {
        return;
    }
    form.dataset.rbContactInquiryBound = '1';

    const root = form.closest('[data-rb-contact-inquiry-root]');
    const metaEl =
        form.querySelector('[data-rb-contact-inquiry-channel-meta]') ??
        (root && root.querySelector('[data-rb-contact-inquiry-channel-meta]'));
    let meta = [];
    try {
        meta = JSON.parse(metaEl?.textContent || '[]');
    } catch {
        meta = [];
    }
    if (!Array.isArray(meta)) {
        meta = [];
    }

    const consentRequired = form.getAttribute('data-rb-contact-inquiry-consent') === '1';

    initPhoneUi(form);
    const msgEl = form.querySelector('[name="message"]');
    const prefillMsg = form.getAttribute('data-rb-contact-inquiry-prefill-message') || '';
    if (msgEl && prefillMsg && !String(msgEl.value || '').trim()) {
        msgEl.value = prefillMsg;
    }
    let syncChannel = () => {};
    const showPreferred = form.getAttribute('data-rb-contact-inquiry-show-preferred') !== '0';
    if (showPreferred && meta.length > 0) {
        syncChannel = initPreferredChannelSync(form, meta);
    }
    initPreferredValueAsciiGuard(form);

    const endpoint = form.getAttribute('data-rb-contact-inquiry-endpoint') || '';
    const defaultSuccessMessage = form.getAttribute('data-rb-contact-inquiry-default-success') || '';
    const alertEl = form.closest('[data-rb-contact-inquiry-root]')?.querySelector('[data-rb-contact-inquiry-alert]');
    const successPanel = form.closest('[data-rb-contact-inquiry-root]')?.querySelector('[data-rb-contact-inquiry-success]');
    const submitBtn = form.querySelector('[data-rb-contact-inquiry-submit]');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (alertEl) {
            alertEl.classList.add('hidden');
            alertEl.textContent = '';
        }
        clearInlineErrors(form);

        if (!validateClientSide(form, meta, consentRequired)) {
            return;
        }

        const fd = new FormData(form);
        appendUtmToFormData(fd);

               const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const submitBtnDefaultHtml = submitBtn?.innerHTML ?? '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.innerHTML = 'Отправка…';
        }
        let submissionOk = false;
        let successUiShown = false;
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
            const titleEl = successPanel?.querySelector('[data-rb-public-form-success-title]');
            const leadEl = successPanel?.querySelector('[data-rb-public-form-success-lead]');
            if (successPanel && titleEl && leadEl) {
                titleEl.textContent = RB_PUBLIC_FORM_SUCCESS_TITLE;
                leadEl.textContent = lead;
                successPanel.classList.remove('hidden');
                form.classList.add('hidden');
                successUiShown = true;
                if (alertEl) {
                    alertEl.classList.add('hidden');
                }
                rbFocusPublicSuccessRoot(successPanel);
                rbDispatchPublicFormSuccess({ kind: 'contact_inquiry', endpoint });
            } else {
                if (alertEl) {
                    alertEl.textContent = lead;
                    alertEl.classList.remove('hidden');
                } else {
                    window.alert(lead);
                }
            }
            form.reset();
            syncChannel();
            const phoneInput = form.querySelector('[data-rb-contact-inquiry-phone]');
            if (window.TenantIntlPhone && phoneInput) {
                window.TenantIntlPhone.syncInputDisplay(phoneInput, '');
            }
            const hintEl = form.querySelector('[data-rb-contact-inquiry-hint]');
            if (hintEl && window.TenantIntlPhone) {
                hintEl.textContent = window.TenantIntlPhone.phoneHelperHint('');
            }
        } catch {
            if (alertEl) {
                alertEl.textContent = 'Сеть недоступна. Попробуйте позже.';
                alertEl.classList.remove('hidden');
            }
        } finally {
            if (submitBtn && (!submissionOk || !successUiShown)) {
                submitBtn.disabled = false;
                submitBtn.removeAttribute('aria-busy');
                submitBtn.innerHTML = submitBtnDefaultHtml;
            }
        }
    });
}

function bootAllContactInquiryForms() {
    document.querySelectorAll('form[data-rb-contact-inquiry-form]').forEach((form) => {
        bootContactInquiryForm(form);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAllContactInquiryForms);
} else {
    bootAllContactInquiryForms();
}

document.addEventListener('rentbase:tenant-dom-mounted', () => {
    bootAllContactInquiryForms();
});
