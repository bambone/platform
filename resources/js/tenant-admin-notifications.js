/**
 * Tenant admin: Web Push subscription, UI preferences, sound on page (not in SW).
 */
function readConfig() {
    const el = document.getElementById('tenant-notify-page-config');
    if (!el || !el.textContent) {
        return null;
    }
    try {
        return JSON.parse(el.textContent);
    } catch {
        return null;
    }
}

function csrfHeaders(cfg) {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': cfg.csrf,
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

let audioContextSingleton = null;

function getAudioContext() {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) {
        return null;
    }
    if (!audioContextSingleton) {
        audioContextSingleton = new Ctx();
    }
    return audioContextSingleton;
}

/** Short beep using Web Audio (works after user gesture / resume). */
async function playNotificationChime() {
    const ctx = getAudioContext();
    if (!ctx) {
        return;
    }
    if (ctx.state === 'suspended') {
        try {
            await ctx.resume();
        } catch {
            return;
        }
    }
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.type = 'sine';
    o.frequency.value = 880;
    o.connect(g);
    g.connect(ctx.destination);
    g.gain.setValueAtTime(0.08, ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);
    o.start(ctx.currentTime);
    o.stop(ctx.currentTime + 0.2);
}

function wmStorageKey(tenantId, userId) {
    return `tenant_notify_crm_wm_${tenantId ?? '0'}_${userId ?? '0'}`;
}

async function loadPreferences(cfg) {
    const r = await fetch(cfg.preferencesLoadUrl, {
        credentials: 'same-origin',
        headers: csrfHeaders(cfg),
    });
    if (!r.ok) {
        return null;
    }
    const data = await r.json();
    return data.preferences ?? null;
}

async function savePreferences(cfg, partial) {
    const r = await fetch(cfg.preferencesSaveUrl, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: csrfHeaders(cfg),
        body: JSON.stringify(partial),
    });
    if (!r.ok) {
        return null;
    }
    const data = await r.json();
    return data.preferences ?? null;
}

function setStatus(el, text) {
    if (el) {
        el.textContent = text;
    }
}

