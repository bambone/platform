<?php

declare(strict_types=1);

namespace Tests\Feature\Geocoding;

use App\Geocoding\Data\PlaceSuggestion;
use App\Geocoding\GeocodePlacesService;
use App\Geocoding\NominatimGeocodingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class GeocodePlacesServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.nominatim.enabled', true);
        Config::set('services.nominatim.base_url', 'https://nominatim.openstreetmap.org');
        Config::set('services.nominatim.contact', 'test@example.com');
        Config::set('services.nominatim.timeout', 5);
        Config::set('services.nominatim.search_cache_ttl', 3600);
        Config::set('services.nominatim.pick_cache_ttl', 3600);
    }

    public function test_search_returns_normalized_suggestions_from_http_fake(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                $this->nominatimCity('Челябинск', 'Челябинская область', 'Россия'),
                $this->nominatimCity('Chelyabinsk', 'Chelyabinsk Oblast', 'Russia'),
            ], 200),
        ]);

        $svc = $this->makeService();
        $list = $svc->search('Челябинск');

        $this->assertCount(2, $list);
        $this->assertContainsOnlyInstancesOf(PlaceSuggestion::class, $list);
        $this->assertStringContainsString('Челябинск', $list[0]->city);
        Http::assertSentCount(1);
    }

    public function test_short_query_returns_empty_without_http(): void
    {
        Http::fake();

        $svc = $this->makeService();
        $this->assertSame([], $svc->search('ч'));

        Http::assertNothingSent();
    }

    public function test_provider_http_error_returns_empty_and_logs(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response('', 500),
        ]);

        Log::spy();

        $svc = $this->makeService();
        $this->assertSame([], $svc->search('Chelyabinsk'));

        Log::shouldHaveReceived('warning');
    }

    public function test_search_uses_cache_second_call_skips_http(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                $this->nominatimCity('Chelyabinsk', 'Oblast', 'Russia'),
            ], 200),
        ]);

        $svc = $this->makeService();

        $first = $svc->search('Chelyabinsk');
        $this->assertCount(1, $first);
        Http::assertSentCount(1);

        $second = $svc->search('CHELYABINSK');
        $this->assertCount(1, $second);

        Http::assertSentCount(1);
    }

    public function test_resolve_pick_returns_dto_after_search(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                $this->nominatimCity('Chelyabinsk', 'O', 'R'),
            ], 200),
        ]);

        $svc = $this->makeService();
        $suggestions = $svc->search('Che');
        $this->assertNotEmpty($suggestions);
        $id = $suggestions[0]->selectionId;

        $resolved = $svc->resolvePick($id);
        $this->assertInstanceOf(PlaceSuggestion::class, $resolved);
        $this->assertSame($suggestions[0]->city, $resolved->city);
    }

    public function test_disabled_nominatim_returns_empty(): void
    {
        Config::set('services.nominatim.enabled', false);
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([$this->nominatimCity('A', 'B', 'C')], 200),
        ]);

        $svc = $this->makeService();
        $this->assertSame([], $svc->search('Anything'));

        Http::assertNothingSent();
    }

    #[DataProvider('filteringProvider')]
    public function test_nominatim_provider_filters_place_types(string $type, bool $expectMapped): void
    {
        $provider = new NominatimGeocodingProvider(
            baseUrl: 'https://nominatim.openstreetmap.org',
            contactIdentifier: 'test@example.com',
            timeoutSeconds: 5,
        );

        $reflection = new \ReflectionMethod($provider, 'mapItem');
        $reflection->setAccessible(true);

        $item = [
            'type' => $type,
            'display_name' => 'X, Y, Z',
            'address' => [
                'city' => 'X',
                'state' => 'Y',
                'country' => 'Z',
            ],
        ];

        /** @var array<string, mixed>|null $mapped */
        $mapped = $reflection->invoke($provider, $item);
        if ($expectMapped) {
            $this->assertNotNull($mapped);
            $this->assertSame('X', $mapped['city']);
        } else {
            $this->assertNull($mapped);
        }
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function filteringProvider(): array
    {
        return [
            'city accepted' => ['city', true],
            'country rejected' => ['country', false],
        ];
    }

    private function makeService(): GeocodePlacesService
    {
        Cache::flush();

        return new GeocodePlacesService(new NominatimGeocodingProvider(
            baseUrl: 'https://nominatim.openstreetmap.org',
            contactIdentifier: 'test@example.com',
            timeoutSeconds: 5,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function nominatimCity(string $city, string $state, string $country): array
    {
        return [
            'place_id' => random_int(100000, 999999),
            'class' => 'place',
            'type' => 'city',
            'display_name' => implode(', ', array_filter([$city, $state, $country])),
            'address' => [
                'city' => $city,
                'state' => $state,
                'country' => $country,
            ],
        ];
    }
}
