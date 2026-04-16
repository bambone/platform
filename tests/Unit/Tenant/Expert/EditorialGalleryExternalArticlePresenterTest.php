<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Expert;

use App\PageBuilder\Expert\EditorialGalleryExternalArticlePreviewApplier;
use App\Tenant\Expert\EditorialGalleryExternalArticlePresenter;
use PHPUnit\Framework\TestCase;

final class EditorialGalleryExternalArticlePresenterTest extends TestCase
{
    public function test_effective_title_falls_back_to_fetched(): void
    {
        $row = [
            'article_url' => 'https://news.example.com/p',
            'article_title' => '',
            'article_fetched_title' => 'Fetched H1',
            'open_in_new_tab' => true,
            'article_image_mode' => EditorialGalleryExternalArticlePreviewApplier::IMAGE_NONE,
        ];
        $c = EditorialGalleryExternalArticlePresenter::fromRow($row);
        $this->assertNotNull($c);
        $this->assertSame('Fetched H1', $c['title']);
        $this->assertSame('_blank', $c['target']);
        $this->assertSame('noopener noreferrer', $c['rel']);
    }

    public function test_same_tab_has_empty_rel(): void
    {
        $row = [
            'article_url' => 'https://news.example.com/p',
            'article_title' => 'T',
            'article_fetched_title' => '',
            'open_in_new_tab' => false,
            'article_image_mode' => EditorialGalleryExternalArticlePreviewApplier::IMAGE_NONE,
        ];
        $c = EditorialGalleryExternalArticlePresenter::fromRow($row);
        $this->assertNotNull($c);
        $this->assertNull($c['target']);
        $this->assertSame('', $c['rel']);
    }

    public function test_invalid_url_returns_null(): void
    {
        $this->assertNull(EditorialGalleryExternalArticlePresenter::fromRow([
            'article_url' => 'ftp://x',
            'article_title' => 'T',
        ]));
    }

    public function test_description_and_site_fallback_to_fetched(): void
    {
        $row = [
            'article_url' => 'https://news.example.com/p',
            'article_title' => 'T',
            'article_description' => '',
            'article_fetched_description' => 'Fetched body',
            'article_site_name' => '',
            'article_fetched_site_name' => 'Fetched Site',
            'article_domain' => 'news.example.com',
            'open_in_new_tab' => true,
            'article_image_mode' => EditorialGalleryExternalArticlePreviewApplier::IMAGE_NONE,
        ];
        $c = EditorialGalleryExternalArticlePresenter::fromRow($row);
        $this->assertNotNull($c);
        $this->assertSame('Fetched body', $c['description']);
        $this->assertSame('Fetched Site', $c['siteLabel']);
    }

    public function test_site_label_falls_back_to_domain_when_names_empty(): void
    {
        $row = [
            'article_url' => 'https://news.example.com/p',
            'article_title' => 'T',
            'article_site_name' => '',
            'article_fetched_site_name' => '',
            'article_domain' => 'news.example.com',
            'open_in_new_tab' => true,
            'article_image_mode' => EditorialGalleryExternalArticlePreviewApplier::IMAGE_NONE,
        ];
        $c = EditorialGalleryExternalArticlePresenter::fromRow($row);
        $this->assertNotNull($c);
        $this->assertSame('news.example.com', $c['siteLabel']);
    }

    public function test_suggested_image_marks_hotlink(): void
    {
        $row = [
            'article_url' => 'https://news.example.com/p',
            'article_title' => 'T',
            'article_image_mode' => EditorialGalleryExternalArticlePreviewApplier::IMAGE_SUGGESTED,
            'article_suggested_image_url' => 'https://cdn.example.com/og.jpg',
        ];
        $c = EditorialGalleryExternalArticlePresenter::fromRow($row);
        $this->assertNotNull($c);
        $this->assertSame('https://cdn.example.com/og.jpg', $c['imageUrl']);
        $this->assertTrue($c['imageIsExternalHotlink']);
    }
}
