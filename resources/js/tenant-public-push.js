/**
 * OneSignal Web SDK (custom code). Server sets window.__tenantPush = { appId } on canonical HTTPS host only.
 * iOS: web push needs 16.4+, Add to Home Screen, then open from icon; standalone is exposed via navigator.standalone.
 * @see https://documentation.onesignal.com/docs/web-push-custom-code-setup
 */
(function () {
    const cfg = typeof window !== 'undefined' ? window.__tenantPush : null;
    if (!cfg || !cfg.appId) {
        return;
    }

    const ua = typeof navigator !== 'undefined' ? navigator.userAgent || '' : '';
    const isIos =
        /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const iosStandalone = typeof navigator !== 'undefined' && navigator.standalone === true;
    document.documentElement.dataset.onesignalIos = isIos ? (iosStandalone ? 'standalone' : 'browser') : 'na';

    window.OneSignalDeferred = window.OneSignalDeferred || [];
    window.OneSignalDeferred.push(async function (OneSignal) {
        await OneSignal.init({
            appId: cfg.appId,
            serviceWorkerPath: '/push/onesignal/OneSignalSDKWorker.js',
            serviceWorkerUpdaterPath: '/push/onesignal/OneSignalSDKUpdaterWorker.js',
        });
    });

    const s = document.createElement('script');
    s.src = 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js';
    s.defer = true;
    document.head.appendChild(s);
})();
