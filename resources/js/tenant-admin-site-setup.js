/**
 * Guided setup: подсветка целей guided-сессии (payload в [data-tenant-site-setup=payload]).
 *
 * Блоки: lifecycle · DOM context · target resolution · auto-open editor · highlight/scroll/focus · floating · bar offset · debug
 */

let activeSetupResolveAbort = null;
let barResizeObserver = null;

/** @type {string|null} */
let guidedFormBaseline = null;
/** @type {Record<string, unknown>|null} */
let lastGuidedPayloadForDirty = null;
let guidedSubmitGuardBound = false;
let guidedBeforeUnloadBound = false;
/** @type {ReturnType<typeof setTimeout>|null} */
let morphInitTimer = null;
let livewireMorphHookRegistered = false;

/** Стабилизация baseline «сохранить → дальше»: токен шага + окно после init (без фиксированных 240ms). */
let guidedBaselineSessionToken = 0;
let guidedBaselineInitAt = 0;
/** @type {ReturnType<typeof setTimeout>|null} */
let guidedBaselineMorphTimer = null;
const guidedBaselineMorphDebounceMs = 80;
const guidedBaselineMorphWindowMs = 700;
/** Пользователь успел тронуть форму до «позднего» снимка после morph — не перезаписывать baseline. */
let guidedBaselineTouchBeforeLock = false;
let guidedBaselineTouchBound = false;
let guidedBaselineMorphHookRegistered = false;
let tenantSetupDialogControlsBound = false;

// ——— lifecycle / reset ———

function abortActiveSetupResolve() {
    if (typeof activeSetupResolveAbort === 'function') {
        activeSetupResolveAbort();
        activeSetupResolveAbort = null;
    }
}

function clearTenantSiteSetupHighlights() {
    document.querySelectorAll('[data-setup-highlighted]').forEach((el) => {
        el.classList.remove('fi-ts-setup-highlight', 'fi-ts-setup-highlight-section');
        el.removeAttribute('data-setup-highlighted');
    });
    document.querySelectorAll('.fi-ts-setup-inline-mount').forEach((el) => {
        el.remove();
    });
    document.querySelectorAll('.fi-ts-setup-inline-card.fi-ts-setup-inline-card-floating').forEach((el) => {
        el.remove();
    });
}

function resetTenantSiteSetupUi() {
    abortActiveSetupResolve();
    if (barResizeObserver) {
        barResizeObserver.disconnect();
        barResizeObserver = null;
    }
    clearTenantSiteSetupHighlights();
    document.getElementById('tenant-site-setup-dev-debug')?.remove();
    document.body.classList.remove('fi-ts-setup-active', 'fi-ts-setup-use-top-offset');
    document.documentElement.style.setProperty('--fi-ts-setup-top-offset', '0px');
}

// ——— DOM context (single-instance / последний root) ———

/**
 * @returns {HTMLElement|null}
 */
function getTenantSiteSetupRoot() {
    const list = document.querySelectorAll('[data-tenant-site-setup-root]');
    if (list.length === 0) {
        return null;
    }
    return list[list.length - 1];
}

/**
 * @param {HTMLElement|null} root
 * @returns {HTMLScriptElement|null}
 */
function getPayloadScriptEl(root) {
    const local = root?.querySelector('[data-tenant-site-setup="payload"]');
    if (local instanceof HTMLScriptElement) {
        return local;
    }
    const all = document.querySelectorAll('[data-tenant-site-setup="payload"]');
    return all.length ? all[all.length - 1] : null;
}

/**
 * @param {HTMLElement|null} root
 * @returns {HTMLElement|null}
 */
function getSetupBarEl(root) {
    const local = root?.querySelector('[data-tenant-site-setup="bar"]');
    if (local instanceof HTMLElement) {
        return local;
    }
    const all = document.querySelectorAll('[data-tenant-site-setup="bar"]');
    return all.length ? all[all.length - 1] : null;
}

/**
 * @param {HTMLElement|null} root
 * @returns {HTMLTemplateElement|null}
 */
function getInlineTemplateEl(root) {
    const local = root?.querySelector('[data-tenant-site-setup="inline-template"]');
    if (local instanceof HTMLTemplateElement) {
        return local;
    }
    const all = document.querySelectorAll('[data-tenant-site-setup="inline-template"]');
    return all.length ? all[all.length - 1] : null;
}

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
 * Первый видимый slide-over редактора (при нескольких узлах в DOM — не опираться на querySelector).
 *
 * @returns {HTMLElement|null}
 */
function getVisiblePageBuilderEditor() {
    const list = document.querySelectorAll('.page-sections-builder-editor');
    for (let i = 0; i < list.length; i += 1) {
        const el = list[i];
        if (el instanceof HTMLElement && isSetupElementVisible(el)) {
            return el;
        }
    }
    return null;
}

/**
 * Id секции в открытом slide-over: атрибут data-setup-editor-section-id на корне .page-sections-builder-editor
 * (resources/views/livewire/tenant/page-sections-builder.blade.php). Обязателен в разметке; см. PageSectionsBuilderEditorDomContractTest.
 *
 * @returns {number|null}
 */
function getOpenEditorSectionIdFromDom() {
    const editor = getVisiblePageBuilderEditor();
    if (!editor) {
        return null;
    }
    const raw = editor.getAttribute('data-setup-editor-section-id');
    if (raw === null || String(raw).trim() === '') {
        return null;
    }
    const n = parseInt(String(raw).trim(), 10);
    return Number.isFinite(n) && n > 0 ? n : null;
}

/**
 * Где искать primary (и settings-section): сначала editor, затем document — чтобы «чужой» editor не скрывал карточки списка.
 *
 * @returns {(Document|Element)[]}
 */
function getPrimarySearchScopes() {
    const editor = getVisiblePageBuilderEditor();
    if (editor) {
        return [editor, document];
    }
    return [document];
}

/** Fallback по section-type / action — в основном DOM builder (каталог, список), не только внутри slide-over. */
function getFallbackSearchScope() {
    return document;
}

function queryAllInScope(scope, selector) {
    if (scope === document || scope === document.documentElement) {
        return document.querySelectorAll(selector);
    }
    return scope.querySelectorAll(selector);
}

