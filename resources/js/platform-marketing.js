import { initPublicFormValidationErrorsFromJson } from './shared/publicFormValidation.js';

const pmIsLowPerfDevice = () =>
    window.innerWidth < 768 || window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (pmIsLowPerfDevice()) {
    document.documentElement.classList.add('reduced-motion');
}

/**
 * Плавный скролл для внутренних якорей с учётом фиксированного header.
 */
const headerOffset = () => {
    const header = document.querySelector('[data-pm-header]');
    return header ? header.getBoundingClientRect().height + 8 : 72;
};

const initPmAnchorScroll = () => {
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        const id = anchor.getAttribute('href')?.slice(1);
        if (!id) {
            return;
        }
        anchor.addEventListener('click', (e) => {
            const target = document.getElementById(id);
            if (!target) {
                return;
            }
            e.preventDefault();
            const top = target.getBoundingClientRect().top + window.scrollY - headerOffset();
            window.scrollTo({ top, behavior: 'smooth' });
            history.pushState(null, '', `#${id}`);
        });
    });
};

/**
 * Мобильное меню шапки (< lg): клавиатура, ресайз, закрытие по ссылке.
 */
const initPmMobileNav = () => {
    const toggle = document.querySelector('[data-pm-nav-toggle]');
    const panel = document.querySelector('[data-pm-mobile-menu]');
    if (!toggle || !panel) {
        return;
    }

    const iconOpen = toggle.querySelector('[data-pm-nav-icon-open]');
    const iconClose = toggle.querySelector('[data-pm-nav-icon-close]');

    const setOpen = (open) => {
        panel.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
        if (iconOpen && iconClose) {
            iconOpen.classList.toggle('hidden', open);
            iconClose.classList.toggle('hidden', !open);
        }
    };

    toggle.addEventListener('click', () => {
        setOpen(panel.classList.contains('hidden'));
    });

    panel.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.classList.contains('hidden')) {
            setOpen(false);
        }
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => {
        if (e.matches) {
            setOpen(false);
        }
    });
};

const initPmScrollReveal = () => {
    const observerOptions = {
        root: null,
        rootMargin: '0px 0px -50px 0px',
        threshold: 0.1
    };

    const observers = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const useMotion = !pmIsLowPerfDevice();
    document.querySelectorAll('.fade-reveal').forEach((el) => {
        if (!useMotion) {
            el.style.transitionDelay = '0ms';
            el.classList.add('visible');
        } else {
            observers.observe(el);
        }
    });
};

/**
 * Хуки аналитики: CustomEvent `pm:analytics` + window.pmTrack для GA4 / Метрики и т.д.
 * detail: { name, location?, cta?, tier?, case?, intent?, depth?, faqIndex? }
 */
const pmDispatch = (name, detail = {}) => {
    const payload = { name, ...detail };
    document.dispatchEvent(new CustomEvent('pm:analytics', { detail: payload }));
    if (typeof window.pmTrack === 'function') {
        window.pmTrack(name, detail);
    }
};

const pmEmitGrowthAliases = (eventName, detail) => {
    if (eventName === 'cta_click' && detail.location === 'hero') {
        pmDispatch('hero_cta_click', detail);
    }
    if (eventName === 'cta_click' && typeof detail.location === 'string' && detail.location.startsWith('pricing')) {
        pmDispatch('pricing_select', detail);
    }
    if (eventName === 'cta_click' && detail.cta === 'secondary') {
        const href = detail.href || '';
        if (href.includes('intent=demo') || href.includes('intent%3Ddemo')) {
            pmDispatch('demo_open', detail);
        }
    }
};

const initPmAnalyticsClicks = () => {
    document.body.addEventListener('click', (e) => {
        const el = e.target.closest('[data-pm-event]');
        if (!el) {
            return;
        }
        const eventName = el.getAttribute('data-pm-event');
        if (!eventName) {
            return;
        }
        const detail = {
            location: el.getAttribute('data-pm-location') || undefined,
            cta: el.getAttribute('data-pm-cta') || undefined,
            tier: el.getAttribute('data-pm-tier') || undefined,
            case: el.getAttribute('data-pm-case') || undefined,
            href: el.getAttribute('href') || undefined,
        };
        pmDispatch(eventName, detail);
        pmEmitGrowthAliases(eventName, detail);
    });
};

const initPmContactSuccess = () => {
    const box = document.querySelector('[data-pm-contact-success="1"]');
    if (!box) {
        return;
    }
    const intent = box.getAttribute('data-pm-contact-intent') || undefined;
    pmDispatch('contact_form_success', { intent });
    pmDispatch('contact_success', { intent });
};

const initPmScrollDepth = () => {
    const milestones = [25, 50, 75, 90];
    const hit = new Set();
    let scheduled = false;

    const check = () => {
        const doc = document.documentElement;
        const scrollTop = window.scrollY || doc.scrollTop;
        const max = doc.scrollHeight - window.innerHeight;
        if (max <= 0) {
            return;
        }
        const pct = Math.round((scrollTop / max) * 100);
        milestones.forEach((m) => {
            if (pct >= m && !hit.has(m)) {
                hit.add(m);
                pmDispatch('scroll_depth', { depth: m });
            }
        });
    };

    window.addEventListener(
        'scroll',
        () => {
            if (scheduled) {
                return;
            }
            scheduled = true;
            window.requestAnimationFrame(() => {
                scheduled = false;
                check();
            });
        },
        { passive: true }
    );
};

