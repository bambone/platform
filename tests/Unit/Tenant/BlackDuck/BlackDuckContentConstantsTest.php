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

    public function test_home_service_preview_follows_configured_slugs_in_matrix_order(): void
    {
        $q1Slugs = [];
        foreach (BlackDuckContentConstants::serviceMatrixQ1() as $row) {
            $q1Slugs[(string) $row['slug']] = true;
        }
        $slugs = array_map(
            static fn (array $row): string => (string) $row['slug'],
            BlackDuckContentConstants::serviceMatrixHomePreview(),
        );
        $this->assertSame(BlackDuckContentConstants::HOME_SERVICE_PREVIEW_SLUGS, $slugs);
        foreach ($slugs as $slug) {
            $this->assertArrayHasKey($slug, $q1Slugs, 'Каждая карточка превью должна ссылаться на строку Q1-матрицы');
        }
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
