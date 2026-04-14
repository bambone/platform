<?php

declare(strict_types=1);

namespace Tests\Unit\PageBuilder;

use App\PageBuilder\Contacts\ContactMapSourceParser;
use App\PageBuilder\Contacts\MapDisplayMode;
use App\PageBuilder\Contacts\MapInputMode;
use App\PageBuilder\Contacts\MapProvider;
use App\PageBuilder\Contacts\MapSourceKind;
use PHPUnit\Framework\TestCase;

final class ContactMapSourceParserTest extends TestCase
{
    public function test_auto_plain_yandex_url(): void
    {
        $r = ContactMapSourceParser::parse(
            MapInputMode::Auto,
            'https://yandex.ru/maps/?ll=30.3%2C59.9&z=12',
            MapProvider::Yandex,
        );
        self::assertTrue($r->ok);
        self::assertSame(MapSourceKind::Url, $r->sourceKind);
        self::assertSame(ContactMapSourceParser::LABEL_DETECTED_URL, $r->detectionLabelRu);
        self::assertStringContainsString('yandex.ru/maps', $r->normalizedPublicUrl);
    }

    public function test_auto_iframe_yandex_priority(): void
    {
        $html = '<div><iframe src="https://yandex.ru/map-widget/v1/?ll=61.4%2C55.17&z=16" width="400"></iframe></div>';
        $r = ContactMapSourceParser::parse(MapInputMode::Auto, $html, MapProvider::Yandex);
        self::assertTrue($r->ok);
        self::assertSame(MapSourceKind::Iframe, $r->sourceKind);
        self::assertSame(ContactMapSourceParser::LABEL_DETECTED_IFRAME, $r->detectionLabelRu);
    }

    /**
     * Реальный HTML «Поделиться» Яндекса: два &lt;a href&gt; + iframe map-widget с длинным ouri.
     */
    public function test_auto_yandex_share_wrapper_html_extracts_map_widget_iframe(): void
    {
        $html = <<<'HTML'
<div style="position:relative;overflow:hidden;"><a href="https://yandex.ru/maps/56/chelyabinsk/?utm_medium=mapframe&utm_source=maps" style="color:#eee;font-size:12px;position:absolute;top:0px;">Челябинск</a><a href="https://yandex.ru/maps/56/chelyabinsk/house/ulitsa_bratyev_kashirinykh_85a/YkgYdQBgSk0CQFtvfX12dn5jZA==/?ll=61.370385%2C55.177201&utm_medium=mapframe&utm_source=maps&z=17" style="color:#eee;font-size:12px;position:absolute;top:14px;">Яндекс Карты</a><iframe src="https://yandex.ru/map-widget/v1/?ll=61.370385%2C55.177201&mode=search&ol=geo&ouri=ymapsbm1%3A%2F%2Fgeo%3Fdata%3DCgg1NjAzMTM3NRJU0KDQvtGB0YHQuNGPLCDQp9C10LvRj9Cx0LjQvdGB0LosINGD0LvQuNGG0LAg0JHRgNCw0YLRjNC10LIg0JrQsNGI0LjRgNC40L3Ri9GFLCA4NdCQIgoNRXt1QhV0tVxC&z=17" width="560" height="400" frameborder="1" allowfullscreen="true" style="position:relative;"></iframe></div>
HTML;
        $r = ContactMapSourceParser::parse(MapInputMode::Auto, $html, MapProvider::Yandex);
        self::assertTrue($r->ok);
        self::assertSame(MapSourceKind::Iframe, $r->sourceKind);
        self::assertStringContainsString('yandex.ru/map-widget/v1/', $r->normalizedPublicUrl);
        self::assertStringContainsString('ll=61.370385', $r->normalizedPublicUrl);
        self::assertStringContainsString('ouri=', $r->normalizedPublicUrl);
    }

    public function test_auto_iframe_falls_back_to_url_in_same_blob(): void
    {
        $html = '<iframe src="https://evil.example/x"></iframe> https://yandex.ru/maps/?ll=30.3%2C59.9&z=12';
        $r = ContactMapSourceParser::parse(MapInputMode::Auto, $html, MapProvider::Yandex);
        self::assertTrue($r->ok);
        self::assertSame(MapSourceKind::Url, $r->sourceKind);
        self::assertSame(ContactMapSourceParser::LABEL_DETECTED_URL, $r->detectionLabelRu);
    }

    public function test_iframe_untrusted_domain(): void
    {
        $html = '<iframe src="https://evil.example/map"></iframe>';
        $r = ContactMapSourceParser::parse(MapInputMode::Iframe, $html, MapProvider::Yandex);
        self::assertFalse($r->ok);
        self::assertStringContainsString('недоверенному домену', $r->errors[0]);
    }

    public function test_iframe_tag_but_no_src(): void
    {
        $r = ContactMapSourceParser::parse(MapInputMode::Iframe, '<iframe></iframe>', MapProvider::Yandex);
        self::assertFalse($r->ok);
        self::assertSame(ContactMapSourceParser::ERR_IFRAME_NO_SRC, $r->errors[0]);
    }

    public function test_url_mode_rejects_iframe_paste(): void
    {
        $html = '<iframe src="https://yandex.ru/map-widget/v1/?ll=61.4%2C55.17&z=16"></iframe>';
        $r = ContactMapSourceParser::parse(MapInputMode::Url, $html, MapProvider::Yandex);
        self::assertFalse($r->ok);
    }

    public function test_maybe_bump_display_mode_when_iframe_paste_and_button_only(): void
    {
        $html = '<iframe src="https://yandex.ru/map-widget/v1/?ll=61.4%2C55.17&z=16"></iframe>';
        $data = [
            'map_provider' => 'yandex',
            'map_input_mode' => 'auto',
            'map_combined_input' => '',
            'map_display_mode' => MapDisplayMode::ButtonOnly->value,
        ];
        self::assertSame(
            MapDisplayMode::EmbedAndButton->value,
            ContactMapSourceParser::maybeBumpDisplayModeForIframePaste($data, $html)
        );
    }

    public function test_maybe_bump_display_mode_returns_null_when_not_button_only(): void
    {
        $html = '<iframe src="https://yandex.ru/map-widget/v1/?ll=61.4%2C55.17&z=16"></iframe>';
        $data = [
            'map_provider' => 'yandex',
            'map_input_mode' => 'auto',
            'map_display_mode' => MapDisplayMode::EmbedOnly->value,
        ];
        self::assertNull(ContactMapSourceParser::maybeBumpDisplayModeForIframePaste($data, $html));
    }

    public function test_parse_from_data_json_prefers_map_combined_input(): void
    {
        $r = ContactMapSourceParser::parseFromDataJson([
            'map_provider' => 'yandex',
            'map_input_mode' => 'auto',
            'map_combined_input' => 'https://yandex.ru/maps/?ll=30.3%2C59.9&z=12',
            'map_public_url' => 'https://yandex.ru/maps/?text=ignored',
        ]);
        self::assertTrue($r->ok);
        self::assertStringContainsString('ll=', $r->normalizedPublicUrl);
        self::assertStringNotContainsString('text=ignored', $r->normalizedPublicUrl);
    }

    public function test_parse_empty_combined_input_does_not_use_stale_map_public_url(): void
    {
        $r = ContactMapSourceParser::parseFromDataJson([
            'map_provider' => 'yandex',
            'map_input_mode' => 'auto',
            'map_combined_input' => '',
            'map_public_url' => 'https://yandex.ru/maps/?ll=30.3%2C59.9&z=12',
        ]);
        self::assertTrue($r->isEmpty);
    }
}
