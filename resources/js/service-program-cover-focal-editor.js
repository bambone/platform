/**
 * Cover preview geometry — same formulas as App\MediaPresentation\FocalCoverPreviewGeometry (PHP).
 *
 * @typedef {Object} SvcFocalConfigRow
 * @property {number} x
 * @property {number} y
 * @property {number} [s]
 * @property {number} [heightFactor]
 * @typedef {Object} SvcFocalEditorConfig
 * @property {SvcFocalConfigRow} mobile
 * @property {SvcFocalConfigRow} [tablet]
 * @property {SvcFocalConfigRow} desktop
 * @property {string} [wirePathPrefix]
 * @property {string} [viewportStorageId] — id для sessionStorage; без него — inst-N на инстанс.
 * @property {string} [previewEngine]
 * @property {'cover'|'height_fit'} [focalPreviewFit] — hero desktop: локальная геометрия «fit по высоте кадра» в превью; это не всегда совпадает с полным object-fit:contain во всех пропорциях исходника.
 */
const EPS = 1e-6;

/** Уникальный id для sessionStorage, если `viewportStorageId` не задан в config (нельзя делить `default` между инстансами). */
let __svcFocalInstanceSeq = 0;

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

/** Дисплейный масштаб превью: высота изображения вписана в высоту кадра (аналог частного случая crop/cover-движения); при сверхшироком источнике расходится с «полным» object-fit:contain по обеим осям. */
export function heightFitDisplaySize(iw, ih, frameW, frameH) {
    if (iw <= 0 || ih <= 0 || frameW <= 0 || frameH <= 0) {
        return { scale: 1, dispW: frameW, dispH: frameH };
    }
    const scale = frameH / ih;
    return { scale, dispW: iw * scale, dispH: frameH };
}

export function previewDisplaySize(iw, ih, frameW, frameH, geometryMode = 'cover') {
    if (geometryMode === 'height_fit') {
        return heightFitDisplaySize(iw, ih, frameW, frameH);
    }
    return coverDisplaySize(iw, ih, frameW, frameH);
}

export function translateFromFocal(px, py, frameW, frameH, iw, ih, userScale = 1, minUserScale = 1, geometryMode = 'cover') {
    const us = Math.max(minUserScale, userScale);
    const { dispW, dispH } = previewDisplaySize(iw, ih, frameW, frameH, geometryMode);
    const ew = dispW * us;
    const eh = dispH * us;
    const tx = Math.abs(frameW - ew) < EPS ? 0 : (px / 100 - 0.5) * (frameW - ew);
    const ty = Math.abs(frameH - eh) < EPS ? 0 : (py / 100 - 0.5) * (frameH - eh);
    return { tx, ty };
}

export function focalFromTranslate(tx, ty, frameW, frameH, iw, ih, userScale = 1, minUserScale = 1, geometryMode = 'cover') {
    const us = Math.max(minUserScale, userScale);
    const { dispW, dispH } = previewDisplaySize(iw, ih, frameW, frameH, geometryMode);
    const ew = dispW * us;
    const eh = dispH * us;
    let px = Math.abs(frameW - ew) < EPS ? 50 : 50 + (tx / (frameW - ew)) * 100;
    let py = Math.abs(frameH - eh) < EPS ? 50 : 50 + (ty / (frameH - eh)) * 100;
    px = Math.max(0, Math.min(100, px));
    py = Math.max(0, Math.min(100, py));
    return { x: px, y: py };
}

export function clampTranslate(tx, ty, frameW, frameH, iw, ih, userScale = 1, minUserScale = 1, geometryMode = 'cover') {
    const f = focalFromTranslate(tx, ty, frameW, frameH, iw, ih, userScale, minUserScale, geometryMode);
    return translateFromFocal(f.x, f.y, frameW, frameH, iw, ih, userScale, minUserScale, geometryMode);
}

export function focalForCommit(x, y) {
    const rx = Math.round(Math.max(0, Math.min(100, x)) * 10) / 10;
    const ry = Math.round(Math.max(0, Math.min(100, y)) * 10) / 10;
    return {
        x: parseFloat(rx.toFixed(1)),
        y: parseFloat(ry.toFixed(1)),
    };
}

/**
 * @param {number} step  e.g. 0.05 — avoids float junk like 1.4500000000000002 from binary arithmetic
 */
function decimalCountForStep(step) {
    if (!Number.isFinite(step) || step <= 0) {
        return 2;
    }
    const t = String(step);
    if (t.includes('e-')) {
        return 4;
    }
    const p = t.split('.')[1];
    return p ? p.length : 0;
}

/**
 * Snap to grid step, then round with toFixed to strip IEEE-754 noise.
 */
export function scaleForCommit(s, min, max, step) {
    const clamped = Math.max(min, Math.min(max, s));
    const n = Math.round(clamped / step);
    const raw = n * step;
    const d = decimalCountForStep(step);
    return d > 0 ? parseFloat(raw.toFixed(d)) : Math.round(raw);
}