function firstVisibleMatchInScope(scope, selector) {
    const list = queryAllInScope(scope, selector);
    for (let i = 0; i < list.length; i += 1) {
        const el = list[i];
        if (isSetupElementVisible(el)) {
            return el;
        }
    }
    return null;
}

function firstVisibleByDataAttrInScope(scope, attr, value) {
    const esc =
        typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
            ? CSS.escape(value)
            : value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    return firstVisibleMatchInScope(scope, `[${attr}="${esc}"]`);
}

function cssEscapeAttrValue(value) {
    return typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
        ? CSS.escape(value)
        : String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

// ——— target resolution ———

/**
 * @typedef {{
 *   phase: 'primary_only' | 'full',
 *   primaryScopes: (Document|Element)[],
 *   fallbackScope: Document|Element,
 * }} ResolveCtx
 */

/**
 * @param {Record<string, unknown>} payload
 * @param {ResolveCtx} ctx
 * @returns {{ el: Element|null, via: string }}
 */
function resolveSetupHighlightTargetWithMeta(payload, ctx) {
    const primary = payload.target_key;
    if (!primary || typeof primary !== 'string') {
        return { el: null, via: '' };
    }

    const primaryScopes = ctx.primaryScopes;
    const fallbackScope = ctx.fallbackScope;
    const sectionId =
        typeof payload.settings_section_id === 'string' && payload.settings_section_id.length > 0
            ? payload.settings_section_id
            : '';

    const fallbackKeys = Array.isArray(payload.target_fallback_keys) ? payload.target_fallback_keys : [];
    const keysToTry = [primary, ...fallbackKeys.filter((k) => typeof k === 'string' && k.length > 0)];

    for (let si = 0; si < primaryScopes.length; si += 1) {
        const scope = primaryScopes[si];
        for (let i = 0; i < keysToTry.length; i += 1) {
            const key = keysToTry[i];
            const esc =
                typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
                    ? CSS.escape(key)
                    : key.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            const matches = queryAllInScope(scope, `[data-setup-target="${esc}"]`);
            for (let j = 0; j < matches.length; j += 1) {
                const el = matches[j];
                if (isSetupElementVisible(el)) {
                    return { el, via: `[data-setup-target="${key}"]` };
                }
            }
        }
    }

    if (ctx.phase === 'primary_only') {
        if (sectionId !== '') {
            for (let si = 0; si < primaryScopes.length; si += 1) {
                const sec = firstVisibleByDataAttrInScope(primaryScopes[si], 'data-setup-section', sectionId);
                if (sec) {
                    return { el: sec, via: `[data-setup-section="${sectionId}"]` };
                }
            }
        }
        return { el: null, via: '' };
    }

    if (sectionId !== '') {
        for (let si = 0; si < primaryScopes.length; si += 1) {
            const sec = firstVisibleByDataAttrInScope(primaryScopes[si], 'data-setup-section', sectionId);
            if (sec) {
                return { el: sec, via: `[data-setup-section="${sectionId}"]` };
            }
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
        const inCatalog = firstVisibleByDataAttrInScope(fallbackScope, 'data-setup-section-type', id);
        if (inCatalog) {
            return { el: inCatalog, via: `[data-setup-section-type="${id}"]` };
        }
    }

    const action = payload.fallback_setup_action;
    if (typeof action === 'string' && action !== '') {
        const byAction = firstVisibleByDataAttrInScope(fallbackScope, 'data-setup-action', action);
        if (byAction) {
            return { el: byAction, via: `[data-setup-action="${action}"]` };
        }
    }

    return { el: null, via: '' };
}

/**
 * @param {Record<string, unknown>} payload
 * @param {ResolveCtx} ctx
 */
function hasHiddenTargetCandidate(payload, ctx) {
    const primary = payload.target_key;
    if (!primary || typeof primary !== 'string') {
        return false;
    }
    const primaryScopes = ctx.primaryScopes;
    const fallbackScope = ctx.fallbackScope;
    const fallbackKeys = Array.isArray(payload.target_fallback_keys) ? payload.target_fallback_keys : [];
    const keysToTry = [primary, ...fallbackKeys.filter((k) => typeof k === 'string' && k.length > 0)];
    for (let si = 0; si < primaryScopes.length; si += 1) {
        const scope = primaryScopes[si];
        for (let i = 0; i < keysToTry.length; i += 1) {
            const key = keysToTry[i];
            const esc = cssEscapeAttrValue(key);
            const matches = queryAllInScope(scope, `[data-setup-target="${esc}"]`);
            for (let j = 0; j < matches.length; j += 1) {
                if (!isSetupElementVisible(matches[j])) {
                    return true;
                }
            }
        }
    }

    const sectionId =
        typeof payload.settings_section_id === 'string' && payload.settings_section_id.length > 0
            ? payload.settings_section_id
            : '';
    if (sectionId !== '') {
        const esc = cssEscapeAttrValue(sectionId);
        for (let si = 0; si < primaryScopes.length; si += 1) {
            const matches = queryAllInScope(primaryScopes[si], `[data-setup-section="${esc}"]`);
            for (let j = 0; j < matches.length; j += 1) {
                if (!isSetupElementVisible(matches[j])) {
                    return true;
                }
            }
        }
    }

    if (ctx.phase === 'primary_only') {
        return false;
    }

    const sectionTypes = Array.isArray(payload.page_builder_fallback_section_types)
        ? payload.page_builder_fallback_section_types
        : [];
    for (let s = 0; s < sectionTypes.length; s += 1) {
        const id = sectionTypes[s];
        if (typeof id !== 'string' || id === '') {
            continue;
        }
        const esc = cssEscapeAttrValue(id);
        const matches = queryAllInScope(fallbackScope, `[data-setup-section-type="${esc}"]`);
        for (let j = 0; j < matches.length; j += 1) {
            if (!isSetupElementVisible(matches[j])) {
                return true;
            }
        }
    }

    const action = payload.fallback_setup_action;
    if (typeof action === 'string' && action !== '') {
        const esc = cssEscapeAttrValue(action);
        const matches = queryAllInScope(fallbackScope, `[data-setup-action="${esc}"]`);
        for (let j = 0; j < matches.length; j += 1) {
            if (!isSetupElementVisible(matches[j])) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Есть ли в DOM узлы, по которым потенциально сработает подсветка (включая скрытые).
 * Если ни одного селектора нет — не ждём maxMs перед fallback-карточкой (после морфа Livewire).
 *
 * @param {Record<string, unknown>} payload
 * @param {ResolveCtx} ctx
 */
function hasAnyTargetKeyMatchInDom(payload, ctx) {
    const primary = payload.target_key;
    if (!primary || typeof primary !== 'string') {
        return false;
    }
    const primaryScopes = ctx.primaryScopes;
    const fallbackScope = ctx.fallbackScope;
    const sectionId =
        typeof payload.settings_section_id === 'string' && payload.settings_section_id.length > 0
            ? payload.settings_section_id
            : '';
    const fallbackKeys = Array.isArray(payload.target_fallback_keys) ? payload.target_fallback_keys : [];
    const keysToTry = [primary, ...fallbackKeys.filter((k) => typeof k === 'string' && k.length > 0)];

    for (let si = 0; si < primaryScopes.length; si += 1) {
        const scope = primaryScopes[si];
        for (let i = 0; i < keysToTry.length; i += 1) {
            const key = keysToTry[i];
            const esc = cssEscapeAttrValue(key);
            if (queryAllInScope(scope, `[data-setup-target="${esc}"]`).length > 0) {
                return true;
            }
        }
    }

    if (sectionId !== '') {
        const esc = cssEscapeAttrValue(sectionId);
        for (let si = 0; si < primaryScopes.length; si += 1) {
            if (queryAllInScope(primaryScopes[si], `[data-setup-section="${esc}"]`).length > 0) {
                return true;
            }
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
        const esc = cssEscapeAttrValue(id);
        if (queryAllInScope(fallbackScope, `[data-setup-section-type="${esc}"]`).length > 0) {
            return true;
        }
    }

    const action = payload.fallback_setup_action;
    if (typeof action === 'string' && action !== '') {
        const esc = cssEscapeAttrValue(action);
        if (queryAllInScope(fallbackScope, `[data-setup-action="${esc}"]`).length > 0) {
            return true;
        }
    }

    return false;
}

function resolveTargetMissReason(payload, clientReason) {
    if (
        payload.target_context_mismatch === 'wrong_settings_tab' ||
        payload.target_context_mismatch === 'wrong_page_edit_relation_tab' ||
        clientReason === 'wrong_tab'
    ) {
        return 'wrong_tab';
    }
    if (clientReason === 'hidden_by_condition') {
        return 'hidden_by_condition';
    }
    if (clientReason === 'target_missing') {
        return 'target_missing';
    }
    if (clientReason === '') {
        return 'resolved';
    }
    return clientReason || 'target_missing';
}

function updateGuidedDevDebug(payload, clientReason, resolvedVia, debugExtra) {
    const dbg = payload.guided_dev_debug;
    if (!dbg || typeof dbg !== 'object') {
        return;
    }
    let el = document.getElementById('tenant-site-setup-dev-debug');
    if (!el) {
        el = document.createElement('pre');
        el.id = 'tenant-site-setup-dev-debug';
        el.className = 'fi-ts-setup-dev-debug';
        el.setAttribute('role', 'status');
        document.body.appendChild(el);
    }
    const extra = debugExtra && typeof debugExtra === 'object' ? debugExtra : {};
    const merged = {
        ...dbg,
        ...extra,
        client_target_miss_reason: clientReason,
        resolved_reason: resolveTargetMissReason(payload, clientReason),
        target_found: clientReason === '',
        resolved_via: resolvedVia ?? null,
    };
    el.textContent = JSON.stringify(merged, null, 2);
}

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
 * Автофокус только для field-like; для fallback-карточек — нет.
 *
 * @param {Element} primaryEl
 * @param {string} via
 * @param {Record<string, unknown>} payload
 */
function shouldAutoFocusHighlight(primaryEl, via, payload) {
    if (payload.focus_allowed === false) {
        return false;
    }
    if (typeof via === 'string') {
        if (via.includes('data-setup-section-type') || via.includes('data-setup-action')) {
            return false;
        }
        if (via.includes('data-setup-section') && !via.includes('data-setup-target')) {
            return false;
        }
    }
    if (primaryEl.matches('.fi-fo-field')) {
        return true;
    }
    if (primaryEl.querySelector('[data-setup-focus-target]')) {
        return true;
    }
    if (primaryEl.querySelector('input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled])')) {
        return true;
    }
    return false;
}

function tryFocusFieldControl(primaryEl) {
    if (!primaryEl || !(primaryEl instanceof Element)) {
        return;
    }
    const explicit = primaryEl.querySelector('[data-setup-focus-target]');
    if (explicit instanceof HTMLElement) {
        try {
            explicit.focus({ preventScroll: true });
        } catch {
            /* ignore */
        }
        return;
    }
    if (primaryEl.matches('[data-setup-focus-target]') && primaryEl instanceof HTMLElement) {
        try {
            primaryEl.focus({ preventScroll: true });
        } catch {
            /* ignore */
        }
        return;
    }
    const focusable = primaryEl.querySelector(
        'input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled])',
    );
    if (focusable && focusable instanceof HTMLElement) {
        try {
            focusable.focus({ preventScroll: true });
        } catch {
            /* ignore */
        }
    }
}

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

function syncSettingsTabQueryIfNeeded(payload) {
    if (payload.route_name !== 'filament.admin.pages.settings') {
        return false;
    }
    const tab = payload.settings_tab;
    if (typeof tab !== 'string' || tab === '') {
        return false;
    }
    const tabMismatch = payload.settings_tab_matches === false;
    const wrongTabCode = payload.target_context_mismatch === 'wrong_settings_tab';
    if (!tabMismatch && !wrongTabCode) {
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

function syncPageEditRelationQueryIfNeeded(payload) {
    const tab = payload.page_edit_relation_tab;
    if (typeof tab !== 'string' || tab === '') {
        return false;
    }
    if (payload.route_name !== 'filament.admin.resources.pages.edit') {
        return false;
    }
    const tabMismatch = payload.page_edit_relation_matches === false;
    const wrongTabCode = payload.target_context_mismatch === 'wrong_page_edit_relation_tab';
    if (!tabMismatch && !wrongTabCode) {
        return false;
    }
    const u = new URL(window.location.href);
    if (u.searchParams.get('relation') === tab) {
        return false;
    }
    u.searchParams.set('relation', tab);
    window.location.replace(u.toString());

    return true;
}

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
 * Плавающая карточка (стандарт для экрана настроек): не ломает сетку секций Filament.
 */
function mountFloatingPrimaryGuidedCard(payload, templateEl) {
    if (getSetupBarEl(getTenantSiteSetupRoot())) {
        return;
    }
    if (document.querySelector('.fi-ts-setup-inline-mount')) {
        return;
    }
    if (document.querySelector('.fi-ts-setup-inline-card.fi-ts-setup-inline-card-floating')) {
        return;
    }
    const tpl = templateEl ?? document.querySelector('[data-tenant-site-setup="inline-template"]');
    if (!tpl || !(tpl instanceof HTMLTemplateElement)) {
        return;
    }
    const node = tpl.content.firstElementChild;
    if (!node || !(node instanceof Element)) {
        return;
    }
    const clone = node.cloneNode(true);
    clone.classList.add('fi-ts-setup-inline-card-floating');
    clone.setAttribute('data-setup-floating-primary', '1');
    document.body.appendChild(clone);
}

function mountInlineSetupCardIfNeeded(payload, primaryEl, sectionEl, templateEl) {
    if (payload.on_target_route !== true) {
        return;
    }
    if (document.querySelector('.fi-ts-setup-inline-mount')) {
        return;
    }
    const placement = typeof payload.guided_inline_placement === 'string' ? payload.guided_inline_placement : 'inline';
    if (placement === 'floating') {
        mountFloatingPrimaryGuidedCard(payload, templateEl);
        return;
    }
    const tpl = templateEl ?? document.querySelector('[data-tenant-site-setup="inline-template"]');
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

function floatingFallbackStorageKey(payload) {
    const sid = payload.session_id != null ? String(payload.session_id) : 'x';
    const ik =
        (typeof payload.current_item_key === 'string' && payload.current_item_key) ||
        (typeof payload.target_item_key === 'string' && payload.target_item_key) ||
        '';
    const tk = typeof payload.target_key === 'string' ? payload.target_key : '';
    return `fi-ts-setup-float-dismiss:${window.location.pathname}:${sid}:${ik}:${tk}`;
}

function mountInlineSetupFallbackFloating(payload, reason, templateEl) {
    if (getSetupBarEl(getTenantSiteSetupRoot())) {
        return;
    }
    if (document.querySelector('.fi-ts-setup-inline-card.fi-ts-setup-inline-card-floating')) {
        return;
    }
    try {
        if (sessionStorage.getItem(floatingFallbackStorageKey(payload)) === '1') {
            return;
        }
    } catch {
        /* sessionStorage недоступен */
    }

    const tpl = templateEl ?? document.querySelector('[data-tenant-site-setup="inline-template"]');
    if (!tpl || !(tpl instanceof HTMLTemplateElement)) {
        return;
    }
    const node = tpl.content.firstElementChild;
    if (!node || !(node instanceof Element)) {
        return;
    }
    const clone = node.cloneNode(true);
    clone.classList.add('fi-ts-setup-inline-card-floating');
    clone.setAttribute('data-setup-fallback-reason', reason);

    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.className = 'fi-ts-setup-float-dismiss';
    dismiss.setAttribute('aria-label', 'Скрыть подсказку быстрого запуска');
    dismiss.textContent = '×';
    dismiss.addEventListener('click', () => {
        clone.remove();
        try {
            sessionStorage.setItem(floatingFallbackStorageKey(payload), '1');
        } catch {
            /* ignore */
        }
    });
    clone.appendChild(dismiss);

    document.body.appendChild(clone);
}

// ——— save-then-next: несохранённые изменения формы ———

/**
 * @returns {HTMLFormElement|null}
 */
function getMainTenantForm() {
    const main = document.querySelector('.fi-main') || document.querySelector('main');
    if (!main) {
        return null;
    }
    const wired = main.querySelector('form[wire\\:submit]');
    if (wired instanceof HTMLFormElement) {
        return wired;
    }
    const first = main.querySelector('form');
    return first instanceof HTMLFormElement ? first : null;
}

/**
 * @param {HTMLFormElement} form
 * @returns {string}
 */
function serializeTenantFormState(form) {
    const fd = new FormData(form);
    const rows = [];
    for (const [k, v] of fd.entries()) {
        if (k.startsWith('_')) {
            continue;
        }
        if (k === 'livewire') {
            continue;
        }
        if (v instanceof File) {
            rows.push([k, v.name ? `file:${v.name}` : 'file:empty']);
        } else {
            rows.push([k, String(v)]);
        }
    }
    rows.sort((a, b) => a[0].localeCompare(b[0]));
    return JSON.stringify(rows);
}

function captureGuidedFormBaseline() {
    const form = getMainTenantForm();
    if (!form) {
        guidedFormBaseline = null;
        return;
    }
    guidedFormBaseline = serializeTenantFormState(form);
}

function markGuidedBaselineFormTouchedIfMainForm(e) {
    if (!document.body.classList.contains('fi-ts-setup-active')) {
        return;
    }
    const t = e.target;
    if (!(t instanceof Element)) {
        return;
    }
    const form = getMainTenantForm();
    if (!form || !form.contains(t)) {
        return;
    }
    guidedBaselineTouchBeforeLock = true;
}

function bindGuidedBaselineTouchTrackingOnce() {
    if (guidedBaselineTouchBound) {
        return;
    }
    guidedBaselineTouchBound = true;
    document.addEventListener('input', markGuidedBaselineFormTouchedIfMainForm, true);
    document.addEventListener('change', markGuidedBaselineFormTouchedIfMainForm, true);
}

function registerGuidedBaselineMorphStabilizationHook() {
    if (guidedBaselineMorphHookRegistered) {
        return;
    }
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
        return;
    }
    guidedBaselineMorphHookRegistered = true;
    window.Livewire.hook('morph.updated', () => {
        if (!document.body.classList.contains('fi-ts-setup-active')) {
            return;
        }
        const token = guidedBaselineSessionToken;
        const initAt = guidedBaselineInitAt;
        if (!initAt || Date.now() - initAt > guidedBaselineMorphWindowMs) {
            return;
        }
        if (guidedBaselineMorphTimer !== null) {
            clearTimeout(guidedBaselineMorphTimer);
        }
        guidedBaselineMorphTimer = window.setTimeout(() => {
            guidedBaselineMorphTimer = null;
            if (token !== guidedBaselineSessionToken) {
                return;
            }
            if (Date.now() - guidedBaselineInitAt > guidedBaselineMorphWindowMs) {
                return;
            }
            if (guidedBaselineTouchBeforeLock) {
                return;
            }
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    if (token !== guidedBaselineSessionToken) {
                        return;
                    }
                    captureGuidedFormBaseline();
                });
            });
        }, guidedBaselineMorphDebounceMs);
    });
}

/**
 * Снимок «чистой» формы после отрисовки и (если есть Livewire) после серии morph без таймера 240ms.
 */
function scheduleGuidedFormBaselineCapture() {
    guidedBaselineSessionToken += 1;
    guidedBaselineInitAt = Date.now();
    guidedBaselineTouchBeforeLock = false;
    bindGuidedBaselineTouchTrackingOnce();
    registerGuidedBaselineMorphStabilizationHook();

    const token = guidedBaselineSessionToken;
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            queueMicrotask(() => {
                if (token !== guidedBaselineSessionToken) {
                    return;
                }
                captureGuidedFormBaseline();
            });
        });
    });
}

