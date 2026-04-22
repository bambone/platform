<?php

declare(strict_types=1);

namespace App\TenantPush;

use App\Auth\AccessRoles;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPushEventPreference;
use App\Models\TenantPushSettings;

/**
 * Product-facing snapshot for guided push setup: step badges, gating flags, and {@see TenantPushGuidedSetupReason} (code + user message).
 * No form structure or long copy — only booleans, enums, and one primary reason to show next to disabled controls.
 */
final readonly class TenantPushGuidedSetupState
{
    public function __construct(
        public TenantPushStepStatus $step1,
        public TenantPushStepStatus $step2,
        public TenantPushStepStatus $step3,
        public TenantPushStepStatus $step4,
        public TenantPushGuidedSetupReason $primaryReason,
        public string $primaryReasonMessage,
        public bool $isFeatureEntitled,
        public bool $hasActiveDomain,
        public bool $hasEligibleDomain,
        public bool $hasConfiguredOneSignal,
        public bool $isOneSignalVerified,
        public bool $canEnablePush,
        public bool $canEnableCrmPush,
        /** Проверка OneSignal (HTTP) читает только БД: App ID + сохранённый ключ. */
        public bool $canVerifyOnesignal,
        /** Тестовый push читает только БД: то же + включённый push + подходящий HTTPS-домен. */
        public bool $canSendTestPush,
        public string $verifyActionDisabledMessage,
        public string $testPushActionDisabledMessage,
    ) {}

    /**
     * @param  array<string, mixed>|null  $formData  live form state; null = use only persisted $settings and $pref
     */
    public static function make(
        Tenant $tenant,
        TenantPushGateResult $gate,
        TenantPushSettings $settings,
        ?TenantPushEventPreference $pref,
        ?array $formData = null,
    ): self {
        $entitled = $gate->isFeatureEntitled();
        if (! $entitled) {
            $reason = TenantPushGuidedSetupReason::FeatureNotEntitled;
            $msg = $reason->userMessage();

            return new self(
                step1: TenantPushStepStatus::NotStarted,
                step2: TenantPushStepStatus::NotStarted,
                step3: TenantPushStepStatus::NotStarted,
                step4: TenantPushStepStatus::NotStarted,
                primaryReason: $reason,
                primaryReasonMessage: $msg,
                isFeatureEntitled: false,
                hasActiveDomain: $tenant->domains()->where('status', TenantDomain::STATUS_ACTIVE)->exists(),
                hasEligibleDomain: false,
                hasConfiguredOneSignal: false,
                isOneSignalVerified: false,
                canEnablePush: false,
                canEnableCrmPush: false,
                canVerifyOnesignal: false,
                canSendTestPush: false,
                verifyActionDisabledMessage: $msg,
                testPushActionDisabledMessage: $msg,
            );
        }

        $activeDomains = $tenant->domains()->where('status', TenantDomain::STATUS_ACTIVE)->get();
        $hasActiveDomain = $activeDomains->isNotEmpty();

        $host = self::submittedOrStoredHost($formData, $settings);
        $domain = $host !== null && $host !== ''
            ? $activeDomains->first(fn (TenantDomain $d): bool => strtolower((string) $d->host) === $host)
            : null;
        $sslOk = $domain !== null && in_array(
            (string) $domain->ssl_status,
            [TenantDomain::SSL_ISSUED, TenantDomain::SSL_NOT_REQUIRED],
            true,
        );
        $hasEligibleDomain = $host !== null && $host !== '' && $domain !== null && $sslOk;

        $appId = self::str($formData, 'onesignal_app_id', (string) ($settings->onesignal_app_id ?? ''));
        $newKey = self::str($formData, 'onesignal_app_api_key', '');
        $clearKey = (bool) ($formData['clear_onesignal_api_key'] ?? false);
        $hasStoredKey = self::hasStoredApiKey($settings);
        $effectiveKey = $clearKey ? ($newKey !== '' ? $newKey : null) : ($newKey !== '' ? $newKey : ($hasStoredKey ? 'stored' : null));
        $hasConfigured = $appId !== '' && $effectiveKey !== null;
        $verified = $settings->providerStatusEnum() === TenantPushProviderStatus::Verified;

        $isPushOn = $formData !== null
            ? (bool) ($formData['is_push_enabled'] ?? false)
            : (bool) $settings->is_push_enabled;

        $canEnablePush = $hasEligibleDomain && $appId !== '' && $effectiveKey !== null;

        $crmWants = $formData !== null
            ? (bool) ($formData['crm_push_enabled'] ?? false)
            : (bool) ($pref?->is_enabled ?? false);

        $recipientsOk = self::recipientsSatisfied(
            $tenant,
            $pref,
            $formData,
            $crmWants,
        );

        $canEnableCrm = $canEnablePush
            && $isPushOn
            && $verified
            && $recipientsOk;

        $step1 = TenantPushStepStatus::Ready;
        $step2 = ! $hasActiveDomain
            ? TenantPushStepStatus::NotStarted
            : (! $host || $host === '' ? TenantPushStepStatus::Partial
                : (! $hasEligibleDomain ? TenantPushStepStatus::Partial : TenantPushStepStatus::Ready));
        $step3 = $appId === '' ? TenantPushStepStatus::NotStarted
            : (! $hasConfigured ? TenantPushStepStatus::Partial
                : (! $verified ? TenantPushStepStatus::Partial : TenantPushStepStatus::Ready));
        $step4 = ! $crmWants
            ? TenantPushStepStatus::NotStarted
            : (! $canEnableCrm && $crmWants ? TenantPushStepStatus::Partial
                : TenantPushStepStatus::Ready);

        $primary = self::resolvePrimaryReason(
            hasActiveDomain: $hasActiveDomain,
            host: $host,
            hasEligibleDomain: $hasEligibleDomain,
            appId: $appId,
            hasConfigured: $hasConfigured,
            verified: $verified,
            isPushOn: $isPushOn,
            crmWants: $crmWants,
            recipientsOk: $recipientsOk,
        );

        $persistedAppId = trim((string) ($settings->onesignal_app_id ?? ''));
        $persistedHasKey = $hasStoredKey;
        $canVerifyPersisted = $persistedAppId !== '' && $persistedHasKey;
        $hasEligiblePersisted = self::hasHttpsEligibleFromPersistedHost($tenant, $settings);
        $isPushOnPersisted = (bool) $settings->is_push_enabled;
        $canTestPersisted = $canVerifyPersisted
            && $isPushOnPersisted
            && $hasEligiblePersisted;

        $verifyMsg = self::resolveVerifyActionMessage(
            persistedAppId: $persistedAppId,
            mergedAppId: $appId,
            persistedHasKey: $persistedHasKey,
            newKeyInForm: $newKey,
        );
        $testMsg = self::resolveTestPushActionMessage(
            canTestPersisted: $canTestPersisted,
            canVerifyPersisted: $canVerifyPersisted,
            isPushOnPersisted: $isPushOnPersisted,
            hasEligiblePersisted: $hasEligiblePersisted,
            verifyMessage: $verifyMsg,
        );

        return new self(
            step1: $step1,
            step2: $step2,
            step3: $step3,
            step4: $step4,
            primaryReason: $primary[0],
            primaryReasonMessage: $primary[1] !== '' ? $primary[1] : $primary[0]->userMessage(),
            isFeatureEntitled: true,
            hasActiveDomain: $hasActiveDomain,
            hasEligibleDomain: $hasEligibleDomain,
            hasConfiguredOneSignal: $hasConfigured,
            isOneSignalVerified: $verified,
            canEnablePush: $canEnablePush,
            canEnableCrmPush: $canEnableCrm,
            canVerifyOnesignal: $canVerifyPersisted,
            canSendTestPush: $canTestPersisted,
            verifyActionDisabledMessage: $verifyMsg,
            testPushActionDisabledMessage: $testMsg,
        );
    }

    public function verifyDisabledMessage(): string
    {
        return $this->verifyActionDisabledMessage;
    }

    public function testPushDisabledMessage(): string
    {
        return $this->testPushActionDisabledMessage;
    }

    /**
     * @return array{0: TenantPushGuidedSetupReason, 1: string}
     */
    private static function resolvePrimaryReason(
        bool $hasActiveDomain,
        ?string $host,
        bool $hasEligibleDomain,
        string $appId,
        bool $hasConfigured,
        bool $verified,
        bool $isPushOn,
        bool $crmWants,
        bool $recipientsOk,
    ): array {
        if (! $hasActiveDomain) {
            return [TenantPushGuidedSetupReason::NoActiveDomain, ''];
        }
        if ($host === null || $host === '') {
            return [TenantPushGuidedSetupReason::DomainNotSelected, ''];
        }
        if (! $hasEligibleDomain) {
            return [TenantPushGuidedSetupReason::SslNotReady, ''];
        }
        if ($appId === '') {
            return [TenantPushGuidedSetupReason::OneSignalAppIdMissing, ''];
        }
        if (! $hasConfigured) {
            return [TenantPushGuidedSetupReason::OneSignalApiKeyMissing, ''];
        }
        if (! $verified) {
            return [TenantPushGuidedSetupReason::OneSignalNotVerified, ''];
        }
        if (! $isPushOn) {
            return [TenantPushGuidedSetupReason::PushSendingNotEnabled, ''];
        }
        if ($crmWants && ! $recipientsOk) {
            return [TenantPushGuidedSetupReason::CrmRecipientsMissing, ''];
        }

        return [TenantPushGuidedSetupReason::None, ''];
    }

    private static function hasStoredApiKey(TenantPushSettings $settings): bool
    {
        $attrs = $settings->getAttributes();

        return array_key_exists('onesignal_app_api_key_encrypted', $attrs)
            && $attrs['onesignal_app_api_key_encrypted'] !== null
            && $attrs['onesignal_app_api_key_encrypted'] !== '';
    }

    private static function hasHttpsEligibleFromPersistedHost(Tenant $tenant, TenantPushSettings $settings): bool
    {
        $h = $settings->canonical_host;
        if (! is_string($h) || $h === '') {
            return false;
        }
        $h = strtolower(trim($h));
        $domain = $tenant->domains()
            ->where('status', TenantDomain::STATUS_ACTIVE)
            ->get()
            ->first(fn (TenantDomain $d): bool => strtolower((string) $d->host) === $h);

        if ($domain === null) {
            return false;
        }

        return in_array((string) $domain->ssl_status, [TenantDomain::SSL_ISSUED, TenantDomain::SSL_NOT_REQUIRED], true);
    }

    private static function resolveVerifyActionMessage(
        string $persistedAppId,
        string $mergedAppId,
        bool $persistedHasKey,
        string $newKeyInForm,
    ): string {
        $pApp = trim($persistedAppId);
        $mApp = trim($mergedAppId);
        if ($pApp !== '' && $persistedHasKey) {
            return '';
        }
        if ($pApp === '' && $mApp !== '') {
            return 'Сохраните настройки: проверка OneSignal использует App ID и ключ, уже записанные в систему.';
        }
        if ($pApp === '') {
            return TenantPushGuidedSetupReason::OneSignalAppIdMissing->userMessage();
        }
        if (! $persistedHasKey && trim($newKeyInForm) !== '') {
            return 'Сохраните настройки, чтобы App API Key применился — после этого станет доступна проверка подключения.';
        }

        return TenantPushGuidedSetupReason::OneSignalApiKeyMissing->userMessage();
    }

    private static function resolveTestPushActionMessage(
        bool $canTestPersisted,
        bool $canVerifyPersisted,
        bool $isPushOnPersisted,
        bool $hasEligiblePersisted,
        string $verifyMessage,
    ): string {
        if ($canTestPersisted) {
            return '';
        }
        if (! $canVerifyPersisted) {
            return $verifyMessage !== ''
                ? $verifyMessage
                : TenantPushGuidedSetupReason::OneSignalApiKeyMissing->userMessage();
        }
        if (! $isPushOnPersisted) {
            return TenantPushGuidedSetupReason::PushSendingNotEnabled->userMessage();
        }
        if (! $hasEligiblePersisted) {
            return 'Для теста выберите и сохраните основной HTTPS-домен с выпущенным SSL, затем снова попробуйте.';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>|null  $formData
     */
    private static function submittedOrStoredHost(?array $formData, TenantPushSettings $settings): ?string
    {
        if ($formData !== null) {
            $h = strtolower(trim((string) ($formData['canonical_host'] ?? '')));

            return $h !== '' ? $h : null;
        }
        $h = $settings->canonical_host;

        return is_string($h) && $h !== '' ? strtolower($h) : null;
    }

    /**
     * @param  array<string, mixed>|null  $formData
     */
    private static function str(?array $formData, string $key, string $fallback): string
    {
        if ($formData === null) {
            return trim($fallback);
        }

        return trim((string) ($formData[$key] ?? $fallback));
    }

    /**
     * @param  array<string, mixed>|null  $formData
     */
    private static function recipientsSatisfied(
        Tenant $tenant,
        ?TenantPushEventPreference $pref,
        ?array $formData,
        bool $crmWants,
    ): bool {
        if (! $crmWants) {
            return true;
        }
        if ($formData === null) {
            if ($pref === null || ! $pref->is_enabled) {
                return false;
            }
            $scope = $pref->recipientScopeEnum();

            return self::recipientsSatisfiedForScope($tenant, $scope, $pref->selectedUserIds());
        }

        $scope = TenantPushRecipientScope::tryFrom((string) ($formData['recipient_scope'] ?? ''))
            ?? TenantPushRecipientScope::OwnerOnly;
        $raw = $formData['selected_user_ids'] ?? [];
        $ids = is_array($raw) ? $raw : [];

        if ($scope === TenantPushRecipientScope::OwnerOnly) {
            $owner = $tenant->owner_user_id;

            return $owner !== null && (int) $owner > 0;
        }
        if ($scope === TenantPushRecipientScope::AllAdmins) {
            return self::allAdminUserIds($tenant) !== [];
        }
        if ($scope === TenantPushRecipientScope::SelectedUsers) {
            $sanitized = (new TenantPushCrmRequestRecipientResolver)->sanitizeSelectedUserIdsForSave($tenant, $ids);

            return $sanitized !== [];
        }

        return true;
    }

    /**
     * @param  list<int>  $selectedFromPref
     */
    private static function recipientsSatisfiedForScope(
        Tenant $tenant,
        TenantPushRecipientScope $scope,
        array $selectedFromPref,
    ): bool {
        return match ($scope) {
            TenantPushRecipientScope::OwnerOnly => $tenant->owner_user_id !== null && (int) $tenant->owner_user_id > 0,
            TenantPushRecipientScope::AllAdmins => self::allAdminUserIds($tenant) !== [],
            TenantPushRecipientScope::SelectedUsers => $selectedFromPref !== [],
        };
    }

    /**
     * @return list<int>
     */
    /**
     * Совпадает с логикой {@see TenantPushCrmRequestRecipientResolver::allAdminUserIds}.
     *
     * @return list<int>
     */
    private static function allAdminUserIds(Tenant $tenant): array
    {
        $ids = [];
        $tenant->users()
            ->wherePivot('status', 'active')
            ->wherePivotIn('role', AccessRoles::tenantMembershipRolesForPanel())
            ->get()
            ->each(function ($u) use (&$ids): void {
                $ids[] = (int) $u->id;
            });

        return array_values(array_unique($ids));
    }
}
