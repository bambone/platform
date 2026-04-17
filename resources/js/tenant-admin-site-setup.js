/**
 * Guided setup: highlight [data-setup-target] / fallbacks for active session (payload in #tenant-site-setup-payload).
 */
function isSetupElementVisible(el) {
    if (!el || !(el instanceof Element)) {
        return false;
    }
    const style = window.getComputedStyle(el);
    if (style.visibility === 'hidden' || style.display === 'none') {
        return false;
    }
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
}

/**
 * @param {string} selector
 * @returns {Element|null}
 */
function firstVisibleMatch(selector) {
    const list = document.querySelectorAll(selector);
    for (let i = 0; i < list.length; i += 1) {
        const el = list[i];
        if (isSetupElementVisible(el)) {
            return el;
        }
    }
    return null;
}

/**
 * @param {string} attr
 * @param {string} value
 * @returns {Element|null}
 */
function firstVisibleByDataAttr(attr, value) {
    const esc =
        typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
            ? CSS.escape(value)
            : value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    return firstVisibleMatch(`[${attr}="${esc}"]`);
}

/**
 * @param {Record<string, unknown>} payload
 * @returns {Element|null}
 */
function resolveSetupHighlightTarget(payload) {
    const primary = payload.target_key;
    if (!primary || typeof primary !== 'string') {
        return null;
    }

    const sectionId =
        typeof payload.settings_section_id === 'string' && payload.settings_section_id.length > 0
            ? payload.settings_section_id
            : '';

    const fallbackKeys = Array.isArray(payload.target_fallback_keys) ? payload.target_fallback_keys : [];
    const keysToTry = [primary, ...fallbackKeys.filter((k) => typeof k === 'string' && k.length > 0)];

    for (let i = 0; i < keysToTry.length; i += 1) {
        const key = keysToTry[i];
        const esc =
            typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
                ? CSS.escape(key)
                : key.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        const matches = document.querySelectorAll(`[data-setup-target="${esc}"]`);
        for (let j = 0; j < matches.length; j += 1) {
            const el = matches[j];
            if (isSetupElementVisible(el)) {
                return el;
            }
        }
    }

    if (sectionId !== '') {
        const sec = firstVisibleByDataAttr('data-setup-section', sectionId);
        if (sec) {
            return sec;
        }
    }

    const sectionTypes = Array.isArray(payload.page_builder_fallback_section_types)
        ? payload.page_builder_fallback_section_types
        : [];
    for (let s = 0; s < sectionTypes.length; s += 1) {
        const id = sectionTypes[s];
        if (typeof id !== 'string' || id === '') {
            continue;
        }
        const inCatalog = firstVisibleByDataAttr('data-setup-section-type', id);
        if (inCatalog) {
            return inCatalog;
        }
    }

    const action = payload.fallback_setup_action;
    if (typeof action === 'string' && action !== '') {
        const byAction = firstVisibleByDataAttr('data-setup-action', action);
        if (byAction) {
            return byAction;
        }
    }

    return null;
}

/**
 * Поднять подсветку с input/внутреннего узла к обёртке поля Filament (лейбл + контрол + ошибки).
 *
 * @param {Element} raw
 * @returns {Element|null}
 */
function primaryHighlightElement(raw) {
    if (!raw || !(raw instanceof Element)) {
        return null;
    }
    if (raw.matches('.fi-fo-field')) {
        return isSetupElementVisible(raw) ? raw : null;
    }
    const field = raw.closest('.fi-fo-field');
    if (field && isSetupElementVisible(field)) {
        return field;
    }
    return isSetupElementVisible(raw) ? raw : null;
}

/**
 * Мягкий контекст секции (например блок «Контакты» / «Основное»), если поле внутри [data-setup-section].
 *
 * @param {Element} primaryEl
 * @returns {Element|null}
 */
function sectionContextElement(primaryEl) {
    if (!primaryEl || !(primaryEl instanceof Element)) {
        return null;
    }
    const sec = primaryEl.closest('[data-setup-section]');
    if (!sec || sec === primaryEl) {
        return null;
    }
    return isSetupElementVisible(sec) ? sec : null;
}

/**
 * На странице настроек активная вкладка хранится в query (`settings_tab`). Подставляем нужное значение до поиска целей.
 *
 * @param {Record<string, unknown>} payload
 * @returns {boolean} true если будет перезагрузка страницы
 */
function syncSettingsTabQueryIfNeeded(payload) {
    if (payload.on_target_route !== true) {
        return false;
    }
    if (payload.route_name !== 'filament.admin.pages.settings') {
        return false;
    }
    const tab = payload.settings_tab;
    if (typeof tab !== 'string' || tab === '') {
        return false;
    }
    const u = new URL(window.location.href);
    if (u.searchParams.get('settings_tab') === tab) {
        return false;
    }
    u.searchParams.set('settings_tab', tab);
    window.location.replace(u.toString());

    return true;
}

function clearTenantSiteSetupHighlights() {
    document.querySelectorAll('[data-setup-highlighted]').forEach((el) => {
        el.classList.remove('fi-ts-setup-highlight', 'fi-ts-setup-highlight-section');
        el.removeAttribute('data-setup-highlighted');
    });
    document.querySelectorAll('.fi-ts-setup-inline-mount').forEach((el) => {
        el.remove();
    });
}

/**
 * Вставить карточку после заголовка секции, в слот, либо перед полем.
 *
 * @param {Element} sectionEl
 * @param {Element} node
 * @param {Element} primaryEl
 * @returns {boolean}
 */
