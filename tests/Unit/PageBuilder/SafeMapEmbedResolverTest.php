<?php

declare(strict_types=1);

namespace Tests\Unit\PageBuilder;

use App\PageBuilder\Contacts\MapProvider;
use App\PageBuilder\Contacts\SafeMapEmbedResolver;
use PHPUnit\Framework\TestCase;

final class SafeMapEmbedResolverTest extends TestCase
{
    public function test_yandex_map_widget_passthrough(): void
    {
        $u = 'https://yandex.ru/map-widget/v1/?ll=61.4%2C55.17&z=16';
        [$src, $can] = SafeMapEmbedResolver::resolveEmbedSrc(MapProvider::Yandex, $u);
        self::assertTrue($can);
        self::assertStringContainsString('map-widget', (string) $src);
    }

    public function test_yandex_maps_ll_z_builds_widget(): void
    {
        $u = 'https://yandex.ru/maps/?ll=30.3%2C59.9&z=12';
        [$src, $can] = SafeMapEmbedResolver::resolveEmbedSrc(MapProvider::Yandex, $u);
        self::assertTrue($can);
        self::assertStringContainsString('yandex.ru/map-widget/v1/', (string) $src);
    }

    public function test_yandex_text_only_no_embed(): void
    {
        $u = 'https://yandex.ru/maps/?text='.rawurlencode('Москва');
        [$src, $can] = SafeMapEmbedResolver::resolveEmbedSrc(MapProvider::Yandex, $u);
        self::assertFalse($can);
        self::assertNull($src);
    }

    public function test_google_embed_url(): void
    {
        $u = 'https://www.google.com/maps/embed?pb=abc';
        [$src, $can] = SafeMapEmbedResolver::resolveEmbedSrc(MapProvider::Google, $u);
        self::assertTrue($can);
        self::assertNotNull($src);
    }
}
