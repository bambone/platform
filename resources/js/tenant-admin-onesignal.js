/**
 * OneSignal in tenant Filament: same external id as server (`t{tenantId}_u{userId}`), then POST identity for routing.
 * Config: window.__tenantAdminOnesignal (see tenant-admin-onesignal-config blade).
 */
(function () {
    const cfg = typeof window !== 'undefined' ? window.__tenantAdminOnesignal : null;
    if (!cfg || !cfg.appId || !cfg.identityUrl) {
        return;
    }

    window.OneSignalDeferred = window.OneSignalDeferred || [];
    window.OneSignalDeferred.push(async function (OneSignal) {
        await OneSignal.init({
            appId: cfg.appId,
            serviceWorkerPath: '/push/onesignal/OneSignalSDKWorker.js',
            serviceWorkerUpdaterPath: '/push/onesignal/OneSignalSDKUpdaterWorker.js',
        });
        if (cfg.externalUserId) {
            await OneSignal.login(cfg.externalUserId);
        }
        try {
            await fetch(cfg.identityUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': cfg.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: '{}',
            });
        } catch (_) {
            /* non-fatal: identity sync is best-effort */
        }
        if (typeof navigator !== 'undefined' && navigator.standalone === true) {
            document.cookie = 'rb_ios_standalone=1;path=/;max-age=86400;SameSite=Lax';
        }
    });

    const s = document.createElement('script');
    s.src = 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js';
    s.defer = true;
    document.head.appendChild(s);
})();