const initPmFaqDetails = () => {
    document.querySelectorAll('details[data-pm-faq-index]').forEach((det) => {
        det.addEventListener('toggle', () => {
            if (!det.open) {
                return;
            }
            pmDispatch('faq_expand', {
                faqIndex: det.getAttribute('data-pm-faq-index') || undefined,
            });
        });
    });
};

/**
 * Форма /contact: показ телефона / доп. поля в зависимости от выбранного канала связи.
 */
const initPmContactPreferredChannels = () => {
    const form = document.querySelector('form[data-pm-contact-form="1"]');
    const metaEl = document.getElementById('pm-contact-channel-meta');
    if (!form || !metaEl) {
        return;
    }

    let meta = [];
    try {
        meta = JSON.parse(metaEl.textContent || '[]');
    } catch {
        return;
    }
    if (!Array.isArray(meta) || meta.length === 0) {
        return;
    }

    const byId = new Map(meta.map((row) => [row.id, row]));
    const phoneBlock = document.getElementById('pm-contact-phone-block');
    const phoneInput = document.getElementById('pm-contact-phone');
    const phoneStar = phoneBlock?.querySelector('.pm-contact-phone-required');
    const phoneHintEl = document.getElementById('pm-contact-phone-hint');
    const valueBlock = document.getElementById('pm-contact-pref-value-block');
    const valueInput = document.getElementById('pm-contact-pref-value');
    const valueHint = document.getElementById('pm-contact-pref-value-dynamic-hint');

    const selectedId = () => {
        const el = form.querySelector('input[name="preferred_contact_channel"]:checked');
        if (el) {
            return el.value;
        }
        const hidden = form.querySelector('input[name="preferred_contact_channel"][type="hidden"]');
        return hidden ? hidden.value : '';
    };

    let prevPreferredId = null;

    const sync = () => {
        const id = selectedId();
        const row = byId.get(id);
        if (!row || !phoneBlock || !phoneInput || !valueBlock || !valueInput) {
            return;
        }

        const needPhone = Boolean(row.needs_phone);
        const needValue = Boolean(row.needs_value);

        if (prevPreferredId !== null && prevPreferredId !== id && valueInput) {
            valueInput.value = '';
        }
        prevPreferredId = id;

        phoneBlock.classList.toggle('hidden', !needPhone);
        phoneInput.toggleAttribute('required', needPhone);
        if (phoneStar) {
            phoneStar.classList.toggle('hidden', !needPhone);
        }

        valueBlock.classList.toggle('hidden', !needValue);
        valueInput.toggleAttribute('required', needValue);

        const ph = String(row.value_placeholder ?? '').trim();
        const hintText = String(row.value_hint ?? '').trim();
        valueInput.setAttribute('placeholder', ph);
        if (ph === '') {
            valueInput.removeAttribute('placeholder');
        }

        if (id === 'whatsapp') {
            valueInput.setAttribute('inputmode', 'tel');
            valueInput.setAttribute('autocomplete', 'tel');
        } else {
            valueInput.setAttribute('inputmode', 'text');
            valueInput.setAttribute('autocomplete', 'off');
        }

        if (phoneHintEl) {
            const phoneHint =
                id === 'whatsapp'
                    ? 'Укажите тот же номер, что в WhatsApp (международный формат).'
                    : 'Международный формат, для РФ можно с +7 или 8.';
            phoneHintEl.textContent = phoneHint;
        }

        if (valueHint) {
            valueHint.textContent = hintText;
            valueHint.classList.toggle('hidden', hintText === '');
        }
    };

    form.addEventListener('change', (e) => {
        const t = e.target;
        if (t && t.matches('input[name="preferred_contact_channel"]')) {
            sync();
        }
    });

    sync();
};

/**
 * Оверлей «сборки сайта» при отправке формы контактов (мгновенная обратная связь до ответа сервера).
 */
const initPmContactSubmitBuildOverlay = () => {
    const form = document.querySelector('form[data-pm-contact-form="1"]');
    const overlay = document.getElementById('pm-contact-submit-overlay');
    const btn = document.getElementById('pm-contact-submit-btn');
    if (!form || !overlay) {
        return;
    }

    form.addEventListener('submit', () => {
        overlay.classList.add('pm-contact-submit-overlay--visible');
        overlay.setAttribute('aria-hidden', 'false');
        if (btn) {
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
        }
        form.setAttribute('data-pm-contact-submitting', '1');
    });

    window.addEventListener('pageshow', (ev) => {
        if (!ev.persisted) {
            return;
        }
        overlay.classList.remove('pm-contact-submit-overlay--visible');
        overlay.setAttribute('aria-hidden', 'true');
        if (btn) {
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
        }
        form.removeAttribute('data-pm-contact-submitting');
    });
};

const initPlatformMarketing = () => {
    initPmAnchorScroll();
    initPmMobileNav();
    initPmScrollReveal();
    initPmAnalyticsClicks();
    initPmContactSuccess();
    initPmScrollDepth();
    initPmFaqDetails();
    initPmContactPreferredChannels();
    initPmContactSubmitBuildOverlay();
    const prefersReducedMotion =
        document.documentElement.classList.contains('reduced-motion') ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    initPublicFormValidationErrorsFromJson({
        formSelector: 'form[data-pm-contact-form="1"]',
        errorKeysScriptId: 'pm-contact-validation-error-keys',
        getStickyOffset: headerOffset,
        afterScrollDelayMs: prefersReducedMotion ? 0 : 480,
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPlatformMarketing);
} else {
    initPlatformMarketing();
}