/**
 * Нативный HTMLDialogElement: во внутренних браузерах showModal иногда падает — есть fallback на show().
 *
 * @param {HTMLDialogElement} d
 */
function showDialogModalSafe(d) {
    try {
        if (typeof d.showModal === 'function') {
            d.showModal();
            return;
        }
    } catch {
        /* try show() */
    }
    try {
        if (typeof d.show === 'function') {
            d.show();
        }
    } catch {
        /* ignore */
    }
}

function openTenantNotNeededDialog() {
    const dlg = document.getElementById('fi-ts-setup-not-needed-dialog');
    if (!(dlg instanceof HTMLDialogElement)) {
        return;
    }
    showDialogModalSafe(dlg);
}

function bindTenantSetupDialogControlsOnce() {
    if (tenantSetupDialogControlsBound) {
        return;
    }
    tenantSetupDialogControlsBound = true;
    document.addEventListener('click', (e) => {
        const target = e.target;
        if (!(target instanceof Element)) {
            return;
        }
        const opener = target.closest('[data-fi-ts-open-not-needed-dialog]');
        if (opener) {
            e.preventDefault();
            openTenantNotNeededDialog();
            return;
        }
        const closer = target.closest('[data-fi-ts-close-dialog]');
        if (closer) {
            const id = closer.getAttribute('data-fi-ts-close-dialog');
            if (!id) {
                return;
            }
            const dlg = document.getElementById(id);
            if (dlg instanceof HTMLDialogElement) {
                dlg.close();
            }
        }
    });
}

