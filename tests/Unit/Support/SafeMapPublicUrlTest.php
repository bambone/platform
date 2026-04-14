<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\PageBuilder\Contacts\MapProvider;
use App\Support\SafeMapPublicUrl;
use PHPUnit\Framework\TestCase;

final class SafeMapPublicUrlTest extends TestCase
{
    public function test_yandex_https_classified(): void
    {
        $u = 'https://yandex.ru/maps/?text='.rawurlencode('Москва');
        $r = SafeMapPublicUrl::normalizeAndClassify($u);
        self::assertNotNull($r);
        self::assertSame(MapProvider::Yandex, $r[1]);
        self::assertStringStartsWith('https://', $r[0]);
    }

    public function test_maps_yandex_host_classified(): void
    {
        $u = 'https://maps.yandex.ru/?text='.rawurlencode('Москва');
        $r = SafeMapPublicUrl::normalizeAndClassify($u);
        self::assertNotNull($r);
        self::assertSame(MapProvider::Yandex, $r[1]);
    }

    public function test_rejects_arbitrary_yandex_subdomain(): void
    {
        self::assertNull(SafeMapPublicUrl::normalizeAndClassify('https://mc.yandex.ru/path'));
    }

    public function test_google_maps_classified(): void
    {
        $u = 'https://www.google.com/maps/search/?api=1&query=Paris';
        $r = SafeMapPublicUrl::normalizeAndClassify($u);
        self::assertNotNull($r);
        self::assertSame(MapProvider::Google, $r[1]);
    }

    public function test_2gis_classified(): void
    {
        $u = 'https://2gis.ru/moscow';
        $r = SafeMapPublicUrl::normalizeAndClassify($u);
        self::assertNotNull($r);
        self::assertSame(MapProvider::TwoGis, $r[1]);
    }

    public function test_rejects_unknown_host(): void
    {
        self::assertNull(SafeMapPublicUrl::normalizeAndClassify('https://evil.example.com/path'));
    }

    public function test_rejects_javascript(): void
    {
        self::assertNull(SafeMapPublicUrl::normalizeAndClassify('javascript:alert(1)'));
    }

    public function test_rejects_http(): void
    {
        self::assertNull(SafeMapPublicUrl::normalizeAndClassify('http://yandex.ru/maps/'));
    }

    public function test_rejects_html_in_field(): void
    {
        self::assertNull(SafeMapPublicUrl::normalizeAndClassify('https://yandex.ru/maps/<script>'));
    }

    public function test_extract_iframe_src(): void
    {
        $html = '<iframe src="https://yandex.ru/map-widget/v1/?ll=1%2C2&z=10"></iframe>';
        self::assertSame(
            'https://yandex.ru/map-widget/v1/?ll=1%2C2&z=10',
            SafeMapPublicUrl::extractFirstIframeSrc($html),
        );
    }

    public function test_validate_matches_provider(): void
    {
        $u = 'https://maps.google.com/?q=test';
        self::assertTrue(SafeMapPublicUrl::validateMatchesProvider($u, MapProvider::Google));
        self::assertFalse(SafeMapPublicUrl::validateMatchesProvider($u, MapProvider::Yandex));
    }
}
