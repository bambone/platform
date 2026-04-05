<?php

namespace App\Services\Seo\Data;

final readonly class TenantSeoAutopilotResult
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(
        public bool $dryRun,
        public bool $wroteLlmsIntro,
        public bool $wroteLlmsEntries,
        public bool $wroteRouteOverrides,
        public bool $touchedHomeSeoMeta,
        public array $messages = [],
    ) {}
}