function isMainTenantFormDirty() {
    const form = getMainTenantForm();
    if (!form || guidedFormBaseline === null) {
        return false;
    }
    return serializeTenantFormState(form) !== guidedFormBaseline;
}

function showSaveRequiredBeforeNextDialog() {
    let d = document.getElementById('fi-ts-setup-save-required-dialog');
    if (!d || !(d instanceof HTMLDialogElement)) {
        d = /** @type {HTMLDialogElement} */ (document.createElement('dialog'));
        d.id = 'fi-ts-setup-save-required-dialog';
        d.className = 'fi-ts-setup-save-required-dialog';
        d.setAttribute('aria-labelledby', 'fi-ts-setup-save-required-title');
        d.innerHTML = `
<div class="fi-ts-setup-not-needed-dialog-panel">
  <h2 id="fi-ts-setup-save-required-title" class="fi-ts-setup-not-needed-dialog-title">Сначала сохраните изменения</h2>
  <p class="fi-ts-setup-not-needed-dialog-lead">Форма на этой странице изменена. Нажмите «Сохранить» у формы, дождитесь сохранения, затем снова «Дальше» в быстром запуске.</p>
  <div class="fi-ts-setup-not-needed-dialog-actions">
    <button type="button" class="fi-ts-setup-btn fi-ts-setup-btn-accent fi-ts-setup-save-required-ok">
      <span class="fi-ts-setup-btn-label">Понятно</span>
    </button>
  </div>
</div>`;
        document.body.appendChild(d);
        const btn = d.querySelector('.fi-ts-setup-save-required-ok');
        btn?.addEventListener('click', () => d.close());
    }
    showDialogModalSafe(d);
}

