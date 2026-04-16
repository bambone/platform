/**
 * Cover preview geometry — same formulas as App\MediaPresentation\FocalCoverPreviewGeometry (PHP).
 */
const EPS = 1e-6;

function getByPath(obj, path) {
    if (obj == null || path === '' || path == null) {
        return undefined;
    }
    let cur = obj;
    for (const part of String(path).split('.')) {
        if (cur == null || typeof cur !== 'object') {
            return undefined;
        }
        cur = cur[part];
    }

    return cur;
}

/**
 * Актуальный viewport_focal_map из Livewire (Filament state), не только wire.data-снимок:
 * в снимке вложенные ключи (напр. tablet) могут отсутствовать до полной гидрации — тогда превью
 * остаётся на дефолте, а mobile/desktop иногда уже есть в JSON.
 */
function readViewportFocalMapFromWire(wire, path) {
    if (!wire || !path) {
        return {};
    }
    const tryObj = (v) => (v && typeof v === 'object' && !Array.isArray(v) ? v : null);
    const tryCall = (fn) => {
        try {
            return tryObj(fn());
        } catch (_) {
            return null;
        }
    };
    const fromGet =
        tryCall(() => (typeof wire.get === 'function' ? wire.get(path) : null)) ||
        tryCall(() => (wire.$wire && typeof wire.$wire.get === 'function' ? wire.$wire.get(path) : null));
    if (fromGet) {
        return fromGet;
    }
    const fromData = tryCall(() => getByPath(wire.data, path));
    if (fromData) {
        return fromData;
    }
    if (wire.snapshot && typeof wire.snapshot === 'object') {
        const snap = wire.snapshot;
        const fromSnap = tryCall(() => getByPath(snap.data ?? snap, path));
        if (fromSnap) {
            return fromSnap;
        }
    }
    const fromRoot = tryCall(() => getByPath(wire, path));
    return fromRoot ?? {};
}

export function coverDisplaySize(iw, ih, frameW, frameH) {
    if (iw <= 0 || ih <= 0 || frameW <= 0 || frameH <= 0) {
        return { scale: 1, dispW: frameW, dispH: frameH };
    }
    const scale = Math.max(frameW / iw, frameH / ih);
    return { scale, dispW: iw * scale, dispH: ih * scale };
}

export function translateFromFocal(px, py, frameW, frameH, iw, ih, userScale = 1) {
    const us = Math.max(1, userScale);
    const { dispW, dispH } = coverDisplaySize(iw, ih, frameW, frameH);
    const ew = dispW * us;
    const eh = dispH * us;
    const tx = Math.abs(frameW - ew) < EPS ? 0 : (px / 100 - 0.5) * (frameW - ew);
    const ty = Math.abs(frameH - eh) < EPS ? 0 : (py / 100 - 0.5) * (frameH - eh);
    return { tx, ty };
}

export function focalFromTranslate(tx, ty, frameW, frameH, iw, ih, userScale = 1) {
    const us = Math.max(1, userScale);
    const { dispW, dispH } = coverDisplaySize(iw, ih, frameW, frameH);
    const ew = dispW * us;
    const eh = dispH * us;
    let px = Math.abs(frameW - ew) < EPS ? 50 : 50 + (tx / (frameW - ew)) * 100;
    let py = Math.abs(frameH - eh) < EPS ? 50 : 50 + (ty / (frameH - eh)) * 100;
    px = Math.max(0, Math.min(100, px));
    py = Math.max(0, Math.min(100, py));
    return { x: px, y: py };
}

export function clampTranslate(tx, ty, frameW, frameH, iw, ih, userScale = 1) {
    const f = focalFromTranslate(tx, ty, frameW, frameH, iw, ih, userScale);
    return translateFromFocal(f.x, f.y, frameW, frameH, iw, ih, userScale);
}

export function focalForCommit(x, y) {
    return {
        x: Math.round(Math.max(0, Math.min(100, x)) * 10) / 10,
        y: Math.round(Math.max(0, Math.min(100, y)) * 10) / 10,
    };
}

export function scaleForCommit(s, min, max, step) {
    const clamped = Math.max(min, Math.min(max, s));
    return Math.round(clamped / step) * step;
}

/**
 * Livewire-компонент с формой секции (focal map в sectionFormData).
 * В Page Builder редактор телепортируется в document.body — превью не под DOM-узлом с wire:id,
 * поэтому сначала ищем data-psb-livewire-id на обёртке телепорта.
 */
