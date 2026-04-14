<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MapEmbedUrl;
use PHPUnit\Framework\TestCase;

final class MapEmbedUrlTest extends TestCase
{
    public function test_yandex_map_widget_is_allowed(): void
    {
        $u = 'https://yandex.ru/map-widget/v1/?ll=61.4%2C55.17&z=16';

        $this->assertSame($u, MapEmbedUrl::iframeSrcForHttpLink($u));
    }

    public function test_yandex_maps_search_page_is_not_iframe_src(): void
    {
        $this->assertNull(MapEmbedUrl::iframeSrcForHttpLink('https://yandex.ru/maps/?text='.rawurlencode('Москва')));
    }

    public function test_google_maps_embed_is_allowed(): void
    {
        $u = 'https://www.google.com/maps/embed?pb=abc';

        $this->assertSame($u, MapEmbedUrl::iframeSrcForHttpLink($u));
    }
}