/**
 * @param {SubmitEvent} e
 */
function guidedSessionSubmitGuard(e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    const actionInput = form.querySelector('input[name="action"]');
    if (!actionInput || !(actionInput instanceof HTMLInputElement) || actionInput.value !== 'next') {
        return;
    }
    const actionUrl = form.getAttribute('action') || '';
    if (!actionUrl.includes('tenant-site-setup/session')) {
        return;
    }
    const p = lastGuidedPayloadForDirty;
    if (!p || typeof p !== 'object') {
        return;
    }
    if (p.guided_next_hint !== 'save_then_next' || p.can_complete_here !== true) {
        return;
    }
    if (!isMainTenantFormDirty()) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    showSaveRequiredBeforeNextDialog();
}

function guidedBeforeUnloadHandler(e) {
    if (!lastGuidedPayloadForDirty || !document.body.classList.contains('fi-ts-setup-active')) {
        return;
    }
    if (lastGuidedPayloadForDirty.guided_next_hint !== 'save_then_next' || lastGuidedPayloadForDirty.can_complete_here !== true) {
        return;
    }
    if (!isMainTenantFormDirty()) {
        return;
    }
    e.preventDefault();
    e.returnValue = '';
}

function bindGuidedSaveGuardsOnce() {
    if (guidedSubmitGuardBound) {
        return;
    }
    guidedSubmitGuardBound = true;
    bindTenantSetupDialogControlsOnce();
    document.addEventListener('submit', guidedSessionSubmitGuard, true);
    if (!guidedBeforeUnloadBound) {
        guidedBeforeUnloadBound = true;
        window.addEventListener('beforeunload', guidedBeforeUnloadHandler);
    }
}

