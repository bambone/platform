<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use App\Scheduling\SchedulingTimezoneOptions;
use PHPUnit\Framework\TestCase;

class SchedulingTimezoneOptionsTest extends TestCase
{
    public function test_normalize_to_known_returns_canonical_identifier(): void
    {
        $this->assertSame('Europe/Moscow', SchedulingTimezoneOptions::normalizeToKnown('europe/moscow'));
        $this->assertSame('UTC', SchedulingTimezoneOptions::normalizeToKnown('UTC'));
    }

    public function test_normalize_to_known_preserves_unknown_non_empty(): void
    {
        $this->assertSame(
            'Not/A/Zone',
            SchedulingTimezoneOptions::normalizeToKnown('Not/A/Zone')
        );
    }

    public function test_try_resolve_returns_null_for_garbage(): void
    {
        $this->assertNull(SchedulingTimezoneOptions::tryResolveToKnownIdentifier('Not/A/Zone'));
    }
}
