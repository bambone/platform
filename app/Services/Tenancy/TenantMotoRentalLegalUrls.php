<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Support\Storage\TenantStorage;

/**
 * Публичные URL юридических страниц и PDF в {@code tenants/{id}/public/media/} для мото-витрины.
 */
final class TenantMotoRentalLegalUrls
{
    public const CONTRACT_FILENAME = 'dogovor-prokata-motocikla.pdf';

    public const RULES_FILENAME = 'pravila-prokata-motociklov.pdf';

    public const SAFETY_FILENAME = 'instrukciya-po-tb.pdf';

    /**
     * @return array{
     *   contract_pdf_url: string,
     *   rules_pdf_url: string,
     *   safety_pdf_url: string,
     *   terms_url: string,
     *   privacy_url: string
     * }
     */
    public function forTenant(Tenant $tenant): array
    {
        $ts = TenantStorage::forTrusted($tenant);
        $media = TenantStorage::MEDIA_FOLDER;

        $urlIfExists = static function (string $basename) use ($ts, $media): string {
            $rel = $media.'/'.ltrim($basename, '/');
            if ($rel === $media.'/' || str_contains($rel, '..')) {
                return '';
            }
            if (! $ts->existsPublic($rel)) {
                return '';
            }

            return $ts->publicUrl($rel);
        };

        return [
            'contract_pdf_url' => $urlIfExists(self::CONTRACT_FILENAME),
            'rules_pdf_url' => $urlIfExists(self::RULES_FILENAME),
            'safety_pdf_url' => $urlIfExists(self::SAFETY_FILENAME),
            'terms_url' => route('terms'),
            'privacy_url' => route('privacy'),
        ];
    }

    /**
     * Снимок для {@see \App\Models\Booking::legal_acceptances_json} при оформлении публичной брони.
     *
     * @return array<string, mixed>
     */
    public function acceptanceSnapshotForBooking(Tenant $tenant): array
    {
        $u = $this->forTenant($tenant);

        return [
            'accepted_at' => now()->toIso8601String(),
            'terms_url' => $u['terms_url'],
            'privacy_url' => $u['privacy_url'],
            'contract_pdf_url' => $u['contract_pdf_url'] !== '' ? $u['contract_pdf_url'] : null,
            'rules_pdf_url' => $u['rules_pdf_url'] !== '' ? $u['rules_pdf_url'] : null,
            'safety_pdf_url' => $u['safety_pdf_url'] !== '' ? $u['safety_pdf_url'] : null,
        ];
    }
}
