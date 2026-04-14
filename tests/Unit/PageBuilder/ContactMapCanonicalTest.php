<?php

declare(strict_types=1);

namespace Tests\Unit\PageBuilder;

use App\PageBuilder\Contacts\ContactMapCanonical;
use App\PageBuilder\Contacts\ContactsMapPersistence;
use App\PageBuilder\Contacts\MapProvider;
use App\PageBuilder\Contacts\MapSourceKind;
use PHPUnit\Framework\TestCase;

final class ContactMapCanonicalTest extends TestCase
{
    public function test_priority_new_public_url_wins(): void
    {
        $c = ContactMapCanonical::fromDataJson([
            'map_enabled' => true,
            'map_provider' => 'yandex',
            'map_public_url' => 'https://yandex.ru/maps/?text=test',
            'map_link' => 'https://yandex.ru/maps/?text=legacy',
            'map_embed_mode' => 'button_only',
        ]);
        self::assertTrue($c->hasVisibleMap());
        self::assertStringContainsString('text=test', $c->mapPublicUrl);
    }

    public function test_migrates_map_link_when_new_empty(): void
    {
        $c = ContactMapCanonical::fromDataJson([
            'map_enabled' => true,
            'map_public_url' => '',
            'map_provider' => 'none',
            'map_link' => 'https://yandex.ru/maps/?text=old',
            'map_embed_mode' => 'button_only',
        ]);
        self::assertTrue($c->hasVisibleMap());
        self::assertSame(MapProvider::Yandex, $c->mapProvider);
    }

    public function test_migrates_iframe_src_from_map_embed(): void
    {
        $c = ContactMapCanonical::fromDataJson([
            'map_enabled' => true,
            'map_embed' => '<iframe src="https://yandex.ru/map-widget/v1/?ll=61.4%2C55.17&z=16"></iframe>',
            'map_embed_mode' => 'button_only',
        ]);
        self::assertTrue($c->hasVisibleMap());
        self::assertStringContainsString('yandex.ru/map-widget', $c->mapPublicUrl);
    }

    public function test_map_enabled_false_skips_legacy(): void
    {
        $c = ContactMapCanonical::fromDataJson([
            'map_enabled' => false,
            'map_link' => 'https://yandex.ru/maps/?text=ignored',
            'map_embed_mode' => 'button_only',
        ]);
        self::assertFalse($c->hasVisibleMap());
        self::assertSame(MapProvider::None, $c->mapProvider);
    }

    public function test_finalize_strips_legacy_keys(): void
    {
        $p = new ContactsMapPersistence;
        $out = $p->finalize([
            'channels' => [],
            'map_enabled' => true,
            'map_provider' => 'yandex',
            'map_public_url' => 'https://yandex.ru/maps/?text=x',
            'map_embed_mode' => 'button_only',
            'map_title' => '',
            'map_link' => 'should be removed',
            'map_embed' => '<iframe src="https://evil"></iframe>',
        ]);
        self::assertArrayNotHasKey('map_link', $out);
        self::assertArrayNotHasKey('map_embed', $out);
        self::assertArrayNotHasKey('map_embed_mode', $out);
        self::assertArrayHasKey('map_display_mode', $out);
        self::assertSame('https://yandex.ru/maps/?text=x', $out['map_public_url']);
        self::assertSame(MapSourceKind::Url->value, $out['map_source_kind']);
        self::assertArrayNotHasKey('map_combined_input', $out);
    }

    public function test_finalize_strips_map_combined_input_and_sets_source_kind_iframe(): void
    {
        $p = new ContactsMapPersistence;
        $out = $p->finalize([
            'map_enabled' => true,
            'map_provider' => 'yandex',
            'map_input_mode' => 'auto',
            'map_combined_input' => '<iframe src="https://yandex.ru/map-widget/v1/?ll=61.4%2C55.17&z=16"></iframe>',
            'map_public_url' => '',
            'map_title' => '',
        ]);
        self::assertArrayNotHasKey('map_combined_input', $out);
        self::assertSame(MapSourceKind::Iframe->value, $out['map_source_kind']);
        self::assertStringContainsString('yandex.ru/map-widget', $out['map_public_url']);
    }
}