// ——— auto-open page builder ———

function getAutoOpenStorageKeys(payload) {
    const sessionId = payload.session_id != null ? String(payload.session_id) : 'x';
    const itemKey =
        (typeof payload.current_item_key === 'string' && payload.current_item_key) ||
        (typeof payload.target_item_key === 'string' && payload.target_item_key) ||
        '';
    const tk = typeof payload.target_key === 'string' ? payload.target_key : '';
    return {
        ok: `fi-ts-setup-pb-ok:${sessionId}:${itemKey}:${tk}`,
        attempts: `fi-ts-setup-pb-at:${sessionId}:${itemKey}:${tk}`,
        lastStartEditAt: `fi-ts-setup-pb-last:${sessionId}:${itemKey}:${tk}`,
    };
}

function isPageBuilderEditorVisible() {
    return Boolean(getVisiblePageBuilderEditor());
}

function readLastStartEditAt(payload) {
    const { lastStartEditAt } = getAutoOpenStorageKeys(payload);
    try {
        const v = sessionStorage.getItem(lastStartEditAt);
        if (v === null || v === '') {
            return 0;
        }
        const n = parseInt(v, 10);
        return Number.isFinite(n) && n > 0 ? n : 0;
    } catch {
        return 0;
    }
}

function setLastStartEditAt(payload, ts) {
    const { lastStartEditAt } = getAutoOpenStorageKeys(payload);
    try {
        sessionStorage.setItem(lastStartEditAt, String(ts));
    } catch {
        /* ignore */
    }
}

/** Минимум между вызовами startEdit, чтобы DOMContentLoaded + livewire:navigated не сожгли attempts. */
const MIN_MS_BETWEEN_AUTO_OPEN_START_EDIT = 900;

function readAutoOpenAttempts(payload) {
    const { attempts } = getAutoOpenStorageKeys(payload);
    try {
        const v = sessionStorage.getItem(attempts);
        if (v === null || v === '') {
            return 0;
        }
        const n = parseInt(v, 10);
        return Number.isFinite(n) && n >= 0 ? n : 0;
    } catch {
        return 0;
    }
}

function setAutoOpenAttempts(payload, n) {
    const { attempts } = getAutoOpenStorageKeys(payload);
    try {
        sessionStorage.setItem(attempts, String(n));
    } catch {
        /* ignore */
    }
}

function markAutoOpenSuccess(payload) {
    const { ok } = getAutoOpenStorageKeys(payload);
    try {
        sessionStorage.setItem(ok, '1');
    } catch {
        /* ignore */
    }
}

function isAutoOpenSuccessMarked(payload) {
    const { ok } = getAutoOpenStorageKeys(payload);
    try {
        return sessionStorage.getItem(ok) === '1';
    } catch {
        return false;
    }
}

/**
 * Запрос Livewire startEdit для нужной секции. «Чужой» открытый editor не блокирует — вызываем startEdit(id) снова.
 * Счётчик attempts + lastStartEditAt — с debounce против двойных init подряд.
 *
 * @param {Record<string, unknown>} payload
 * @returns {boolean} true если вызвали startEdit
 */
function requestAutoOpenPageBuilderEditor(payload) {
    const ao = payload.page_builder_auto_open;
    if (!ao || typeof ao !== 'object' || ao.enabled !== true) {
        return false;
    }
    const sectionIdRaw = ao.section_id;
    const sectionId = typeof sectionIdRaw === 'number' ? sectionIdRaw : parseInt(String(sectionIdRaw ?? ''), 10);
    if (!Number.isFinite(sectionId) || sectionId <= 0) {
        return false;
    }

    if (isAutoOpenSuccessMarked(payload)) {
        return false;
    }

    const max = typeof ao.max_auto_open_attempts === 'number' ? ao.max_auto_open_attempts : 5;
    const at = readAutoOpenAttempts(payload);
    if (at >= max) {
        return false;
    }

    if (payload.route_name !== 'filament.admin.resources.pages.edit') {
        return false;
    }
    if (payload.on_target_route !== true) {
        return false;
    }

    const openSectionId = getOpenEditorSectionIdFromDom();
    if (openSectionId !== null && openSectionId === sectionId) {
        return false;
    }

    if (Date.now() - readLastStartEditAt(payload) < MIN_MS_BETWEEN_AUTO_OPEN_START_EDIT) {
        return false;
    }

    const root = document.querySelector('.page-sections-builder-root[wire\\:id]');
    if (!root) {
        return false;
    }
    const wireId = root.getAttribute('wire:id');
    if (!wireId || typeof window.Livewire === 'undefined' || typeof window.Livewire.find !== 'function') {
        return false;
    }
    const comp = window.Livewire.find(wireId);
    if (!comp || typeof comp.call !== 'function') {
        return false;
    }
    try {
        comp.call('startEdit', sectionId);
        setLastStartEditAt(payload, Date.now());
        setAutoOpenAttempts(payload, at + 1);
        return true;
    } catch {
        return false;
    }
}

function resolutionPathLabel(via) {
    if (!via || typeof via !== 'string') {
        return 'unknown';
    }
    if (via.includes('data-setup-target')) {
        return 'primary_field';
    }
    if (via.includes('data-setup-section') && !via.includes('data-setup-section-type')) {
        return 'settings_section';
    }
    if (via.includes('data-setup-section-type')) {
        return 'section_type_fallback';
    }
    if (via.includes('data-setup-action')) {
        return 'action_fallback';
    }
    return 'other';
}

// ——— bar offset (только CSS var + класс body) ———

function applyTopOffsetFromBar(bar) {
    if (bar && document.body.contains(bar)) {
        const topOffset = parseFloat(getComputedStyle(bar).top) || 0;
        const barH = bar.offsetHeight || 0;
        const total = topOffset + barH;
        document.documentElement.style.setProperty('--fi-ts-setup-top-offset', `${total}px`);
        document.body.classList.add('fi-ts-setup-use-top-offset');
    } else {
        document.documentElement.style.setProperty('--fi-ts-setup-top-offset', '0px');
        document.body.classList.remove('fi-ts-setup-use-top-offset');
    }
}