export function heightFactorForCommit(h, min, max, step) {
    return scaleForCommit(h, min, max, step);
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
            mobile: {
                x: config.mobile.x,
                y: config.mobile.y,
                s: config.mobile.s ?? 1,
                heightFactor: config.mobile.heightFactor ?? config.defaults?.mobile?.heightFactor ?? 1,
            },
            tablet: {
                x: config.tablet?.x ?? 50,
                y: config.tablet?.y ?? 50,
                s: config.tablet?.s ?? 1,
                heightFactor: config.mobile?.heightFactor ?? 1,
            },
            desktop: {
                x: config.desktop.x,
                y: config.desktop.y,
                s: config.desktop.s ?? 1,
                heightFactor: config.desktop.heightFactor ?? config.defaults?.desktop?.heightFactor ?? 1,
            },
        },
        /** Только превью: object-contain, без сопоставления с cover-геометрией на сайте. */
        previewShowFullImage: false,
        /** Один активный кадр в UI: mobile | tablet | desktop */
        activeViewport: 'mobile',
        /** Ошибка загрузки <img> по вьюпорту — не подставляем «фейковые» natural, drag выключен. */
        loadError: { mobile: false, tablet: false, desktop: false },
        /** Сообщение при неудачном commitFraming (wire.set); null = ок. */
        commitError: null,
        frameVersion: 0,
        /** Сериализация commitFraming (очередь + «последний победил»). */
        _commitRunId: 0,
        commitQueue: Promise.resolve(),
        _nudgeDebounce: null,
        _dragRaf: 0,
        _pendingMove: null,
        pageAbort: null,
        dragMoveAbort: null,
        _cleaned: false,
        _instanceId: 0,
        pointerId: null,
        _onWinUp: null,
        _onWinCancel: null,
        _onWinMove: null,
        _onVis: null,

        init() {
            this._instanceId = ++__svcFocalInstanceSeq;
            this.sync = config.syncDefault !== false;
            const dt = config.defaults?.tablet ?? { x: 50, y: 50, s: 1, heightFactor: 1 };
            const mhf = config.mobile?.heightFactor ?? config.defaults?.mobile?.heightFactor ?? 1;
            this.local = {
                mobile: {
                    ...config.mobile,
                    heightFactor: mhf,
                },
                tablet: {
                    x: config.tablet?.x ?? dt.x,
                    y: config.tablet?.y ?? dt.y,
                    s: config.tablet?.s ?? dt.s ?? 1,
                    heightFactor: mhf,
                },
                desktop: {
                    ...config.desktop,
                    heightFactor: config.desktop?.heightFactor ?? config.defaults?.desktop?.heightFactor ?? 1,
                },
            };
            this.restoreActiveViewportFromStorage();
            this._onWinUp = (e) => this.endDrag(e);
            this._onWinCancel = (e) => this.cancelDrag(e);
            this._onWinMove = (e) => this.moveDrag(e);
            this._onVis = () => {
                if (document.visibilityState === 'hidden' && this.dragging) {
                    this.cancelDrag(new Event('pointercancel'));
                }
            };
            /** `addEventListener(..., { signal })` — Safari 15.4+ / Chromium 80+; целенаправленно admin-only. */
            this.pageAbort = new AbortController();
            const sig = this.pageAbort.signal;
            window.addEventListener('pointerup', this._onWinUp, { signal: sig });
            window.addEventListener('pointercancel', this._onWinCancel, { signal: sig });
            document.addEventListener('visibilitychange', this._onVis, { signal: sig });
            this.$el.addEventListener('alpine:destroy', () => {
                this._runCleanup();
            });
            this.$watch('previewShowFullImage', (value) => {
                this.resyncFromWire();
                this.syncViewportFocalExtraReadonly(!!value);
            });
            this.$nextTick(() => {
                this.setupResize();
                this.hydrateNaturalDimensionsFromImages();
                this.resyncFromWire();
                this.syncViewportFocalExtraReadonly(!!this.previewShowFullImage);
            });
        },

        destroy() {
            this._runCleanup();
        },

        _runCleanup() {
            if (this._cleaned) {
                return;
            }
            this._cleaned = true;
            try {
                this.syncViewportFocalExtraReadonly(false);
            } catch (_) {
                /* ignore */
            }
            this.dragMoveAbort?.abort();
            this.dragMoveAbort = null;
            this.pageAbort?.abort();
            this.pageAbort = null;
            if (this._dragRaf) {
                cancelAnimationFrame(this._dragRaf);
                this._dragRaf = 0;
            }
            this._pendingMove = null;
            if (this._nudgeDebounce) {
                clearTimeout(this._nudgeDebounce);
                this._nudgeDebounce = null;
            }
            if (this.ro) {
                this.ro.disconnect();
                this.ro = null;
            }
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
                this.loadError[key] = false;
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
                if (img.dataset.svcFocalBound === '1') {
                    return;
                }
                img.dataset.svcFocalBound = '1';
                img.addEventListener('load', () => this.applyNaturalFromImage(key, img), { once: true });
                if (img.complete) {
                    this.applyNaturalFromImage(key, img);
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
            const mhf = newConfig.mobile?.heightFactor ?? newConfig.defaults?.mobile?.heightFactor ?? 1;
            this.local.mobile = { ...newConfig.mobile, heightFactor: mhf };
            const dt = newConfig.defaults?.tablet ?? { x: 50, y: 50, s: 1, heightFactor: 1 };
            this.local.tablet = {
                x: newConfig.tablet?.x ?? dt.x,
                y: newConfig.tablet?.y ?? dt.y,
                s: newConfig.tablet?.s ?? dt.s ?? 1,
                heightFactor: mhf,
            };
            this.local.desktop = {
                ...newConfig.desktop,
                heightFactor: newConfig.desktop?.heightFactor ?? newConfig.defaults?.desktop?.heightFactor ?? 1,
            };
            this.natural = { mobile: null, tablet: null, desktop: null };
            this.$nextTick(() => {
                ['mobile', 'tablet', 'desktop'].forEach((key) => {
                    const frame = this.frameRefs[key];
                    const img = frame?.querySelector('img.svc-program-focal-img');
                    if (img) {
                        delete img.dataset.svcFocalBound;
                    }
                });
                this.hydrateNaturalDimensionsFromImages();
            });
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

        heightBounds() {
            return {
                min: this.config.heightFactorMin ?? 0.5,
                max: this.config.heightFactorMax ?? 2,
                step: this.config.heightFactorStep ?? 0.05,
            };
        },

        heightFactorEnabled() {
            const b = this.config.mediaBase;

            return !!(b && b.wNarrow > 0);
        },

        /** Геометрия drag/zoom: на сайте desktop hero — height-first; mobile/tablet — cover. */
        geometryModeFor(key) {
            if (this.config?.focalPreviewFit === 'height_fit' && key === 'desktop') {
                return 'height_fit';
            }

            return 'cover';
        },

        /** Класс object-fit превью: hero desktop в режиме height_fit — object-contain; иначе object-cover для карточек. */
        previewObjectFitClass(key) {
            if (this.previewShowFullImage) {
                return 'object-contain';
            }
            if (this.geometryModeFor(key) === 'height_fit') {
                return 'object-contain';
            }

            return 'object-cover';
        },

        /**
         * Pixel size of preview frame: program cards from mediaBase (см. PHP profile); иначе staticPreviewFrames (hero / Page Builder).
         */
        previewFramePixelSize(key) {
            const b = this.config.mediaBase ?? {};
            if (b.wNarrow > 0) {
                const rw = this.config.refWidths?.[key] ?? 360;
                const hfm = this.local.mobile?.heightFactor ?? 1;
                const hfd = this.local.desktop?.heightFactor ?? 1;
                let wNum;
                let hNum;
                if (key === 'desktop') {
                    wNum = b.wDesktop ?? 2.1;
                    hNum = (b.hDesktop ?? 1.1) * hfd;
                } else if (key === 'tablet') {
                    wNum = b.wSm ?? 7;
                    hNum = (b.hSm ?? 4.4) * hfm;
                } else {
                    wNum = b.wNarrow ?? 3;
                    hNum = (b.hNarrow ?? 2.2) * hfm;
                }
                const h = Math.round((rw * hNum) / wNum);

                return { w: rw, h: h };
            }
            const st = this.config.staticPreviewFrames?.[key];
            if (st) {
                return { w: st.w, h: st.h };
            }

            return { w: 360, h: 200 };
        },

        frameOuterStyle(key) {
            const { w, h } = this.previewFramePixelSize(key);

            return {
                width: `${w}px`,
                maxWidth: '100%',
                aspectRatio: `${w} / ${h}`,
            };
        },

        /**
         * Нижняя «зона текста/CTA» в превью: на сайте при росте медиа (height_factor) высота блока в пикселях
         * ≈та же, поэтому как доля от высоты кадра величина ∝ 1/hf. Статичные 38% визуально «липли» к ползунку высоты.
         */
        safeAreaStyle(key) {
            const p0 = this.config.safeAreaBottomPercent ?? 38;
            const hMin = this.config.heightFactorMin ?? 0.5;
            const hMax = this.config.heightFactorMax ?? 2;
            const raw =
                key === 'desktop' ? (this.local.desktop?.heightFactor ?? 1) : (this.local.mobile?.heightFactor ?? 1);
            const hf = Math.max(hMin, Math.min(hMax, Number.isFinite(raw) ? raw : 1));
            let pct = p0 / hf;
            pct = Math.max(16, Math.min(55, pct));
            return { height: `${Math.round(pct * 10) / 10}%` };
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
            if (this.previewShowFullImage) {
                return '50% 50%';
            }
            const f = this.localFocal(key);
            return `${f.x}% ${f.y}%`;
        },

        layerTransformStyle(key) {
            if (this.previewShowFullImage) {
                return { transform: 'none', willChange: 'auto' };
            }
            const f = this.localFocal(key);
            const s = f.s ?? 1;
            return {
                transform: `scale(${s})`,
                transformOrigin: `${f.x}% ${f.y}%`,
                willChange: 'transform',
                backfaceVisibility: 'hidden',
            };
        },

        canDrag(key) {
            if (this.config?.previewEngine === 'public_card' && !this.config?.allowFocalDrag) {
                return false;
            }
            if (this.previewShowFullImage) {
                return false;
            }
            if (this.loadError?.[key]) {
                return false;
            }
            const n = this.naturalFor(key);
            return !!(n && n.iw > 0 && n.ih > 0);
        },

        /** Оверлей «Загрузка…» — только пока нет natural; не путать с canDrag (в режиме «весь кадр» drag выключен, но картинка уже загружена). */
        isNaturalReady(key) {
            if (this.loadError?.[key]) {
                return false;
            }
            const n = this.naturalFor(key);
            return !!(n && n.iw > 0 && n.ih > 0);
        },

        activeViewportStorageKey() {
            const id = this.config?.viewportStorageId;
            if (id != null && String(id) !== '') {
                return `svc-focal-active-vp:${String(id)}`;
            }

            return `svc-focal-active-vp:inst-${this._instanceId}`;
        },

        persistActiveViewport(key) {
            try {
                sessionStorage.setItem(this.activeViewportStorageKey(), key);
            } catch (_) {
                /* private mode / quota */
            }
        },

        restoreActiveViewportFromStorage() {
            try {
                const raw = sessionStorage.getItem(this.activeViewportStorageKey());
                if (raw === 'mobile' || raw === 'tablet' || raw === 'desktop') {
                    this.activeViewport = raw;
                }
            } catch (_) {
                /* ignore */
            }
        },

        setActiveViewport(key) {
            if (key !== 'mobile' && key !== 'tablet' && key !== 'desktop') {
                return;
            }
            this.activeViewport = key;
            this.persistActiveViewport(key);
            this.resyncFromWire();
            this.$nextTick(() => {
                this.setupResize();
                this.hydrateNaturalDimensionsFromImages();
            });
        },

        showZoomSliderForActive() {
            return true;
        },

        showHeightSliderForActive() {
            if (!this.heightFactorEnabled()) {
                return false;
            }
            if (this.activeViewport === 'tablet') {
                return false;
            }
            if (this.activeViewport === 'mobile') {
                return true;
            }

            return !this.sync;
        },

        axisSlackHint(key) {
            if (this.previewShowFullImage) {
                return '';
            }
            const n = this.naturalFor(key);
            if (!n) {
                return '';
            }
            const { w, h } = this.frameSize(key);
            const f = this.localFocal(key);
            const b = this.scaleBounds();
            const us = Math.max(b.min, f.s ?? 1);
            const mode = this.geometryModeFor(key);
            const { dispW, dispH } = previewDisplaySize(n.iw, n.ih, w, h, mode);
            const ew = dispW * us;
            const eh = dispH * us;
            const slackX = Math.abs(w - ew) >= EPS;
            const slackY = Math.abs(h - eh) >= EPS;
            const fitPhrase = mode === 'height_fit' ? 'после вписывания по высоте' : 'после cover-fit';
            if (slackX && slackY) {
                return '';
            }
            if (!slackX && !slackY) {
                return 'Нет запаса для сдвига по обеим осям — измените zoom или смените источник.';
            }
            if (!slackX) {
                return `По горизонтали нет запаса (${fitPhrase}) — уменьшите или увеличьте zoom, либо другой источник.`;
            }
            return 'По вертикали нет запаса — измените zoom или используйте другой источник.';
        },

        startDrag(key, ev) {
            if (this.previewShowFullImage) {
                return;
            }
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
            this.dragMoveAbort?.abort();
            this.dragMoveAbort = new AbortController();
            const sig = this.dragMoveAbort.signal;
            window.addEventListener('pointermove', this._onWinMove, { signal: sig });
            // При setPointerCapture цель — элемент кадра; всплытие до window иногда съедается (Livewire/оверлеи).
            // Слушатели capture + глобальный bubble (pageAbort) дублируют end — второй no-op.
            window.addEventListener('pointerup', this._onWinUp, { capture: true, signal: sig });
            window.addEventListener('pointercancel', this._onWinCancel, { capture: true, signal: sig });
            const n = this.naturalFor(key);
            const { w, h } = this.frameSize(key);
            const focal = this.localFocal(key);
            const us = focal.s ?? 1;
            const { min } = this.scaleBounds();
            const mode = this.geometryModeFor(key);
            const { tx, ty } = translateFromFocal(focal.x, focal.y, w, h, n.iw, n.ih, us, min, mode);
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
            this._pendingMove = { x: ev.clientX, y: ev.clientY };
            if (this._dragRaf) {
                return;
            }
            this._dragRaf = requestAnimationFrame(() => {
                this._dragRaf = 0;
                const p = this._pendingMove;
                if (!p || !this.dragging) {
                    return;
                }
                this._applyMoveDrag(p.x, p.y);
            });
        },

        _applyMoveDrag(clientX, clientY) {
            if (!this.dragging) {
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
            const { min } = this.scaleBounds();
            const mode = this.geometryModeFor(key);
            let tx = startTx + (clientX - startX);
            let ty = startTy + (clientY - startY);
            const c = clampTranslate(tx, ty, w, h, n.iw, n.ih, us, min, mode);
            tx = c.tx;
            ty = c.ty;
            const f = focalFromTranslate(tx, ty, w, h, n.iw, n.ih, us, min, mode);
            const com = focalForCommit(f.x, f.y);
            if (key === 'tablet') {
                this.local.tablet = { ...this.local.tablet, x: com.x, y: com.y, s: this.local.tablet.s };
                if (this.sync) {
                    const s = this.local.tablet.s;
                    this.local.mobile = { ...this.local.mobile, x: com.x, y: com.y, s };
                    this.local.desktop = { ...this.local.desktop, x: com.x, y: com.y, s };
                }
            } else if (key === 'desktop') {
                this.local.desktop = { ...this.local.desktop, x: com.x, y: com.y, s: this.local.desktop.s };
                if (this.sync) {
                    const s = this.local.desktop.s;
                    this.local.mobile = { ...this.local.mobile, x: com.x, y: com.y, s };
                    this.local.tablet = { ...this.local.tablet, x: com.x, y: com.y, s };
                }
            } else {
                this.local.mobile = { ...this.local.mobile, x: com.x, y: com.y, s: this.local.mobile.s };
                if (this.sync) {
                    const s = this.local.mobile.s;
                    this.local.tablet = { ...this.local.tablet, x: com.x, y: com.y, s };
                    this.local.desktop = { ...this.local.desktop, x: com.x, y: com.y, s };
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
            const frameKey = this.dragging.key;
            const capId = this.pointerId;
            this._pendingMove = null;
            this.dragMoveAbort?.abort();
            this.dragMoveAbort = null;
            if (this._dragRaf) {
                cancelAnimationFrame(this._dragRaf);
                this._dragRaf = 0;
            }
            this.dragging = null;
            this.pointerId = null;
            const frame = this.frameRefs[frameKey];
            if (frame && capId != null) {
                try {
                    if (frame.hasPointerCapture?.(capId)) {
                        frame.releasePointerCapture(capId);
                    }
                } catch (_) {
                    /* ignore */
                }
            }
            this.queueCommit();
        },

        cancelDrag(ev) {
            if (!this.dragging) {
                return;
            }
            if (ev && ev.pointerId !== undefined && ev.pointerId !== this.pointerId) {
                return;
            }
            const frameKey = this.dragging.key;
            const capId = this.pointerId;
            this._pendingMove = null;
            this.dragMoveAbort?.abort();
            this.dragMoveAbort = null;
            if (this._dragRaf) {
                cancelAnimationFrame(this._dragRaf);
                this._dragRaf = 0;
            }
            this.dragging = null;
            this.pointerId = null;
            const frame = this.frameRefs[frameKey];
            if (frame && capId != null) {
                try {
                    if (frame.hasPointerCapture?.(capId)) {
                        frame.releasePointerCapture(capId);
                    }
                } catch (_) {
                    /* ignore */
                }
            }
            this.resyncFromWire();
        },

        /**
         * Поля «Дополнительно» (секция с data-svc-focal-numeric-extras) — readOnly в режиме «весь кадр»; не сканировать весь form.
         * Исходные disabled/readOnly фиксируем в `data-svc-focal-orig-*` (один раз на элемент), чтобы не включать обратно контрол,
         * который был disabled не из-за этого режима.
         */
        syncViewportFocalExtraReadonly(readonly) {
            const form = this.$el?.closest('form');
            const box = form?.querySelector('[data-svc-focal-numeric-extras]');
            const root = box ?? form;
            if (!root) {
                return;
            }
            for (const el of root.querySelectorAll('input, textarea, select')) {
                let isFocal = false;
                for (const name of el.getAttributeNames()) {
                    if (!name.startsWith('wire:model')) {
                        continue;
                    }
                    const p = el.getAttribute(name) ?? '';
                    // Hero: hero_background_presentation…; карточки: cover_presentation… — достаточно viewport_focal_map внутри блока extras.
                    if (p.includes('viewport_focal_map')) {
                        isFocal = true;
                    }
                }
                if (!isFocal) {
                    continue;
                }
                if (!el.hasAttribute('data-svc-focal-orig-captured')) {
                    el.setAttribute('data-svc-focal-orig-captured', '1');
                    if (el.tagName === 'SELECT') {
                        el.setAttribute('data-svc-focal-orig-disabled', el.disabled ? '1' : '0');
                    } else {
                        el.setAttribute('data-svc-focal-orig-readonly', el.readOnly ? '1' : '0');
                    }
                }
                if (readonly) {
                    if (el.tagName === 'SELECT') {
                        el.disabled = true;
                    } else {
                        el.readOnly = true;
                    }
                } else if (el.tagName === 'SELECT') {
                    el.disabled = el.getAttribute('data-svc-focal-orig-disabled') === '1';
                } else {
                    el.readOnly = el.getAttribute('data-svc-focal-orig-readonly') === '1';
                }
            }
        },

        /**
         * Как {@see \App\MediaPresentation\ServiceProgramCardPresentationResolver::articleStyleAttribute} — на превью
         * карточки справа, чтобы кадр обновлялся при moveDrag/drag (this.local) без round-trip Livewire.
         */
        clampHfForArticle(v) {
            const min = this.config?.heightFactorMin ?? 0.5;
            const max = this.config?.heightFactorMax ?? 2.0;
            const n = Number(v);
            if (!Number.isFinite(n)) {
                return 1;
            }
            return Math.min(max, Math.max(min, n));
        },

        /**
         * @param {'mobile'|'tablet'|'desktop'} previewMode  Какая карточка справа: узкая / ~768 / широкая.
         *        На сайте 768–1023 кадр из ветки mobile в JSON, поля — в `tablet`; в CSS это всё ещё --svc-*-mobile.
         */
        programCardArticleStyleForPreview(previewMode) {
            void this.frameVersion;
            const mode = previewMode === 'tablet' || previewMode === 'desktop' ? previewMode : 'mobile';
            const cfg = this.config || {};
            const mb = cfg.mediaBase || {};
            const lm = this.local?.mobile || {};
            const lt = this.local?.tablet || {};
            const ld = this.local?.desktop || {};
            /** Для --svc-*-mobile: на превью «планшет» подставляем строку tablet (как на лендинге). */
            const m =
                mode === 'tablet'
                    ? lt
                    : lm;
            const wN = mb.wNarrow ?? 3;
            const hN = mb.hNarrow ?? 2.2;
            const wD = mb.wDesktop ?? 2.1;
            const hD = mb.hDesktop ?? 1.1;
            const mhf = this.clampHfForArticle(lm.heightFactor);
            const dhf = this.clampHfForArticle(ld.heightFactor);
            const sm = Number(m.s ?? 1);
            const sd = Number(ld.s ?? 1);
            const parts = [
                `--svc-program-focal-x-mobile: ${Number(m.x).toFixed(1)}%`,
                `--svc-program-focal-y-mobile: ${Number(m.y).toFixed(1)}%`,
                `--svc-program-focal-x-desktop: ${Number(ld.x).toFixed(1)}%`,
                `--svc-program-focal-y-desktop: ${Number(ld.y).toFixed(1)}%`,
                `--svc-program-scale-mobile: ${sm}`,
                `--svc-program-scale-desktop: ${sd}`,
                `--svc-program-media-aspect-w-mobile: ${wN}`,
                `--svc-program-media-aspect-h-mobile: ${hN * mhf}`,
                `--svc-program-media-aspect-w-desktop: ${wD}`,
                `--svc-program-media-aspect-h-desktop: ${hD * dhf}`,
            ];
            const ov = cfg.articleStyleOverlay;
            if (ov && typeof ov === 'object') {
                for (const [k, val] of Object.entries(ov)) {
                    const s = val != null && typeof val === 'string' ? val : String(val);
                    parts.push(`--${k}: ${s}`);
                }
            }
            return parts.join('; ');
        },

        programCardArticleStyle() {
            return this.programCardArticleStyleForPreview('mobile');
        },

        queueCommit() {
            this.commitQueue = this.commitQueue
                .then(() => this.commitFraming())
                .catch((err) => {
                    console.error('serviceProgramCoverFocalEditor queueCommit failed', err);
                });
            return this.commitQueue;
        },

        async commitFraming() {
            const wire = this.getWire();
            if (!wire) {
                return;
            }
            const runId = ++this._commitRunId;
            const base = this.wirePath();
            const { min, max, step } = this.scaleBounds();
            const hb = this.heightBounds();
            /** wire.set до расчёта: подставляем missing/undefined из Livewire, иначе NaN и JSON-мердж обнуляют поля. */
            const prev = readViewportFocalMapFromWire(wire, base) || {};
            const pm = prev.mobile && typeof prev.mobile === 'object' ? prev.mobile : {};
            const pt = prev.tablet && typeof prev.tablet === 'object' ? prev.tablet : {};
            const pd = prev.desktop && typeof prev.desktop === 'object' ? prev.desktop : {};
            const pickXy = (localVal, row, def) => {
                if (Number.isFinite(Number(localVal))) {
                    return Number(localVal);
                }
                const w = parseFloat(row?.x);
                return Number.isFinite(w) ? w : def;
            };
            const pickY = (localVal, row, def) => {
                if (Number.isFinite(Number(localVal))) {
                    return Number(localVal);
                }
                const w = parseFloat(row?.y);
                return Number.isFinite(w) ? w : def;
            };
            const pickS = (localVal, row) => {
                if (Number.isFinite(Number(localVal))) {
                    return Number(localVal);
                }
                const w = parseFloat(row?.scale);
                return Number.isFinite(w) ? w : 1;
            };
            const pickHf = (localVal, row, fallback) => {
                if (Number.isFinite(Number(localVal))) {
                    return Number(localVal);
                }
                const r = row?.height_factor ?? row?.heightFactor;
                if (r !== undefined && r !== null && r !== '' && Number.isFinite(parseFloat(r))) {
                    return parseFloat(r);
                }

                return fallback;
            };
            const mxy = pickXy(this.local.mobile?.x, pm, 50);
            const myy = pickY(this.local.mobile?.y, pm, 52);
            const txy = pickXy(this.local.tablet?.x, pt, 50);
            const tyy = pickY(this.local.tablet?.y, pt, 50);
            const dxy = pickXy(this.local.desktop?.x, pd, 50);
            const dyy = pickY(this.local.desktop?.y, pd, 48);
            const m = focalForCommit(mxy, myy);
            const t = focalForCommit(txy, tyy);
            const d = focalForCommit(dxy, dyy);
            const ms = scaleForCommit(pickS(this.local.mobile?.s, pm), min, max, step);
            const ts = scaleForCommit(pickS(this.local.tablet?.s, pt), min, max, step);
            const ds = scaleForCommit(pickS(this.local.desktop?.s, pd), min, max, step);
            const hfOn = this.heightFactorEnabled();
            const mh = hfOn
                ? heightFactorForCommit(
                      pickHf(this.local.mobile?.heightFactor, pm, 1),
                      hb.min,
                      hb.max,
                      hb.step,
                  )
                : 1;
            const dh = hfOn
                ? heightFactorForCommit(
                      pickHf(this.local.desktop?.heightFactor, pd, 1),
                      hb.min,
                      hb.max,
                      hb.step,
                  )
                : 1;
            const lm = { ...this.local.mobile, x: m.x, y: m.y, s: ms };
            const lt = { ...this.local.tablet, x: t.x, y: t.y, s: ts };
            const ld = { ...this.local.desktop, x: d.x, y: d.y, s: ds };
            if (hfOn) {
                lm.heightFactor = mh;
                lt.heightFactor = mh;
                ld.heightFactor = dh;
            }
            this.local.mobile = lm;
            this.local.tablet = lt;
            this.local.desktop = ld;
            try {
                /** Один round-trip: раньше было 9× wire.set подряд → 9× POST /livewire/update. */
                const buildRow = (px, py, pscale, phf) => {
                    if (hfOn) {
                        return { x: px, y: py, scale: pscale, height_factor: phf };
                    }

                    return { x: px, y: py, scale: pscale };
                };
                const mapPayload = {
                    ...prev,
                    mobile: buildRow(m.x, m.y, ms, mh),
                    tablet: buildRow(t.x, t.y, ts, mh),
                    desktop: buildRow(d.x, d.y, ds, dh),
                };
                await wire.set(base, mapPayload);
                if (runId !== this._commitRunId) {
                    return;
                }
                this.commitError = null;
                this.persistActiveViewport(this.activeViewport);
                this.$nextTick(() => {
                    this.syncViewportFocalExtraReadonly(!!this.previewShowFullImage);
                });
            } catch (_) {
                if (runId === this._commitRunId) {
                    this.commitError =
                        'Не удалось сохранить кадр. Проверьте соединение и попробуйте ещё раз.';
                    this.resyncFromWire();
                }
                if (typeof window.dispatchEvent === 'function') {
                    window.dispatchEvent(
                        new CustomEvent('service-program-focal-commit-error', { detail: { base } }),
                    );
                }
            }
        },

        resyncFromWire() {
            if (this.dragging) {
                return;
            }
            const wire = this.getWire();
            if (!wire) {
                return;
            }
            const map = readViewportFocalMapFromWire(wire, this.wirePath());
            const mx = parseFloat(map.mobile?.x ?? 50);
            const my = parseFloat(map.mobile?.y ?? 52);
            const ms = parseFloat(map.mobile?.scale ?? 1);
            const mhfR = map.mobile?.height_factor ?? map.mobile?.heightFactor;
            const mhfP = mhfR !== undefined && mhfR !== null && mhfR !== '' ? parseFloat(mhfR) : 1;
            const tx = parseFloat(map.tablet?.x ?? 50);
            const ty = parseFloat(map.tablet?.y ?? 50);
            const ts = parseFloat(map.tablet?.scale ?? 1);
            const dx = parseFloat(map.desktop?.x ?? 50);
            const dy = parseFloat(map.desktop?.y ?? 48);
            const ds = parseFloat(map.desktop?.scale ?? 1);
            const dhfR = map.desktop?.height_factor ?? map.desktop?.heightFactor;
            const dhfP = dhfR !== undefined && dhfR !== null && dhfR !== '' ? parseFloat(dhfR) : 1;
            const { min, max, step } = this.scaleBounds();
            const hb = this.heightBounds();
            const mhf = heightFactorForCommit(Number.isFinite(mhfP) ? mhfP : 1, hb.min, hb.max, hb.step);
            const dhf = heightFactorForCommit(Number.isFinite(dhfP) ? dhfP : 1, hb.min, hb.max, hb.step);
            this.local.mobile = {
                x: mx,
                y: my,
                s: scaleForCommit(ms, min, max, step),
                heightFactor: mhf,
            };
            this.local.tablet = {
                x: tx,
                y: ty,
                s: scaleForCommit(ts, min, max, step),
                heightFactor: mhf,
            };
            this.local.desktop = {
                x: dx,
                y: dy,
                s: scaleForCommit(ds, min, max, step),
                heightFactor: dhf,
            };
            this.persistActiveViewport(this.activeViewport);
        },

        onScaleInput(key, raw) {
            const v = parseFloat(raw);
            const { min, max, step } = this.scaleBounds();
            const s = Number.isFinite(v) ? scaleForCommit(v, min, max, step) : min;
            if (key === 'tablet') {
                this.local.tablet = { ...this.local.tablet, s };
                if (this.sync) {
                    this.local.mobile = { ...this.local.mobile, s };
                    this.local.desktop = { ...this.local.desktop, s };
                }
            } else if (key === 'desktop') {
                this.local.desktop = { ...this.local.desktop, s };
                if (this.sync) {
                    this.local.mobile = { ...this.local.mobile, s };
                    this.local.tablet = { ...this.local.tablet, s };
                }
            } else {
                this.local.mobile = { ...this.local.mobile, s };
                if (this.sync) {
                    this.local.tablet = { ...this.local.tablet, s };
                    this.local.desktop = { ...this.local.desktop, s };
                }
            }
        },

        onHeightInput(key, raw) {
            if (!this.heightFactorEnabled()) {
                return;
            }
            const { min, max, step } = this.heightBounds();
            const v = parseFloat(raw);
            const h = Number.isFinite(v) ? heightFactorForCommit(v, min, max, step) : min;
            if (key === 'desktop') {
                this.local.desktop = { ...this.local.desktop, heightFactor: h };
                if (this.sync) {
                    this.local.mobile = { ...this.local.mobile, heightFactor: h };
                    this.local.tablet = { ...this.local.tablet, heightFactor: h };
                }
            } else {
                this.local.mobile = { ...this.local.mobile, heightFactor: h };
                this.local.tablet = { ...this.local.tablet, heightFactor: h };
                if (this.sync) {
                    this.local.desktop = { ...this.local.desktop, heightFactor: h };
                }
            }
        },

        onHeightInputForActive(raw) {
            if (this.activeViewport === 'mobile' || this.activeViewport === 'tablet') {
                this.onHeightInput('mobile', raw);
            } else {
                this.onHeightInput('desktop', raw);
            }
        },

        commitScaleFromSlider() {
            return this.queueCommit();
        },

        commitHeightFromSlider() {
            return this.queueCommit();
        },

        /** Подобрать zoom так, чтобы после cover-fit высота изображения совпала с высотой кадра превью. */
        fitScaleToFrameHeight(key) {
            if (this.previewShowFullImage || !this.canDrag(key)) {
                return;
            }
            const n = this.naturalFor(key);
            if (!n) {
                return;
            }
            const { w, h } = this.frameSize(key);
            const mode = this.geometryModeFor(key);
            const { dispH } = previewDisplaySize(n.iw, n.ih, w, h, mode);
            const { min, max, step } = this.scaleBounds();
            let s;
            if (mode === 'height_fit') {
                /** Базовый dispH уже равен высоте кадра; user scale 1 = по высоте, >1 — кроп по вертикали. */
                s = scaleForCommit(1, min, max, step);
            } else {
                s = 1;
                if (dispH > h + EPS) {
                    s = h / dispH;
                }
                s = scaleForCommit(s, min, max, step);
            }
            if (key === 'tablet') {
                this.local.tablet = { ...this.local.tablet, s };
                if (this.sync) {
                    this.local.mobile = { ...this.local.mobile, s };
                    this.local.desktop = { ...this.local.desktop, s };
                }
            } else if (key === 'desktop') {
                this.local.desktop = { ...this.local.desktop, s };
                if (this.sync) {
                    this.local.mobile = { ...this.local.mobile, s };
                    this.local.tablet = { ...this.local.tablet, s };
                }
            } else {
                this.local.mobile = { ...this.local.mobile, s };
                if (this.sync) {
                    this.local.tablet = { ...this.local.tablet, s };
                    this.local.desktop = { ...this.local.desktop, s };
                }
            }
            this.queueCommit();
        },

        nudge(key, dpx, dpy, shift) {
            if (this.previewShowFullImage) {
                return;
            }
            const n = this.naturalFor(key);
            if (!n) {
                return;
            }
            const { w, h } = this.frameSize(key);
            const mult = shift ? 0.05 : 0.01;
            const f = this.localFocal(key);
            const us = f.s ?? 1;
            const { min } = this.scaleBounds();
            const mode = this.geometryModeFor(key);
            const { tx, ty } = translateFromFocal(f.x, f.y, w, h, n.iw, n.ih, us, min, mode);
            const dtx = dpx * mult * w;
            const dty = dpy * mult * h;
            let ntx = tx + dtx;
            let nty = ty + dty;
            const c = clampTranslate(ntx, nty, w, h, n.iw, n.ih, us, min, mode);
            ntx = c.tx;
            nty = c.ty;
            const nf = focalFromTranslate(ntx, nty, w, h, n.iw, n.ih, us, min, mode);
            if (key === 'tablet') {
                this.local.tablet = { ...this.local.tablet, x: nf.x, y: nf.y, s: this.local.tablet.s };
                if (this.sync) {
                    const s = this.local.tablet.s;
                    this.local.mobile = { ...this.local.mobile, x: nf.x, y: nf.y, s };
                    this.local.desktop = { ...this.local.desktop, x: nf.x, y: nf.y, s };
                }
            } else if (key === 'desktop') {
                this.local.desktop = { ...this.local.desktop, x: nf.x, y: nf.y, s: this.local.desktop.s };
                if (this.sync) {
                    const s = this.local.desktop.s;
                    this.local.mobile = { ...this.local.mobile, x: nf.x, y: nf.y, s };
                    this.local.tablet = { ...this.local.tablet, x: nf.x, y: nf.y, s };
                }
            } else {
                this.local.mobile = { ...this.local.mobile, x: nf.x, y: nf.y, s: this.local.mobile.s };
                if (this.sync) {
                    const s = this.local.mobile.s;
                    this.local.tablet = { ...this.local.tablet, x: nf.x, y: nf.y, s };
                    this.local.desktop = { ...this.local.desktop, x: nf.x, y: nf.y, s };
                }
            }
            clearTimeout(this._nudgeDebounce);
            this._nudgeDebounce = setTimeout(() => this.queueCommit(), 120);
        },

        resetMobile() {
            const d = this.config.defaults.mobile;
            this.local.mobile = { x: d.x, y: d.y, s: d.s, heightFactor: d.heightFactor ?? 1 };
            this.local.tablet = { ...this.local.tablet, heightFactor: d.heightFactor ?? 1 };
            if (this.sync) {
                this.local.tablet = { ...this.local.tablet, x: d.x, y: d.y, s: d.s };
                this.local.desktop = {
                    ...this.local.desktop,
                    x: d.x,
                    y: d.y,
                    s: d.s,
                    heightFactor: this.config.defaults.desktop.heightFactor ?? 1,
                };
            }
            this.queueCommit();
        },

        resetDesktop() {
            const d = this.config.defaults.desktop;
            this.local.desktop = { x: d.x, y: d.y, s: d.s, heightFactor: d.heightFactor ?? 1 };
            if (this.sync) {
                this.local.mobile = {
                    ...this.local.mobile,
                    x: d.x,
                    y: d.y,
                    s: d.s,
                    heightFactor: this.config.defaults.mobile.heightFactor ?? 1,
                };
                this.local.tablet = { ...this.local.tablet, x: d.x, y: d.y, s: d.s, heightFactor: this.local.mobile.heightFactor };
            }
            this.queueCommit();
        },

        resetBoth() {
            const md = this.config.defaults.mobile;
            const mdT = this.config.defaults?.tablet ?? { x: 50, y: 50, s: 1, heightFactor: 1 };
            this.local.mobile = { ...md, heightFactor: md.heightFactor ?? 1 };
            this.local.tablet = { x: mdT.x, y: mdT.y, s: mdT.s, heightFactor: md.heightFactor ?? 1 };
            const dd = this.config.defaults.desktop;
            this.local.desktop = { ...dd, heightFactor: dd.heightFactor ?? 1 };
            this.queueCommit();
        },

        resetTablet() {
            const mdT = this.config.defaults?.tablet ?? { x: 50, y: 50, s: 1, heightFactor: 1 };
            this.local.tablet = {
                x: mdT.x,
                y: mdT.y,
                s: mdT.s,
                heightFactor: this.local.mobile?.heightFactor ?? 1,
            };
            if (this.sync) {
                this.local.mobile = { ...this.local.mobile, x: mdT.x, y: mdT.y, s: mdT.s };
                this.local.desktop = { ...this.local.desktop, x: mdT.x, y: mdT.y, s: mdT.s };
            }
            this.queueCommit();
        },

        copyToDesktop() {
            this.local.desktop = {
                ...this.local.desktop,
                x: this.local.mobile.x,
                y: this.local.mobile.y,
                s: this.local.mobile.s,
            };
            this.queueCommit();
        },

        copyToMobile() {
            this.local.mobile = {
                ...this.local.mobile,
                x: this.local.desktop.x,
                y: this.local.desktop.y,
                s: this.local.desktop.s,
            };
            this.local.tablet = {
                ...this.local.tablet,
                x: this.local.desktop.x,
                y: this.local.desktop.y,
                s: this.local.desktop.s,
            };
            this.queueCommit();
        },

        copyMobileToTablet() {
            this.local.tablet = {
                ...this.local.tablet,
                x: this.local.mobile.x,
                y: this.local.mobile.y,
                s: this.local.mobile.s,
            };
            this.queueCommit();
        },

        copyTabletToMobile() {
            this.local.mobile = {
                ...this.local.mobile,
                x: this.local.tablet.x,
                y: this.local.tablet.y,
                s: this.local.tablet.s,
            };
            this.queueCommit();
        },

        copyDesktopToTablet() {
            this.local.tablet = {
                ...this.local.tablet,
                x: this.local.desktop.x,
                y: this.local.desktop.y,
                s: this.local.desktop.s,
            };
            this.queueCommit();
        },

        copyTabletToDesktop() {
            this.local.desktop = {
                ...this.local.desktop,
                x: this.local.tablet.x,
                y: this.local.tablet.y,
                s: this.local.tablet.s,
            };
            this.queueCommit();
        },

        onImgLoad(key, ev) {
            this.loadError[key] = false;
            const img = ev.target;
            this.applyNaturalFromImage(key, img);
            this.$nextTick(() => this.setupResize());
        },

        onImgError(key) {
            this.loadError[key] = true;
            this.natural[key] = null;
        },

        retryFocalImage(key) {
            this.loadError[key] = false;
            this.natural[key] = null;
            const frame = this.frameRefs[key];
            const img = frame?.querySelector('img.svc-program-focal-img');
            if (img) {
                img.removeAttribute('data-svc-focal-bound');
                const raw = String(img.getAttribute('src') ?? img.src);
                try {
                    const u = new URL(raw, document.baseURI);
                    u.searchParams.set('_retry', String(Date.now()));
                    img.src = u.href;
                } catch (_) {
                    const b = raw.split('?')[0];
                    img.src = `${b}?_retry=${Date.now()}`;
                }
            }
            this.$nextTick(() => this.hydrateNaturalDimensionsFromImages());
        },

        setupResize() {
            if (typeof ResizeObserver !== 'function') {
                return;
            }
            if (this.ro) {
                this.ro.disconnect();
            }
            this.ro = new ResizeObserver(() => {
                this.frameVersion++;
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