function getWire(el) {
    if (!window.Livewire || !el?.closest) {
        return null;
    }
    const psbTeleport = el.closest('[data-psb-livewire-id]');
    if (psbTeleport) {
        const id = psbTeleport.getAttribute('data-psb-livewire-id');
        return id ? window.Livewire.find(id) : null;
    }
    const root = el.closest('[wire\\:id]');
    if (!root) {
        return null;
    }
    const id = root.getAttribute('wire:id');
    return id ? window.Livewire.find(id) : null;
}

/**
 * Vite может подгрузить этот entry после Alpine.start(): тогда alpine:init уже был,
 * и регистрация только в alpine:init никогда не выполнится → ReferenceError: sync, local, onImgLoad.
 */
function registerServiceProgramCoverFocalEditor() {
    if (typeof window.Alpine === 'undefined' || typeof window.Alpine.data !== 'function') {
        return false;
    }
    if (window.__serviceProgramCoverFocalEditorRegistered) {
        return true;
    }
    window.__serviceProgramCoverFocalEditorRegistered = true;

    window.Alpine.data('serviceProgramCoverFocalEditor', (config) => ({
        config,
        sync: config.syncDefault !== false,
        dragging: null,
        frameRefs: { mobile: null, tablet: null, desktop: null },
        ro: null,
        natural: { mobile: null, tablet: null, desktop: null },
        local: {
            mobile: { x: config.mobile.x, y: config.mobile.y, s: config.mobile.s ?? 1 },
            tablet: {
                x: config.tablet?.x ?? 50,
                y: config.tablet?.y ?? 50,
                s: config.tablet?.s ?? 1,
            },
            desktop: { x: config.desktop.x, y: config.desktop.y, s: config.desktop.s ?? 1 },
        },
        pointerId: null,
        _onWinUp: null,
        _onWinCancel: null,
        _onWinMove: null,
        _onVis: null,
        _resyncTimers: null,

        init() {
            this.sync = config.syncDefault !== false;
            const dt = config.defaults?.tablet ?? { x: 50, y: 50, s: 1 };
            this.local = {
                mobile: { ...config.mobile },
                tablet: {
                    x: config.tablet?.x ?? dt.x,
                    y: config.tablet?.y ?? dt.y,
                    s: config.tablet?.s ?? dt.s ?? 1,
                },
                desktop: { ...config.desktop },
            };
            this._onWinUp = (e) => this.endDrag(e);
            this._onWinCancel = (e) => this.cancelDrag(e);
            this._onWinMove = (e) => this.moveDrag(e);
            this._onVis = () => {
                if (document.visibilityState === 'hidden' && this.dragging) {
                    this.cancelDrag(new Event('pointercancel'));
                }
            };
            window.addEventListener('pointerup', this._onWinUp);
            window.addEventListener('pointercancel', this._onWinCancel);
            document.addEventListener('visibilitychange', this._onVis);
            this.$el.addEventListener('alpine:destroy', () => this.cleanup());
            this._resyncTimers = [];
            this.$nextTick(() => {
                this.setupResize();
                this.hydrateNaturalDimensionsFromImages();
                this.resyncFromWire();
                [0, 120, 500].forEach((ms) => {
                    const t = window.setTimeout(() => {
                        this.resyncFromWire();
                        this.hydrateNaturalDimensionsFromImages();
                    }, ms);
                    this._resyncTimers.push(t);
                });
            });
        },

        /**
         * Кэшированное изображение: load не повторится → x-on:load не сработает.
         * Без naturalWidth оверлей «Загрузка изображения…» не скрывается.
         */
        applyNaturalFromImage(key, img) {
            if (!img) {
                return;
            }
            const w = img.naturalWidth;
            const h = img.naturalHeight;
            if (w > 0 && h > 0) {
                this.setNatural(key, w, h);
            }
        },

        hydrateNaturalDimensionsFromImages() {
            ['mobile', 'tablet', 'desktop'].forEach((key) => {
                const frame = this.frameRefs[key];
                if (!frame) {
                    return;
                }
                const img = frame.querySelector('img.svc-program-focal-img');
                if (!img) {
                    return;
                }
                const apply = () => this.applyNaturalFromImage(key, img);
                if (img.complete) {
                    apply();
                } else {
                    img.addEventListener('load', apply, { once: true });
                }
            });
            this.$nextTick(() => this.setupResize());
        },

        onConfigUpdate(newConfig) {
            if (!newConfig) {
                return;
            }
            this.config = { ...this.config, ...newConfig };
            this.sync = newConfig.syncDefault !== false;
            this.local.mobile = { ...newConfig.mobile };
            const dt = newConfig.defaults?.tablet ?? { x: 50, y: 50, s: 1 };
            this.local.tablet = {
                x: newConfig.tablet?.x ?? dt.x,
                y: newConfig.tablet?.y ?? dt.y,
                s: newConfig.tablet?.s ?? dt.s ?? 1,
            };
            this.local.desktop = { ...newConfig.desktop };
            this.natural = { mobile: null, tablet: null, desktop: null };
            this.$nextTick(() => this.hydrateNaturalDimensionsFromImages());
        },

        cleanup() {
            window.removeEventListener('pointerup', this._onWinUp);
            window.removeEventListener('pointercancel', this._onWinCancel);
            window.removeEventListener('pointermove', this._onWinMove);
            document.removeEventListener('visibilitychange', this._onVis);
            if (Array.isArray(this._resyncTimers)) {
                this._resyncTimers.forEach((id) => window.clearTimeout(id));
                this._resyncTimers = null;
            }
            if (this.ro) {
                this.ro.disconnect();
                this.ro = null;
            }
        },

        getWire() {
            return getWire(this.$el);
        },

        wirePath() {
            return this.config.wirePathPrefix ?? 'data.cover_presentation.viewport_focal_map';
        },

        scaleBounds() {
            return {
                min: this.config.scaleMin ?? 1,
                max: this.config.scaleMax ?? 1.5,
                step: this.config.scaleStep ?? 0.05,
            };
        },

        frameSize(key) {
            const el = this.frameRefs[key];
            if (!el) {
                return { w: 360, h: 200 };
            }
            const r = el.getBoundingClientRect();
            return { w: Math.max(1, r.width), h: Math.max(1, r.height) };
        },

        naturalFor(key) {
            return this.natural[key] ?? null;
        },

        setNatural(key, iw, ih) {
            this.natural[key] = { iw, ih };
        },

        localFocal(key) {
            return this.local[key];
        },

        objectPositionStyle(key) {
            const f = this.localFocal(key);
            return `${f.x}% ${f.y}%`;
        },

        layerTransformStyle(key) {
            const f = this.localFocal(key);
            const s = f.s ?? 1;
            return {
                transform: `scale(${s})`,
                transformOrigin: `${f.x}% ${f.y}%`,
            };
        },

        canDrag(key) {
            const n = this.naturalFor(key);
            return !!(n && n.iw > 0 && n.ih > 0);
        },

        axisSlackHint(key) {
            const n = this.naturalFor(key);
            if (!n) {
                return '';
            }
            const { w, h } = this.frameSize(key);
            const f = this.localFocal(key);
            const us = Math.max(1, f.s ?? 1);
            const { dispW, dispH } = coverDisplaySize(n.iw, n.ih, w, h);
            const ew = dispW * us;
            const eh = dispH * us;
            const slackX = Math.abs(w - ew) >= EPS;
            const slackY = Math.abs(h - eh) >= EPS;
            if (slackX && slackY) {
                return '';
            }
            if (!slackX && !slackY) {
                return 'Нет запаса для сдвига по обеим осям — увеличьте zoom или смените источник.';
            }
            if (!slackX) {
                return 'По горизонтали нет запаса (после cover-fit) — увеличьте zoom или используйте другой источник.';
            }
            return 'По вертикали нет запаса — увеличьте zoom или используйте другой источник.';
        },

        startDrag(key, ev) {
            if (!this.canDrag(key) || ev.button === 2) {
                return;
            }
            ev.preventDefault();
            ev.stopPropagation();
            const frame = this.frameRefs[key];
            if (!frame) {
                return;
            }
            try {
                frame.setPointerCapture(ev.pointerId);
            } catch (_) {
                /* ignore */
            }
            this.pointerId = ev.pointerId;
            window.addEventListener('pointermove', this._onWinMove);
            const n = this.naturalFor(key);
            const { w, h } = this.frameSize(key);
            const focal = this.localFocal(key);
            const us = focal.s ?? 1;
            const { tx, ty } = translateFromFocal(focal.x, focal.y, w, h, n.iw, n.ih, us);
            this.dragging = {
                key,
                startX: ev.clientX,
                startY: ev.clientY,
                startTx: tx,
                startTy: ty,
            };
        },

        moveDrag(ev) {
            if (!this.dragging || ev.pointerId !== this.pointerId) {
                return;
            }
            const { key, startX, startY, startTx, startTy } = this.dragging;
            const n = this.naturalFor(key);
            if (!n) {
                return;
            }
            const { w, h } = this.frameSize(key);
            const focal = this.localFocal(key);
            const us = focal.s ?? 1;
            let tx = startTx + (ev.clientX - startX);
            let ty = startTy + (ev.clientY - startY);
            const c = clampTranslate(tx, ty, w, h, n.iw, n.ih, us);
            tx = c.tx;
            ty = c.ty;
            const f = focalFromTranslate(tx, ty, w, h, n.iw, n.ih, us);
            if (key === 'tablet') {
                this.local.tablet = { x: f.x, y: f.y, s: this.local.tablet.s };
            } else if (key === 'desktop') {
                this.local.desktop = { x: f.x, y: f.y, s: this.local.desktop.s };
                if (this.sync) {
                    this.local.mobile = { x: f.x, y: f.y, s: this.local.mobile.s };
                }
            } else {
                this.local.mobile = { x: f.x, y: f.y, s: this.local.mobile.s };
                if (this.sync) {
                    this.local.desktop = { x: f.x, y: f.y, s: this.local.desktop.s };
                }
            }
        },

        endDrag(ev) {
            if (!this.dragging) {
                return;
            }
            if (ev && ev.pointerId !== undefined && ev.pointerId !== this.pointerId) {
                return;
            }
            window.removeEventListener('pointermove', this._onWinMove);
            const frame = this.frameRefs[this.dragging.key];
            if (frame && this.pointerId != null) {
                try {
                    if (frame.hasPointerCapture?.(this.pointerId)) {
                        frame.releasePointerCapture(this.pointerId);
                    }
                } catch (_) {
                    /* ignore */
                }
            }
            this.dragging = null;
            this.pointerId = null;
            this.commitFraming();
        },

        cancelDrag(ev) {
            if (!this.dragging) {
                return;
            }
            if (ev && ev.pointerId !== undefined && ev.pointerId !== this.pointerId) {
                return;
            }
            window.removeEventListener('pointermove', this._onWinMove);
            const frame = this.frameRefs[this.dragging.key];
            if (frame && this.pointerId != null) {
                try {
                    if (frame.hasPointerCapture?.(this.pointerId)) {
                        frame.releasePointerCapture(this.pointerId);
                    }
                } catch (_) {
                    /* ignore */
                }
            }
            this.dragging = null;
            this.pointerId = null;
            this.resyncFromWire();
        },

        async commitFraming() {
            const wire = this.getWire();
            if (!wire) {
                return;
            }
            const base = this.wirePath();
            const { min, max, step } = this.scaleBounds();
            const m = focalForCommit(this.local.mobile.x, this.local.mobile.y);
            const t = focalForCommit(this.local.tablet.x, this.local.tablet.y);
            const d = focalForCommit(this.local.desktop.x, this.local.desktop.y);
            const ms = scaleForCommit(this.local.mobile.s ?? 1, min, max, step);
            const ts = scaleForCommit(this.local.tablet.s ?? 1, min, max, step);
            const ds = scaleForCommit(this.local.desktop.s ?? 1, min, max, step);
            const lm = { ...this.local.mobile, x: m.x, y: m.y, s: ms };
            const lt = { ...this.local.tablet, x: t.x, y: t.y, s: ts };
            const ld = { ...this.local.desktop, x: d.x, y: d.y, s: ds };
            this.local.mobile = lm;
            this.local.tablet = lt;
            this.local.desktop = ld;
            try {
                /** Один round-trip: раньше было 9× wire.set подряд → 9× POST /livewire/update. */
                const prev = readViewportFocalMapFromWire(wire, base) || {};
                const mapPayload = {
                    ...prev,
                    mobile: { x: m.x, y: m.y, scale: ms },
                    tablet: { x: t.x, y: t.y, scale: ts },
                    desktop: { x: d.x, y: d.y, scale: ds },
                };
                await wire.set(base, mapPayload);
            } catch (_) {
                this.resyncFromWire();
                if (typeof window.dispatchEvent === 'function') {
                    window.dispatchEvent(
                        new CustomEvent('service-program-focal-commit-error', { detail: { base } }),
                    );
                }
            }
        },

        resyncFromWire() {
            const wire = this.getWire();
            if (!wire) {
                return;
            }
            const map = readViewportFocalMapFromWire(wire, this.wirePath());
            const mx = parseFloat(map.mobile?.x ?? 50);
            const my = parseFloat(map.mobile?.y ?? 52);
            const ms = parseFloat(map.mobile?.scale ?? 1);
            const tx = parseFloat(map.tablet?.x ?? 50);
            const ty = parseFloat(map.tablet?.y ?? 50);
            const ts = parseFloat(map.tablet?.scale ?? 1);
            const dx = parseFloat(map.desktop?.x ?? 50);
            const dy = parseFloat(map.desktop?.y ?? 48);
            const ds = parseFloat(map.desktop?.scale ?? 1);
            const { min, max, step } = this.scaleBounds();
            this.local.mobile = { x: mx, y: my, s: scaleForCommit(ms, min, max, step) };
            this.local.tablet = { x: tx, y: ty, s: scaleForCommit(ts, min, max, step) };
            this.local.desktop = { x: dx, y: dy, s: scaleForCommit(ds, min, max, step) };
        },

        onScaleInput(key, raw) {
            const v = parseFloat(raw);
            const { min, max, step } = this.scaleBounds();
            const s = Number.isFinite(v) ? scaleForCommit(v, min, max, step) : min;
            if (key === 'tablet') {
                this.local.tablet = { ...this.local.tablet, s };
            } else if (key === 'desktop') {
                this.local.desktop = { ...this.local.desktop, s };
                if (this.sync) {
                    this.local.mobile = { ...this.local.mobile, s };
                }
            } else {
                this.local.mobile = { ...this.local.mobile, s };
                if (this.sync) {
                    this.local.desktop = { ...this.local.desktop, s };
                }
            }
        },

        async commitScaleFromSlider() {
            await this.commitFraming();
        },

        async nudge(key, dpx, dpy, shift) {
            const step = shift ? 5 : 1;
            const sx = dpx * step;
            const sy = dpy * step;
            const cur = this.localFocal(key);
            let x = Math.max(0, Math.min(100, cur.x + sx));
            let y = Math.max(0, Math.min(100, cur.y + sy));
            if (key === 'tablet') {
                this.local.tablet = { ...this.local.tablet, x, y };
            } else if (key === 'desktop') {
                this.local.desktop = { ...this.local.desktop, x, y };
                if (this.sync) {
                    this.local.mobile = { ...this.local.mobile, x, y };
                }
            } else {
                this.local.mobile = { ...this.local.mobile, x, y };
                if (this.sync) {
                    this.local.desktop = { ...this.local.desktop, x, y };
                }
            }
            await this.commitFraming();
        },

        resetMobile() {
            const d = this.config.defaults.mobile;
            this.local.mobile = { x: d.x, y: d.y, s: d.s };
            if (this.sync) {
                this.local.desktop = { ...this.config.defaults.desktop };
            }
            this.commitFraming();
        },

        resetDesktop() {
            const d = this.config.defaults.desktop;
            this.local.desktop = { x: d.x, y: d.y, s: d.s };
            if (this.sync) {
                this.local.mobile = { ...this.config.defaults.mobile };
            }
            this.commitFraming();
        },

        resetBoth() {
            this.local.mobile = { ...this.config.defaults.mobile };
            this.local.tablet = { ...(this.config.defaults?.tablet ?? { x: 50, y: 50, s: 1 }) };
            this.local.desktop = { ...this.config.defaults.desktop };
            this.commitFraming();
        },

        resetTablet() {
            this.local.tablet = { ...(this.config.defaults?.tablet ?? { x: 50, y: 50, s: 1 }) };
            this.commitFraming();
        },

        copyToDesktop() {
            this.local.desktop = { ...this.local.mobile };
            this.commitFraming();
        },

        copyToMobile() {
            this.local.mobile = { ...this.local.desktop };
            this.commitFraming();
        },

        onImgLoad(key, ev) {
            const img = ev.target;
            this.applyNaturalFromImage(key, img);
            this.$nextTick(() => this.setupResize());
        },

        /** Fallback if браузер не отдал naturalWidth (редко); иначе перетаскивание не включится. */
        onImgError(key) {
            this.setNatural(key, 1600, 1200);
            this.$nextTick(() => this.setupResize());
        },

        setupResize() {
            if (this.ro) {
                this.ro.disconnect();
            }
            this.ro = new ResizeObserver(() => {
                /* trigger Alpine re-eval for object-position (same focal, new frame size) */
            });
            ['mobile', 'tablet', 'desktop'].forEach((k) => {
                const el = this.frameRefs[k];
                if (el) {
                    this.ro.observe(el);
                }
            });
        },
    }));

    return true;
}

document.addEventListener('alpine:init', () => {
    registerServiceProgramCoverFocalEditor();
});
registerServiceProgramCoverFocalEditor();
