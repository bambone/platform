<?php

declare(strict_types=1);

namespace Tests\Unit\PageBuilder;

use App\PageBuilder\Expert\EditorialGalleryExternalArticlePreviewApplier;
use App\Services\LinkPreview\ExternalArticlePreviewData;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EditorialGalleryExternalArticlePreviewApplierTest extends TestCase
{
    private function okData(string $title = 'T', string $canonical = 'https://ex.test/a'): ExternalArticlePreviewData
    {
        return new ExternalArticlePreviewData(
            title: $title,
            description: 'D',
            siteName: 'S',
            domain: 'ex.test',
            canonicalUrl: $canonical,
            imageUrl: 'https://ex.test/i.jpg',
            imageWidth: null,
            imageHeight: null,
            fetchedAt: new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
            ok: true,
            errorCode: '',
            errorMessage: '',
            finalUrl: $canonical,
        );
    }

    public function test_refresh_patch_updates_fetched_only_not_manual_title(): void
    {
        $item = [
            'article_title' => 'Manual',
            'article_image_mode' => EditorialGalleryExternalArticlePreviewApplier::IMAGE_TENANT_FILE,
            'article_image_override_url' => 'site/x.jpg',
            'article_last_fetched_input_url' => 'https://ex.test/a',
            'article_fetch_status' => EditorialGalleryExternalArticlePreviewApplier::FETCH_OK,
        ];
        $patch = EditorialGalleryExternalArticlePreviewApplier::applyRefreshResult(
            $item,
            $this->okData(title: 'NewFetched'),
            'https://ex.test/a',
        );
        $this->assertSame('NewFetched', $patch['article_fetched_title']);
        $this->assertArrayNotHasKey('article_title', $patch);
    }

    public function test_url_change_resets_manual_title_from_new_fetch(): void
    {
        $item = [
            'article_title' => 'OldManual',
            'article_last_fetched_input_url' => 'https://ex.test/old',
            'article_fetch_status' => EditorialGalleryExternalArticlePreviewApplier::FETCH_OK,
            'article_image_mode' => EditorialGalleryExternalArticlePreviewApplier::IMAGE_TENANT_FILE,
            'article_image_override_url' => 'site/old.jpg',
        ];
        $patch = EditorialGalleryExternalArticlePreviewApplier::applyAutoFetchResult(
            $item,
            $this->okData(title: 'FromB', canonical: 'https://ex.test/b'),
            'https://ex.test/b',
        );
        $this->assertSame('FromB', $patch['article_title']);
        $this->assertSame(EditorialGalleryExternalArticlePreviewApplier::IMAGE_SUGGESTED, $patch['article_image_mode']);
        $this->assertSame('', $patch['article_image_override_url']);
    }

    public function test_first_success_fills_article_when_empty(): void
    {
        $item = [
            'article_last_fetched_input_url' => '',
            'article_fetch_status' => EditorialGalleryExternalArticlePreviewApplier::FETCH_IDLE,
        ];
        $patch = EditorialGalleryExternalArticlePreviewApplier::applyAutoFetchResult(
            $item,
            $this->okData(),
            'https://ex.test/a',
        );
        $this->assertSame('T', $patch['article_title']);
    }

    public function test_should_skip_auto_fetch_when_same_url_and_ok(): void
    {
        $item = [
            'article_last_fetched_input_url' => 'https://ex.test/a',
            'article_fetch_status' => EditorialGalleryExternalArticlePreviewApplier::FETCH_OK,
        ];
        $this->assertTrue(EditorialGalleryExternalArticlePreviewApplier::shouldSkipAutoFetch('https://ex.test/a', $item));
    }

    public function test_should_skip_auto_fetch_when_utm_differs_but_matches_saved_canonical(): void
    {
        $item = [
            'article_last_fetched_input_url' => 'https://ex.test/a?utm_source=x',
            'article_last_fetch_canonical_url' => 'https://ex.test/a',
            'article_fetch_status' => EditorialGalleryExternalArticlePreviewApplier::FETCH_OK,
        ];
        $this->assertTrue(
            EditorialGalleryExternalArticlePreviewApplier::shouldSkipAutoFetch('https://ex.test/a?utm_campaign=y', $item),
        );
    }

    public function test_should_not_skip_auto_fetch_when_path_differs_from_canonical(): void
    {
        $item = [
            'article_last_fetched_input_url' => 'https://ex.test/a',
            'article_last_fetch_canonical_url' => 'https://ex.test/a',
            'article_fetch_status' => EditorialGalleryExternalArticlePreviewApplier::FETCH_OK,
        ];
        $this->assertFalse(
            EditorialGalleryExternalArticlePreviewApplier::shouldSkipAutoFetch('https://ex.test/b', $item),
        );
    }

    public function test_url_material_identity_strips_utm_and_normalizes_path(): void
    {
        $id = EditorialGalleryExternalArticlePreviewApplier::urlMaterialIdentityForAutoFetchDedupe(
            'HTTPS://Ex.TEST/a/?utm_source=1',
        );
        $this->assertSame('https://ex.test/a', $id);
    }
}
