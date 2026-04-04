<?php

namespace App\Http\Controllers;

use App\Models\TenantSetting;
use App\Services\Seo\FallbackSeoGenerator;
use App\Services\Seo\TenantCanonicalPublicBaseUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TenantLlmsTxtController extends Controller
{
    public function __invoke(Request $request, TenantCanonicalPublicBaseUrl $canonical, FallbackSeoGenerator $branding): Response
    {
        $tenant = tenant();
        abort_if($tenant === null, 404);

        $base = rtrim($canonical->resolve($tenant), '/');
        $siteName = $branding->siteName($tenant);

        $lines = [
            '# '.$siteName,
            '',
        ];

        $intro = trim((string) TenantSetting::getForTenant($tenant->id, 'seo.llms_intro', ''));
        if ($intro !== '') {
            foreach (preg_split("/\r\n|\n|\r/", $intro) as $ln) {
                $lines[] = $ln;
            }
            $lines[] = '';
        } else {
            $lines[] = 'Публичный сайт проката мототехники. Экспериментальный llms.txt; не заменяет sitemap.xml и HTML.';
            $lines[] = '';
        }

        $lines[] = '## Полезные страницы';

        $entriesRaw = TenantSetting::getForTenant($tenant->id, 'seo.llms_entries', '');
        $entries = [];
        if (is_string($entriesRaw) && trim($entriesRaw) !== '') {
            $decoded = json_decode($entriesRaw, true);
            if (is_array($decoded)) {
                $entries = $decoded;
            }
        }

        if ($entries !== []) {
            foreach ($entries as $e) {
                if (! is_array($e)) {
                    continue;
                }
                $path = trim((string) ($e['path'] ?? ''));
                if ($path === '') {
                    continue;
                }
                $summary = trim((string) ($e['summary'] ?? ''));
                $url = $path === '/' ? $base.'/' : $base.$path;
                if ($summary !== '') {
                    $lines[] = '- '.$url.' — '.$summary;
                } else {
                    $lines[] = '- '.$url;
                }
            }
        } else {
            $paths = config('seo_sitemap.llms_paths', []);
            $paths = is_array($paths) ? $paths : [];
            foreach ($paths as $path) {
                $p = trim((string) $path);
                if ($p === '') {
                    continue;
                }
                $lines[] = '- '.($p === '/' ? $base.'/' : $base.$p);
            }
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.$base.'/sitemap.xml';

        $body = implode("\n", $lines);
        $response = new Response($body, 200);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $response;
    }
}