function init() {
    const cfg = readConfig();
    if (!cfg) {
        return;
    }

    const statusEl = document.getElementById('tenant-notify-status');
    const soundStatusEl = document.getElementById('tenant-notify-sound-status');
    const btnPerm = document.getElementById('tenant-notify-btn-permission');
    const btnUnsub = document.getElementById('tenant-notify-btn-unsubscribe');
    const btnSoundToggle = document.getElementById('tenant-notify-btn-sound-toggle');
    const btnTestSound = document.getElementById('tenant-notify-btn-test-sound');

    let lastWatermarkForSound = 0;
    try {
        const raw = sessionStorage.getItem(wmStorageKey(cfg.tenantId, cfg.userId));
        if (raw) {
            lastWatermarkForSound = parseInt(raw, 10) || 0;
        }
    } catch {
        lastWatermarkForSound = 0;
    }

    let prefs = null;
    let pollTimer = null;

    function persistWatermark() {
        try {
            sessionStorage.setItem(
                wmStorageKey(cfg.tenantId, cfg.userId),
                String(lastWatermarkForSound),
            );
        } catch {
            /* ignore */
        }
    }

    /**
     * Дедупликация между poll и postMessage: один event_id — не более одного сигнала.
     */
    function tryChimeForWatermark(wm) {
        if (!Number.isFinite(wm) || wm <= 0) {
            return;
        }
        if (wm <= lastWatermarkForSound) {
            return;
        }
        lastWatermarkForSound = wm;
        persistWatermark();
        void playNotificationChime();
    }

    async function refreshPrefsUi() {
        prefs = await loadPreferences(cfg);
        if (!prefs) {
            setStatus(soundStatusEl, 'Не удалось загрузить настройки.');
            return;
        }
        const perm =
            typeof Notification !== 'undefined' ? Notification.permission : 'unsupported';
        setStatus(
            statusEl,
            `Разрешение браузера: ${perm}. ` +
                `В БД: уведомления ${prefs.browser_notifications_enabled ? 'вкл' : 'выкл'}, ` +
                `последнее известное состояние: ${prefs.last_permission_state}.`,
        );
        setStatus(
            soundStatusEl,
            `Звук в кабинете: ${prefs.sound_enabled ? 'включён' : 'выключен'}. ` +
                'Воспроизводится только при открытой вкладке.',
        );
    }

    function startPollIfNeeded() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (!prefs || !prefs.sound_enabled) {
            return;
        }
        pollTimer = window.setInterval(async () => {
            if (document.visibilityState !== 'visible') {
                return;
            }
            if (!prefs.sound_enabled) {
                return;
            }
            try {
                const r = await fetch(cfg.watermarkUrl, {
                    credentials: 'same-origin',
                    headers: csrfHeaders(cfg),
                });
                if (!r.ok) {
                    return;
                }
                const data = await r.json();
                const wm = parseInt(data.watermark, 10) || 0;
                tryChimeForWatermark(wm);
            } catch {
                /* ignore */
            }
        }, 20000);
    }

    navigator.serviceWorker?.addEventListener('message', (event) => {
        const d = event.data;
        if (!d || d.type !== 'TENANT_PUSH_RECEIVED') {
            return;
        }
        if (document.visibilityState !== 'visible') {
            return;
        }
        if (!prefs || !prefs.sound_enabled) {
            return;
        }
        const payload = d.payload || {};
        const eventId = parseInt(payload.notification_event_id, 10);
        if (Number.isFinite(eventId) && eventId > 0) {
            tryChimeForWatermark(eventId);
        }
    });

    async function syncWatermarkBaseline() {
        try {
            const r = await fetch(cfg.watermarkUrl, {
                credentials: 'same-origin',
                headers: csrfHeaders(cfg),
            });
            if (!r.ok) {
                return;
            }
            const data = await r.json();
            const wm = parseInt(data.watermark, 10) || 0;
            if (lastWatermarkForSound === 0 && wm > 0) {
                lastWatermarkForSound = wm;
                persistWatermark();
            }
        } catch {
            /* ignore */
        }
    }

    refreshPrefsUi()
        .then(() => syncWatermarkBaseline())
        .then(() => {
            startPollIfNeeded();
        });

    btnPerm?.addEventListener('click', async () => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            setStatus(statusEl, 'Браузер не поддерживает push.');
            return;
        }
        try {
            const perm = await Notification.requestPermission();
            await savePreferences(cfg, { last_permission_state: perm });
            if (perm !== 'granted') {
                await refreshPrefsUi();
                setStatus(
                    statusEl,
                    perm === 'denied'
                        ? 'Уведомления заблокированы в браузере. Разрешите их в настройках сайта и повторите попытку.'
                        : 'Разрешение на уведомления не выдано. Подписка на push не выполняется.',
                );
                return;
            }

            const reg = await navigator.serviceWorker.register(cfg.swUrl, { scope: '/' });
            await navigator.serviceWorker.ready;
            const vapidRes = await fetch(cfg.vapidUrl, {
                credentials: 'same-origin',
                headers: csrfHeaders(cfg),
            });
            const vapidJson = await vapidRes.json();
            const key = vapidJson.vapid_public_key;
            if (!key) {
                setStatus(
                    statusEl,
                    'VAPID не настроен на платформе. Обратитесь к администратору платформы.',
                );
                return;
            }

            let sub = await reg.pushManager.getSubscription();
            if (!sub) {
                sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(key),
                });
            }

            const json = sub.toJSON();
            const keyBuf = json.keys;
            if (!keyBuf || !keyBuf.p256dh || !keyBuf.auth) {
                setStatus(statusEl, 'Не удалось получить ключи подписки.');
                return;
            }
            const body = {
                endpoint: sub.endpoint,
                public_key: keyBuf.p256dh,
                auth_token: keyBuf.auth,
                device_label: navigator.userAgent?.slice(0, 120) ?? null,
            };
            const store = await fetch(cfg.pushStoreUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(cfg),
                body: JSON.stringify(body),
            });
            if (!store.ok) {
                setStatus(statusEl, 'Не удалось сохранить подписку на сервере.');
                return;
            }
            await savePreferences(cfg, {
                browser_notifications_enabled: true,
                last_permission_state: perm,
            });
            await refreshPrefsUi();
            startPollIfNeeded();
            setStatus(statusEl, 'Подписка сохранена. Системные уведомления включены.');
        } catch (e) {
            setStatus(statusEl, 'Ошибка: ' + (e?.message || String(e)));
        }
    });

    btnUnsub?.addEventListener('click', async () => {
        try {
            const reg = await navigator.serviceWorker?.ready;
            const sub = await reg?.pushManager?.getSubscription();
            if (sub) {
                const endpoint = sub.endpoint;
                await fetch(cfg.pushDestroyUrl, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        ...csrfHeaders(cfg),
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ endpoint }),
                });
                await sub.unsubscribe();
            }
            await savePreferences(cfg, { browser_notifications_enabled: false });
            await refreshPrefsUi();
            setStatus(statusEl, 'Push отключён для этого браузера.');
        } catch (e) {
            setStatus(statusEl, 'Ошибка отписки: ' + (e?.message || String(e)));
        }
    });

    btnSoundToggle?.addEventListener('click', async () => {
        const next = !(prefs && prefs.sound_enabled);
        const saved = await savePreferences(cfg, { sound_enabled: next });
        if (saved) {
            prefs = { ...prefs, ...saved };
        }
        await refreshPrefsUi();
        if (next) {
            void playNotificationChime();
        }
        startPollIfNeeded();
    });

    btnTestSound?.addEventListener('click', () => {
        void playNotificationChime();
        setStatus(soundStatusEl, 'Если вы слышали сигнал, воспроизведение разблокировано для этой вкладки.');
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshPrefsUi().then(() => startPollIfNeeded());
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
