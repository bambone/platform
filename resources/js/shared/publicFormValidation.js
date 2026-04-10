/**
 * Публичные формы: приоритет первой ошибки Laravel, плавный скролл с учётом sticky-шапки, общая подсветка.
 */

export const publicFormFieldErrorPriority = [
    'intent',
    'name',
    'email',
    'preferred_contact_channel',
    'phone',
    'preferred_contact_value',
    'message',
    'company_site',
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_content',
    'utm_term',
];

/**
 * @param  {string[]}  keys
 */
export function pickFirstPublicFormErrorKey(keys) {
    if (!Array.isArray(keys) || keys.length === 0) {
        return null;
    }
    const set = new Set(keys);
    for (const k of publicFormFieldErrorPriority) {
        if (set.has(k)) {
            return k;
        }
    }

    return keys[0];
}

/**
 * @param  {Element}  el
 * @param  {() => number}  getStickyOffset
 */
export function scrollElementBelowStickyHeader(el, getStickyOffset) {
    const offset = typeof getStickyOffset === 'function' ? getStickyOffset() : 72;
    const rect = el.getBoundingClientRect();
    const top = rect.top + window.scrollY - offset - 12;
    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
}

const defaultFlashClass = 'rb-public-field-error-flash';

/**
 * @param  {ParentNode}  form
 * @param  {string}  fieldName  data-rb-public-field value
 * @param  {{ getStickyOffset?: () => number, durationMs?: number, flashClass?: string, afterScrollDelayMs?: number }}  options
 * @returns {boolean}
 */
export function flashPublicFieldWrap(form, fieldName, options = {}) {
    const wrap = form.querySelector(`[data-rb-public-field="${fieldName}"]`);
    if (!wrap || wrap.nodeType !== 1) {
        return false;
    }

    const {
        getStickyOffset,
        durationMs = 2000,
        flashClass = defaultFlashClass,
        afterScrollDelayMs = 0,
    } = options;

    scrollElementBelowStickyHeader(wrap, getStickyOffset || (() => 72));

    const w = wrap;
    if (w._rbPublicFieldFlashTimer) {
        clearTimeout(w._rbPublicFieldFlashTimer);
    }

    const applyFlash = () => {
        wrap.classList.add(flashClass);
        w._rbPublicFieldFlashTimer = setTimeout(() => {
            wrap.classList.remove(flashClass);
            w._rbPublicFieldFlashTimer = null;
        }, durationMs);
    };

    if (afterScrollDelayMs > 0) {
        setTimeout(applyFlash, afterScrollDelayMs);
    } else {
        applyFlash();
    }

    return true;
}

/**
 * @param  {{ formSelector: string, errorKeysScriptId: string, getStickyOffset?: () => number, durationMs?: number, afterScrollDelayMs?: number }}  options
 */
export function initPublicFormValidationErrorsFromJson(options) {
    const { formSelector, errorKeysScriptId, getStickyOffset, durationMs, afterScrollDelayMs } = options;
    const script = document.getElementById(errorKeysScriptId);
    const form = document.querySelector(formSelector);
    if (!script || !form) {
        return;
    }

    let keys = [];
    try {
        keys = JSON.parse(script.textContent || '[]');
    } catch {
        return;
    }
    if (!Array.isArray(keys) || keys.length === 0) {
        return;
    }

    const first = pickFirstPublicFormErrorKey(keys);
    if (!first) {
        return;
    }

    // Два кадра: после sync каналов связи браузер успевает пересчитать высоту блоков.
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            flashPublicFieldWrap(form, first, {
                getStickyOffset,
                durationMs,
                afterScrollDelayMs: afterScrollDelayMs ?? 0,
            });
        });
    });
}
