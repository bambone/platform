@php
    use App\TenantPush\OneSignalExternalUserId;
    use App\TenantPush\TenantPushFeatureGate;

    $tenant = currentTenant();
    $user = auth()->user();
    $ps = $tenant?->pushSettings;
    $canon = $ps && $ps->canonical_host ? strtolower(trim((string) $ps->canonical_host)) : '';
    $host = strtolower(request()->getHost());
    $canonicalOk = $canon !== '' && $host === $canon;
    $gate = $tenant ? app(TenantPushFeatureGate::class)->evaluate($tenant) : null;
    $shouldBootstrap =
        $tenant
        && $user
        && $ps
        && $gate?->isFeatureEntitled()
        && $ps->is_push_enabled
        && trim((string) $ps->onesignal_app_id) !== ''
        && $canonicalOk
        && request()->secure();
@endphp
@if ($shouldBootstrap)
    @php
        $externalId = OneSignalExternalUserId::format((int) $tenant->id, (int) $user->id);
        $tenantAdminOnesignalPayload = [
            'appId' => $ps->onesignal_app_id,
            'externalUserId' => $externalId,
            'identityUrl' => route('filament.admin.notification-push.onesignal.identity.store'),
            'csrfToken' => csrf_token(),
        ];
    @endphp
    <div id="rb-tenant-admin-onesignal" hidden data-config="{{ e(json_encode($tenantAdminOnesignalPayload)) }}"></div>
    <script>
        (function () {
            var el = document.getElementById('rb-tenant-admin-onesignal');
            if (!el || !el.dataset.config) {
                return;
            }
            try {
                window.__tenantAdminOnesignal = JSON.parse(el.dataset.config);
            } catch (err) {}
        })();
    </script>
@endif
