<?php

namespace App\Product\CRM\DTO;

/**
 * @param  array<string, mixed>  $payloadJson
 */
final class PublicInboundSubmission
{
    /**
     * @param  array<string, mixed>  $payloadJson
     */
    public function __construct(
        public readonly string $requestType,
        public readonly string $name,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $message,
        public readonly ?string $source,
        public readonly string $channel = 'web',
        public readonly array $payloadJson = [],
        public readonly ?string $utmSource = null,
        public readonly ?string $utmMedium = null,
        public readonly ?string $utmCampaign = null,
        public readonly ?string $utmContent = null,
        public readonly ?string $utmTerm = null,
        public readonly ?string $referrer = null,
        public readonly ?string $landingPage = null,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $leadInitialStatus = null,
        public readonly ?string $preferredContactChannel = null,
        public readonly ?string $preferredContactValue = null,
        /** @var list<array<string, mixed>>|null */
        public readonly ?array $visitorContactChannelsJson = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $legalAcceptancesJson = null,
    ) {}
}