function insertSetupCardInSection(sectionEl, node, primaryEl) {
    const slot = sectionEl.querySelector(':scope [data-setup-inline-slot="top"]');
    if (slot) {
        slot.prepend(node);
        return true;
    }

    const labelCtn = sectionEl.querySelector(':scope > .fi-sc-section-label-ctn');
    if (labelCtn) {
        labelCtn.insertAdjacentElement('afterend', node);
        return true;
    }

    const heading = sectionEl.querySelector(':scope h2, :scope h3');
    if (heading) {
        const headerBlock = heading.parentElement;
        if (headerBlock && sectionEl.contains(headerBlock) && headerBlock !== sectionEl) {
            headerBlock.insertAdjacentElement('afterend', node);
        } else {
            heading.insertAdjacentElement('afterend', node);
        }
        return true;
    }

    if (sectionEl.contains(primaryEl)) {
        primaryEl.parentNode?.insertBefore(node, primaryEl);
        return true;
    }

    sectionEl.insertBefore(node, sectionEl.firstChild);
    return true;
}

/**
 * @param {Record<string, unknown>} payload
 * @param {Element} primaryEl
 * @param {Element | null} sectionEl
 */
function mountInlineSetupCardIfNeeded(payload, primaryEl, sectionEl) {
    if (payload.on_target_route !== true) {
        return;
    }
    const tpl = document.getElementById('tenant-site-setup-inline-template');
    if (!tpl || !(tpl instanceof HTMLTemplateElement)) {
        return;
    }
    const frag = tpl.content.cloneNode(true);
    const node = frag.firstElementChild;
    if (!node || !(node instanceof Element)) {
        return;
    }
    if (sectionEl && sectionEl instanceof Element) {
        insertSetupCardInSection(sectionEl, node, primaryEl);
    } else {
        primaryEl.parentNode?.insertBefore(node, primaryEl);
    }
}

/**
 * @returns {string}
 */
function floatingFallbackStorageKey() {
    return `fi-ts-setup-float-dismiss:${window.location.pathname}`;
}

/**
 * Fallback снизу: только если нет верхней полосы; не показывать повторно после «Скрыть» до смены URL.
 */
function mountInlineSetupFallbackFloating() {
    if (document.getElementById('tenant-site-setup-bar')) {
        return;
    }
    try {
        if (sessionStorage.getItem(floatingFallbackStorageKey()) === '1') {
            return;
        }
    } catch {
        /* sessionStorage недоступен */
    }

    const tpl = document.getElementById('tenant-site-setup-inline-template');
    if (!tpl || !(tpl instanceof HTMLTemplateElement)) {
        return;
    }
    const node = tpl.content.firstElementChild;
    if (!node || !(node instanceof Element)) {
        return;
    }
    const clone = node.cloneNode(true);
    clone.classList.add('fi-ts-setup-inline-card-floating');

    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.className = 'fi-ts-setup-float-dismiss';
    dismiss.setAttribute('aria-label', 'Скрыть подсказку быстрого запуска');
    dismiss.textContent = '×';
    dismiss.addEventListener('click', () => {
        clone.remove();
        try {
            sessionStorage.setItem(floatingFallbackStorageKey(), '1');
        } catch {
            /* ignore */
        }
    });
    clone.appendChild(dismiss);

    document.body.appendChild(clone);
}

function initTenantSiteSetup() {
    const payloadEl = document.getElementById('tenant-site-setup-payload');
    if (!payloadEl) {
        document.body.classList.remove('fi-ts-setup-active');
        clearTenantSiteSetupHighlights();
        document.body.style.paddingTop = '';
        return;
    }

    let payload;
    try {
        payload = JSON.parse(payloadEl.textContent || '{}');
    } catch {
        return;
    }

    document.body.classList.add('fi-ts-setup-active');

    clearTenantSiteSetupHighlights();

    if (syncSettingsTabQueryIfNeeded(payload)) {
        return;
    }

    const rawTarget = resolveSetupHighlightTarget(payload);
    if (!rawTarget) {
        const key = payload.target_key;
        if (key && window.console && console.info) {
            console.info('[tenant-site-setup] target not found', key);
        }
        if (payload.on_target_route === true) {
            mountInlineSetupFallbackFloating();
        }
        const barOnly = document.getElementById('tenant-site-setup-bar');
        const topOffset = barOnly ? parseFloat(getComputedStyle(barOnly).top) || 0 : 0;
        const barH = barOnly ? barOnly.offsetHeight : 0;
        if (topOffset + barH > 0) {
            document.body.style.paddingTop = `${topOffset + barH}px`;
        }
        return;
    }

    const primaryEl = primaryHighlightElement(rawTarget);
    if (!primaryEl) {
        return;
    }

    primaryEl.classList.add('fi-ts-setup-highlight');
    primaryEl.setAttribute('data-setup-highlighted', 'primary');

    const sectionEl = sectionContextElement(primaryEl);
    if (sectionEl) {
        sectionEl.classList.add('fi-ts-setup-highlight-section');
        sectionEl.setAttribute('data-setup-highlighted', 'section');
    }

    mountInlineSetupCardIfNeeded(payload, primaryEl, sectionEl);

    const bar = document.getElementById('tenant-site-setup-bar');
    const barH = bar ? bar.offsetHeight : 0;
    const topOffset = bar ? parseFloat(getComputedStyle(bar).top) || 0 : 0;
    if (topOffset + barH > 0) {
        document.body.style.paddingTop = `${topOffset + barH}px`;
    }
    const scheduleScroll = () => {
        const top = primaryEl.getBoundingClientRect().top + window.scrollY - topOffset - barH - 12;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    };
    if (payload.on_target_route === true) {
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(scheduleScroll);
        });
    } else {
        scheduleScroll();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTenantSiteSetup);
} else {
    initTenantSiteSetup();
}

document.addEventListener('livewire:navigated', initTenantSiteSetup);
