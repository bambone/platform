<?php

namespace Tests\Unit\Rules;

use App\Rules\EditorialGalleryAssetUrlRule;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Validator;

final class EditorialGalleryAssetUrlRuleTest extends TestCase
{
    #[DataProvider('imageOk')]
    public function test_image_accepts_storage_paths(string $v): void
    {
        $v = Validator::make(['u' => $v], ['u' => [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_IMAGE)]]);
        $this->assertTrue($v->passes());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function imageOk(): iterable
    {
        yield 'site path' => ['site/brand/gallery-1.jpg'];
        yield 'storage path' => ['storage/tenant/1/public/x.png'];
        yield 'absolute' => ['https://cdn.example.com/a/b/image.webp'];
        yield 'root relative with ext' => ['/images/cover.jpg'];
        yield 'root relative storage style' => ['/storage/tenant/1/public/x.png'];
        yield 'tenant object key' => ['tenants/7/public/site/brand/photo.jpeg'];
        yield 'tenant object key jpg under page-builder' => ['tenants/1/public/site/page-builder/file.jpg'];
    }

    #[DataProvider('imageBadInternalPathsWithoutImageExtension')]
    public function test_image_rejects_internal_paths_without_image_extension(string $v): void
    {
        $v = Validator::make(['u' => $v], ['u' => [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_IMAGE)]]);
        $this->assertFalse($v->passes());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function imageBadInternalPathsWithoutImageExtension(): iterable
    {
        yield 'site no extension' => ['site/page-builder/noext'];
        yield 'storage no extension' => ['storage/tenant/1/public/file'];
        yield 'tenant key no extension' => ['tenants/1/public/site/page-builder/file'];
        yield 'tenant key wrong extension' => ['tenants/1/public/site/page-builder/file.txt'];
    }

    #[DataProvider('imageBadRootRelativeAndProtocolRelative')]
    public function test_image_rejects_non_asset_paths(string $v): void
    {
        $v = Validator::make(['u' => $v], ['u' => [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_IMAGE)]]);
        $this->assertFalse($v->passes());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function imageBadRootRelativeAndProtocolRelative(): iterable
    {
        yield 'root page path' => ['/contacts'];
        yield 'root article path' => ['/news/article'];
        yield 'protocol relative' => ['//evil.com/x'];
        yield 'protocol relative even with image ext' => ['//cdn.example.com/x.jpg'];
    }

    public function test_poster_rejects_root_path_without_image_extension(): void
    {
        $v = Validator::make(['u' => '/about'], ['u' => [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_POSTER)]]);
        $this->assertFalse($v->passes());
    }

    public function test_poster_accepts_root_relative_png(): void
    {
        $v = Validator::make(['u' => '/media/poster.png'], ['u' => [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_POSTER)]]);
        $this->assertTrue($v->passes());
    }

    public function test_image_rejects_article_url(): void
    {
        $v = Validator::make(['u' => 'https://example.com/news/article-slug'], ['u' => [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_IMAGE)]]);
        $this->assertFalse($v->passes());
    }

    public function test_video_rejects_vk_watch_page(): void
    {
        $v = Validator::make(['u' => 'https://vk.com/video-1_2'], ['u' => [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_VIDEO_FILE)]]);
        $this->assertFalse($v->passes());
    }

    public function test_video_accepts_mp4(): void
    {
        $v = Validator::make(['u' => 'site/brand/intro.mp4'], ['u' => [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_VIDEO_FILE)]]);
        $this->assertTrue($v->passes());
    }

    public function test_video_accepts_webm(): void
    {
        $v = Validator::make(['u' => 'site/brand/clip.webm'], ['u' => [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_VIDEO_FILE)]]);
        $this->assertTrue($v->passes());
    }

    public function test_video_rejects_ogv(): void
    {
        $v = Validator::make(['u' => 'site/brand/legacy.ogv'], ['u' => [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_VIDEO_FILE)]]);
        $this->assertFalse($v->passes());
    }

    public function test_video_rejects_mov(): void
    {
        $v = Validator::make(['u' => 'site/brand/clip.mov'], ['u' => [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_VIDEO_FILE)]]);
        $this->assertFalse($v->passes());
    }
}
