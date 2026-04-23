<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\BlackDuck;

use App\Tenant\BlackDuck\BlackDuckContentConstants;
use Tests\TestCase;

class BlackDuckContentConstantsTest extends TestCase
{
    public function test_instagram_url_for_public_empty_when_not_configured(): void
    {
        $this->assertSame('', BlackDuckContentConstants::instagramUrlForPublic());
    }

    public function test_home_service_preview_preserves_constants_order(): void
    {
        $slugs = array_map(
            static fn (array $row): string => (string) $row['slug'],
            BlackDuckContentConstants::serviceMatrixHomePreview(),
        );
        $this->assertSame(BlackDuckContentConstants::HOME_SERVICE_PREVIEW_SLUGS, $slugs);
    }
}