function observeSetupBar(bar) {
    if (barResizeObserver) {
        barResizeObserver.disconnect();
        barResizeObserver = null;
    }
    applyTopOffsetFromBar(bar);
    if (bar && typeof ResizeObserver !== 'undefined') {
        barResizeObserver = new ResizeObserver(() => {
            applyTopOffsetFromBar(bar);
        });
        barResizeObserver.observe(bar);
    }
}

function scrollPrimaryIntoComfortZone(primaryEl, bar) {
    const barH = bar && document.body.contains(bar) ? bar.offsetHeight : 0;
    const topOffset = bar && document.body.contains(bar) ? parseFloat(getComputedStyle(bar).top) || 0 : 0;
    const margin = 12;
    const topSafe = topOffset + barH + margin;
    const rect = primaryEl.getBoundingClientRect();
    const vh = window.innerHeight || document.documentElement.clientHeight;
    if (rect.top >= topSafe && rect.bottom <= vh - margin) {
        return;
    }
    const targetTop = rect.top + window.scrollY - topSafe;
    window.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });
}

function focusIfAllowed(primaryEl, allowFocus) {
    if (!allowFocus) {
        return;
    }
    const ae = document.activeElement;
    if (ae && ae instanceof Element && primaryEl.contains(ae)) {
        return;
    }
    tryFocusFieldControl(primaryEl);
}

function finalizeHighlight(payload, primaryEl, sectionEl, bar, resolvedVia, allowFocus, templateEl) {
    primaryEl.classList.add('fi-ts-setup-highlight');
    primaryEl.setAttribute('data-setup-highlighted', 'primary');

    if (sectionEl) {
        sectionEl.classList.add('fi-ts-setup-highlight-section');
        sectionEl.setAttribute('data-setup-highlighted', 'section');
    }

    mountInlineSetupCardIfNeeded(payload, primaryEl, sectionEl, templateEl);

    observeSetupBar(bar);

    const scheduleScroll = () => {
        scrollPrimaryIntoComfortZone(primaryEl, bar);
        focusIfAllowed(primaryEl, allowFocus);
    };
    if (payload.on_target_route === true) {
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                scheduleScroll();
            });
        });
    } else {
        scheduleScroll();
    }
}

function logSetup(payload, ...rest) {
    const isDev = typeof import.meta !== 'undefined' && import.meta.env && import.meta.env.DEV;
    if (!payload.guided_dev_debug && !isDev) {
        return;
    }
    if (window.console && console.info) {
        console.info('[tenant-site-setup]', ...rest);
    }
}

/** Slide-over редактора в teleport на body — observer на body + data-setup-editor-section-id при смене секции. */
function getMutationObserverRoot() {
    return document.body;
}

/**
 * @param {Record<string, unknown>} payload
 * @param {(raw: Element, via: string, meta: { resolveMs: number, path: string }) => void} onFound
 * @param {(reason: string, meta: { resolveMs: number }) => void} onMiss
 * @param {{ resolveStart: number, preferPrimaryUntil: number|null, autoOpenRequested: boolean }} opts
 */
function resolveTargetWithRetry(payload, onFound, onMiss, opts) {
    const resolveStart = opts.resolveStart;
    const preferPrimaryUntil = opts.preferPrimaryUntil;
    const hiddenGraceMs = 500;
    const maxMs = 5000;
    let done = false;
    let observer = null;
    let intervalId = null;
    let rafScheduled = false;

    const getPhase = () => {
        if (preferPrimaryUntil === null || Date.now() >= preferPrimaryUntil) {
            return /** @type {'full'} */ ('full');
        }
        return /** @type {'primary_only'} */ ('primary_only');
    };

    const cleanup = () => {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
        if (intervalId !== null) {
            clearInterval(intervalId);
            intervalId = null;
        }
        rafScheduled = false;
    };

    const abort = () => {
        if (done) {
            return;
        }
        done = true;
        cleanup();
    };

    const tick = () => {
        if (done) {
            return;
        }
        const elapsed = Date.now() - resolveStart;
        const phase = getPhase();
        const primaryScopes = getPrimarySearchScopes();
        const fallbackScope = getFallbackSearchScope();
        const ctx = { phase, primaryScopes, fallbackScope };
        const meta = resolveSetupHighlightTargetWithMeta(payload, ctx);
        const resolveMs = Date.now() - resolveStart;

        const debugBase = {
            auto_open_requested: opts.autoOpenRequested,
            auto_open_editor_visible: isPageBuilderEditorVisible(),
            prefer_primary_until_ms: preferPrimaryUntil,
            resolution_phase: phase,
            resolve_elapsed_ms: resolveMs,
        };

        if (meta.el) {
            done = true;
            cleanup();
            const path = resolutionPathLabel(meta.via);
            onFound(meta.el, meta.via, { resolveMs, path });
            return;
        }
        if (
            payload.target_context_mismatch === 'wrong_settings_tab' ||
            payload.target_context_mismatch === 'wrong_page_edit_relation_tab'
        ) {
            done = true;
            cleanup();
            onMiss('wrong_tab', { resolveMs, debugBase });
            return;
        }
        const noDomCandidateGraceMs = 220;
        if (
            phase === 'full' &&
            !hasAnyTargetKeyMatchInDom(payload, ctx) &&
            elapsed >= noDomCandidateGraceMs
        ) {
            done = true;
            cleanup();
            onMiss('target_missing', { resolveMs, debugBase });
            return;
        }
        if (hasHiddenTargetCandidate(payload, ctx)) {
            if (elapsed < hiddenGraceMs) {
                return;
            }
            done = true;
            cleanup();
            onMiss('hidden_by_condition', { resolveMs, debugBase });
            return;
        }
        if (elapsed >= maxMs) {
            done = true;
            cleanup();
            onMiss('target_missing', { resolveMs, debugBase });
        }
    };

    const scheduleTick = () => {
        if (rafScheduled) {
            return;
        }
        rafScheduled = true;
        requestAnimationFrame(() => {
            rafScheduled = false;
            tick();
        });
    };

    tick();
    if (done) {
        return null;
    }

    const fastPollMs = 1500;
    intervalId = window.setInterval(() => {
        tick();
        if (intervalId !== null && Date.now() - resolveStart >= fastPollMs) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }, 80);

    const obsRoot = getMutationObserverRoot();
    observer = new MutationObserver(() => {
        scheduleTick();
    });
    observer.observe(obsRoot, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style', 'hidden', 'data-setup-editor-section-id'],
    });

    return abort;
}

