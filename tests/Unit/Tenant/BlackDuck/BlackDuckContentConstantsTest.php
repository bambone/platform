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

    public function test_home_service_preview_is_q1_without_hash_slugs(): void
    {
        $expected = [];
        foreach (BlackDuckContentConstants::serviceMatrixQ1() as $row) {
            $slug = (string) $row['slug'];
            if (str_starts_with($slug, '#')) {
                continue;
            }
            $expected[] = $slug;
        }
        $slugs = array_map(
            static fn (array $row): string => (string) $row['slug'],
            BlackDuckContentConstants::serviceMatrixHomePreview(),
        );
        $this->assertSame($expected, $slugs);
        $this->assertSame($expected, BlackDuckContentConstants::homeServicePreviewSlugs());
    }

    public function test_contacts_inquiry_url_includes_service_query(): void
    {
        $this->assertSame(
            '/contacts?service=ppf#contact-inquiry',
            BlackDuckContentConstants::contactsInquiryUrlForServiceSlug('ppf'),
        );
        $this->assertSame(BlackDuckContentConstants::PRIMARY_LEAD_URL, BlackDuckContentConstants::contactsInquiryUrlForServiceSlug('#x'));
    }

    public function test_service_landing_book_intent_url(): void
    {
        $this->assertSame('/detejling-mojka?book=1', BlackDuckContentConstants::serviceLandingBookIntentUrl('detejling-mojka'));
    }
}
