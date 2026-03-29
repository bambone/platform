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

const initPlatformMarketing = () => {
    initPmAnchorScroll();
    initPmMobileNav();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPlatformMarketing);
} else {
    initPlatformMarketing();
}