function initTenantSiteSetup() {
    resetTenantSiteSetupUi();

    const root = getTenantSiteSetupRoot();
    const payloadEl = getPayloadScriptEl(root);
    if (!payloadEl) {
        return;
    }

    let payload;
    try {
        payload = JSON.parse(payloadEl.textContent || '{}');
    } catch {
        return;
    }

    document.body.classList.add('fi-ts-setup-active');

    if (syncSettingsTabQueryIfNeeded(payload)) {
        return;
    }

    if (syncPageEditRelationQueryIfNeeded(payload)) {
        return;
    }

    const resolveStart = Date.now();
    const ao = payload.page_builder_auto_open;
    const autoOpenRequested = requestAutoOpenPageBuilderEditor(payload);

    let preferPrimaryUntil = null;
    if (ao && typeof ao === 'object' && ao.enabled === true) {
        const ms = typeof ao.prefer_primary_target_ms === 'number' ? ao.prefer_primary_target_ms : 1600;
        const rawSid = ao.section_id;
        const expectedSectionId =
            typeof rawSid === 'number' ? rawSid : parseInt(String(rawSid ?? ''), 10);
        const correctEditorOpen =
            Number.isFinite(expectedSectionId) &&
            expectedSectionId > 0 &&
            getOpenEditorSectionIdFromDom() === expectedSectionId;
        if (autoOpenRequested || correctEditorOpen) {
            preferPrimaryUntil = resolveStart + ms;
        }
    }

    const bar = getSetupBarEl(root);
    const templateEl = getInlineTemplateEl(root);

    // Сразу резервируем место под fixed-полосу (top + высота), иначе отступ добавлялся только
    // после resolveTargetWithRetry — контент «прыгал», когда дорисовывались Livewire/цель.
    if (bar instanceof HTMLElement) {
        observeSetupBar(bar);
    }

    const cancelResolve = resolveTargetWithRetry(
        payload,
        (rawTarget, via, meta) => {
            const primaryEl = primaryHighlightElement(rawTarget);
            if (!primaryEl) {
                const phase = preferPrimaryUntil === null || Date.now() >= preferPrimaryUntil ? 'full' : 'primary_only';
                const primaryScopes = getPrimarySearchScopes();
                const fallbackScope = getFallbackSearchScope();
                const reason = hasHiddenTargetCandidate(payload, { phase, primaryScopes, fallbackScope })
                    ? 'hidden_by_condition'
                    : 'target_missing';
                updateGuidedDevDebug(payload, reason, via || null, {
                    auto_open_requested: autoOpenRequested,
                    auto_open_editor_visible: isPageBuilderEditorVisible(),
                    resolution_path: resolutionPathLabel(via),
                    resolve_elapsed_ms: meta.resolveMs,
                    prefer_primary_until_ms: preferPrimaryUntil,
                });
                if (payload.on_target_route === true) {
                    mountInlineSetupFallbackFloating(payload, reason, templateEl);
                }
                observeSetupBar(bar);
                logSetup(payload, reason, payload.target_key, via);
                return;
            }

            const aoInner = payload.page_builder_auto_open;
            if (aoInner && typeof aoInner === 'object' && aoInner.enabled === true && typeof via === 'string' && via.includes('data-setup-target')) {
                markAutoOpenSuccess(payload);
            }

            const allowFocus = shouldAutoFocusHighlight(primaryEl, via, payload);
            const sectionEl = sectionContextElement(primaryEl);
            finalizeHighlight(payload, primaryEl, sectionEl, bar, via, allowFocus, templateEl);
            updateGuidedDevDebug(payload, '', via, {
                auto_open_requested: autoOpenRequested,
                auto_open_editor_visible: isPageBuilderEditorVisible(),
                resolution_path: meta.path,
                resolve_elapsed_ms: meta.resolveMs,
                prefer_primary_until_ms: preferPrimaryUntil,
            });
        },
        (reason, missMeta) => {
            const base = missMeta.debugBase && typeof missMeta.debugBase === 'object' ? missMeta.debugBase : {};
            updateGuidedDevDebug(payload, reason, null, {
                ...base,
                auto_open_requested: autoOpenRequested,
                auto_open_editor_visible: isPageBuilderEditorVisible(),
                resolve_elapsed_ms: missMeta.resolveMs,
                prefer_primary_until_ms: preferPrimaryUntil,
            });
            if (payload.on_target_route === true) {
                mountInlineSetupFallbackFloating(payload, reason, templateEl);
            }
            observeSetupBar(bar);
            logSetup(payload, reason, payload.target_key);
        },
        {
            resolveStart,
            preferPrimaryUntil,
            autoOpenRequested,
        },
    );
    activeSetupResolveAbort = typeof cancelResolve === 'function' ? cancelResolve : null;

    lastGuidedPayloadForDirty = payload;
    bindGuidedSaveGuardsOnce();
    scheduleGuidedFormBaselineCapture();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTenantSiteSetup);
} else {
    initTenantSiteSetup();
}

document.addEventListener('livewire:navigated', initTenantSiteSetup);

function registerLivewireMorphGuidedInitHook() {
    if (livewireMorphHookRegistered) {
        return;
    }
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
        return;
    }
    livewireMorphHookRegistered = true;
    window.Livewire.hook('morph.updated', () => {
        if (morphInitTimer !== null) {
            clearTimeout(morphInitTimer);
        }
        morphInitTimer = window.setTimeout(() => {
            morphInitTimer = null;
            initTenantSiteSetup();
        }, 220);
    });
}

document.addEventListener('livewire:init', registerLivewireMorphGuidedInitHook);
registerLivewireMorphGuidedInitHook();

document.addEventListener('livewire:init', registerGuidedBaselineMorphStabilizationHook);
registerGuidedBaselineMorphStabilizationHook();
bindTenantSetupDialogControlsOnce();
