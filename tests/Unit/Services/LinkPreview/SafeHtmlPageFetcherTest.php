<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LinkPreview;

use App\Services\LinkPreview\LinkPreviewHostSafetyChecker;
use App\Services\LinkPreview\SafeHtmlPageFetcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SafeHtmlPageFetcherTest extends TestCase
{
    public function test_redirect_to_loopback_host_fails_without_second_http_request(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://example.com/start') {
                return Http::response('', 302, ['Location' => 'http://127.0.0.1/secret']);
            }

            return Http::response('unexpected', 500);
        });

        $fetcher = new SafeHtmlPageFetcher;
        $r = $fetcher->fetch('https://example.com/start');

        $this->assertFalse($r['ok']);
        $this->assertSame(LinkPreviewHostSafetyChecker::ERROR_BLOCKED_HOST, $r['error']);
    }

    public function test_follows_external_redirect_and_returns_html(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://example.com/a') {
                return Http::response('', 302, ['Location' => 'https://example.com/b']);
            }
            if ($request->url() === 'https://example.com/b') {
                return Http::response(
                    '<!DOCTYPE html><html><head><title>Final</title></head><body>ok</body></html>',
                    200,
                    ['Content-Type' => 'text/html; charset=utf-8'],
                );
            }

            return Http::response('no', 404);
        });

        $fetcher = new SafeHtmlPageFetcher;
        $r = $fetcher->fetch('https://example.com/a');

        $this->assertTrue($r['ok']);
        $this->assertStringContainsString('Final', $r['html']);
        $this->assertSame('https://example.com/b', $r['finalUrl']);
    }
}
