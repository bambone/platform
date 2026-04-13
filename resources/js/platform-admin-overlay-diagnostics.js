/**
 * Временная диагностика залипшего overlay после логина (platform Filament).
 * Включается только при DEBUG_FILAMENT_PLATFORM_OVERLAY=true.
 *
 * См. filament-ghost-modal-overlay.css и план диагностики overlay.
 *
 * Примечание: cssRuleForGhostOverlayPresent() лишь проверяет, что текст правила
 * попал в загруженные stylesheet'ы, а не то, что оно матчится на конкретный DOM.
 */
const LOG_PREFIX = '[RentBase platform overlay diagnostics]';

function cssRuleForGhostOverlayPresent() {
    return [...document.styleSheets].some((sheet) => {
        try {
            return [...sheet.cssRules].some((rule) =>
                rule.cssText.includes('.fi-modal-close-overlay'),
            );
        } catch {
            return false;
        }
    });
}

function collectFullscreenLayers() {
    return [...document.querySelectorAll('body *')]
        .map((el) => {
            const s = getComputedStyle(el);
            const r = el.getBoundingClientRect();
            return {
                el,
                tag: el.tagName.toLowerCase(),
                id: el.id || '',
                class: (el.className || '').toString(),
                position: s.position,
                zIndex: s.zIndex,
                pointerEvents: s.pointerEvents,
                opacity: s.opacity,
                display: s.display,
                visibility: s.visibility,
                bg: s.backgroundColor,
                w: Math.round(r.width),
                h: Math.round(r.height),
            };
        })
        .filter(
            (x) =>
                x.display !== 'none' &&
                x.visibility !== 'hidden' &&
                ['fixed', 'absolute'].includes(x.position) &&
                x.w >= window.innerWidth * 0.9 &&
                x.h >= window.innerHeight * 0.9,
        )
        .sort((a, b) => (parseInt(b.zIndex, 10) || 0) - (parseInt(a.zIndex, 10) || 0));
}

function logModalOverlays() {
    const overlays = document.querySelectorAll('.fi-modal-close-overlay');
    console.log(LOG_PREFIX, 'fi-modal-close-overlay count:', overlays.length);
    overlays.forEach((el, i) => {
        const modal = el.closest('.fi-modal');
        console.log(LOG_PREFIX, `overlay #${i}:`, {
            hasFiModalOpen: modal?.classList.contains('fi-modal-open') ?? false,
            modalId: modal?.id ?? null,
        });
    });
}

function logCenterHitTest() {
    const cx = Math.round(window.innerWidth / 2);
    const cy = Math.round(window.innerHeight / 2);
    const centerEl = document.elementFromPoint(cx, cy);

    console.log(LOG_PREFIX, 'elementFromPoint(center):', centerEl, { x: cx, y: cy });

    if (centerEl) {
        const chain = [];
        let node = centerEl;
        while (node) {
            chain.push({
                tag: node.tagName?.toLowerCase?.() ?? '',
                id: node.id || '',
                class: (node.className || '').toString(),
            });
            node = node.parentElement;
        }
        console.table(chain);
    }
}

function runOverlayDiagnostics() {
    console.log(LOG_PREFIX, 'tick');

    console.log(LOG_PREFIX, 'stylesheet has .fi-modal-close-overlay rule:', cssRuleForGhostOverlayPresent());

    console.log(LOG_PREFIX, 'html inert:', document.documentElement.hasAttribute('inert'));
    console.log(LOG_PREFIX, 'body inert:', document.body.hasAttribute('inert'));
    console.log(LOG_PREFIX, 'html classes:', document.documentElement.className);
    console.log(LOG_PREFIX, 'body classes:', document.body.className);

    console.log(LOG_PREFIX, 'activeElement:', document.activeElement);

    logCenterHitTest();

    const layers = collectFullscreenLayers();
    console.table(layers.map(({ el, ...rest }) => rest));
    window.__overlayCandidates = layers.map((x) => x.el);

    logModalOverlays();
}

function installErrorTaps() {
    window.addEventListener('error', (event) => {
        console.warn(LOG_PREFIX, 'window.error', event.message, event.filename, event.lineno);
    });
    window.addEventListener('unhandledrejection', (event) => {
        console.warn(LOG_PREFIX, 'unhandledrejection', event.reason);
    });
}

function installDomObservers() {
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (
                mutation.type === 'attributes' &&
                ['class', 'inert'].includes(mutation.attributeName ?? '')
            ) {
                const target = mutation.target;
                console.log(LOG_PREFIX, 'attr mutation:', {
                    target: target instanceof Element ? target.tagName : String(target),
                    attr: mutation.attributeName,
                    className: target instanceof Element ? (target.className || '').toString() : '',
                    inert: target instanceof Element ? target.hasAttribute('inert') : false,
                });
            }

            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof HTMLElement)) {
                        return;
                    }
                    const s = getComputedStyle(node);
                    const r = node.getBoundingClientRect();
                    if (
                        ['fixed', 'absolute'].includes(s.position) &&
                        r.width >= window.innerWidth * 0.9 &&
                        r.height >= window.innerHeight * 0.9
                    ) {
                        console.log(LOG_PREFIX, 'fullscreen node added:', node);
                    }
                });
            }
        }
    });

    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class', 'inert'],
        subtree: true,
        childList: true,
    });
}

function scheduleBurst() {
    [50, 300, 800, 1500].forEach((delay) => {
        setTimeout(runOverlayDiagnostics, delay);
    });
}

installErrorTaps();
installDomObservers();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => scheduleBurst(), { once: true });
} else {
    scheduleBurst();
}

window.addEventListener('load', scheduleBurst);
window.addEventListener('pageshow', scheduleBurst);

document.addEventListener('livewire:navigated', () => scheduleBurst());
