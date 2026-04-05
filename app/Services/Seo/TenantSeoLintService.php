<?php

namespace App\Services\Seo;

use App\Models\Motorcycle;
use App\Models\Tenant;
use App\Services\Seo\Data\TenantSeoLintResult;
use DOMElement;
use DOMXPath;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class TenantSeoLintService
{
    /**
     * Top-level and common @types allowed in JSON-LD graph nodes.
     *
     * Nested helper nodes used inside ItemList / FAQPage (not always separate graph roots):
     * ListItem, Question, Answer.
     */
    private const ALLOWED_LD_TYPES = [
        'Organization', 'WebSite', 'WebPage', 'ItemList', 'Product', 'Offer', 'FAQPage',
        'ListItem', 'Question', 'Answer',
    ];

    public function __construct(
        private TenantCanonicalPublicBaseUrl $canonicalBase,
    ) {}

    public function lint(Tenant $tenant, bool $useHttp = false): TenantSeoLintResult
    {
        $errors = [];
        $warnings = [];
        $notices = [];
        $checked = [];

        $base = rtrim($this->canonicalBase->resolve($tenant), '/');
        $host = (string) (parse_url($base, PHP_URL_HOST) ?: '');

        $routeNames = config('seo_autopilot.lint_route_names', []);
        $routeNames = is_array($routeNames) ? $routeNames : [];

        foreach ($routeNames as $routeName) {
            if (! is_string($routeName) || $routeName === '') {
                continue;
            }
            $path = TenantSeoSafeRoutePath::forLint($routeName);
            if ($path === null) {
                continue;
            }
            $url = $base.$path;
            $checked[] = $url;
            $this->analyzePage($url, $host, $useHttp, $errors, $warnings, $notices);
        }

        if ((bool) config('seo_autopilot.lint_include_sample_motorcycle', true)) {
            $m = Motorcycle::query()
                ->where('tenant_id', $tenant->id)
                ->where('show_in_catalog', true)
                ->where('status', 'available')
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->orderBy('sort_order')
                ->first();
            if ($m !== null) {
                $slug = rawurlencode(trim((string) $m->slug));
                $url = $base.'/moto/'.$slug;
                $checked[] = $url;
                $this->analyzePage($url, $host, $useHttp, $errors, $warnings, $notices);
            }
        }

        $this->lintLlmsTxt($base, $useHttp, $errors, $warnings, $notices, $checked);

        $score = $this->computeScore($errors, $warnings, $notices);

        return new TenantSeoLintResult(
            errors: $errors,
            warnings: $warnings,
            notices: $notices,
            score: $score,
            checkedPages: $checked,
        );
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @param  list<string>  $notices
     */
    private function analyzePage(
        string $url,
        string $host,
        bool $useHttp,
        array &$errors,
        array &$warnings,
        array &$notices,
    ): void {
        $html = $this->fetchHtml($url, $host, $useHttp);
        if ($html === null) {
            $errors[] = 'Failed to fetch: '.$url;

            return;
        }

        $xp = $this->loadHtmlXPath($html);
        if ($xp === null) {
            $errors[] = 'Could not parse HTML: '.$url;

            return;
        }

        $title = $this->firstTitleText($xp);
        if ($title === null || trim(html_entity_decode(strip_tags($title))) === '') {
            $errors[] = 'Empty <title>: '.$url;
        }

        $desc = $this->metaContentByName($xp, 'description');
        if ($desc === null) {
            $warnings[] = 'Missing meta description: '.$url;
        } elseif (trim(html_entity_decode($desc)) === '') {
            $warnings[] = 'Empty meta description: '.$url;
        }

        $canon = null;
        $canonRaw = $this->linkHrefByRel($xp, 'canonical');
        if ($canonRaw === null || trim($canonRaw) === '') {
            $errors[] = 'Missing canonical link: '.$url;
        } else {
            $canon = trim($canonRaw);
            if (! str_starts_with($canon, 'http')) {
                $errors[] = 'Canonical is not absolute: '.$url;
            }
            $canonHost = parse_url($canon, PHP_URL_HOST);
            if (is_string($canonHost) && strtolower($canonHost) !== strtolower($host)) {
                $errors[] = 'Canonical host mismatch for '.$url.' (expected '.$host.', got '.$canonHost.')';
            }
        }

        $ogUrl = $this->metaContentByProperty($xp, 'og:url');
        if ($ogUrl !== null && $canon !== null && trim($ogUrl) !== '') {
            if (trim($ogUrl) !== $canon) {
                $warnings[] = 'og:url differs from canonical: '.$url;
            }
        }

        $ogSn = $this->metaContentByProperty($xp, 'og:site_name');
        if ($ogSn === null || trim(html_entity_decode($ogSn)) === '') {
            $warnings[] = 'Missing or empty og:site_name: '.$url;
        }

        $ogI = $this->metaContentByProperty($xp, 'og:image');
        if ($ogI !== null) {
            $img = trim($ogI);
            if ($img !== '') {
                $this->checkOgImageReachable($img, $url, $host, $useHttp, $errors, $warnings, $notices);
            }
        } else {
            $warnings[] = 'Missing og:image: '.$url;
        }

        $jsonLdBodies = $this->collectJsonLdScriptBodies($xp);
        if ($jsonLdBodies === []) {
            $notices[] = 'No application/ld+json block: '.$url;
        } else {
            foreach ($jsonLdBodies as $i => $raw) {
                $decoded = json_decode($raw, true);
                if (! is_array($decoded)) {
                    $errors[] = 'Invalid JSON-LD JSON (block '.($i + 1).'): '.$url;
                } else {
                    $this->validateJsonLdWhitelist($decoded, $url, $errors, $warnings);
                }
            }
        }
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @param  list<string>  $notices
     */
    private function checkOgImageReachable(
        string $imageUrl,
        string $pageUrl,
        string $host,
        bool $useHttp,
        array &$errors,
        array &$warnings,
        array &$notices,
    ): void {
        $resolved = $this->resolveAbsoluteUrl($imageUrl, $pageUrl);
        if ($resolved === '') {
            $warnings[] = 'og:image could not be resolved to URL: '.$pageUrl;

            return;
        }

        $timeout = (int) config('seo_autopilot.lint_og_image_timeout_seconds', 5);
        $imgHost = parse_url($resolved, PHP_URL_HOST);
        $sameHost = is_string($imgHost) && strtolower($imgHost) === strtolower($host);

        try {
            if ($useHttp) {
                $r = Http::timeout($timeout)->head($resolved);
                if (! $r->successful()) {
                    $errors[] = 'og:image not reachable ('.$r->status().'): '.$pageUrl;
                }

                return;
            }

            if ($sameHost) {
                $status = $this->internalHttpStatus($resolved, 'HEAD');
                if ($status === 405) {
                    $status = $this->internalHttpStatus($resolved, 'GET');
                }
                if ($status === null || $status >= 400) {
                    $warnings[] = 'og:image not reachable (internal): '.$pageUrl;
                }

                return;
            }

            $notices[] = 'og:image uses external host; reachability checked via HTTP in internal lint: '.$pageUrl;
            $r = Http::timeout($timeout)->withoutVerifying()->head($resolved);
            if (! $r->successful()) {
                $warnings[] = 'og:image HEAD not successful (external, internal mode): '.$pageUrl;
            }
        } catch (\Throwable) {
            $warnings[] = 'og:image check failed: '.$pageUrl;
        }
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function validateJsonLdWhitelist(array $decoded, string $pageUrl, array &$errors, array &$warnings): void
    {
        $graph = [];
        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            $graph = $decoded['@graph'];
        } elseif (isset($decoded['@type'])) {
            $graph = [$decoded];
        }

        foreach ($graph as $node) {
            if (! is_array($node)) {
                continue;
            }
            $type = $node['@type'] ?? null;
            if (is_string($type)) {
                if (! in_array($type, self::ALLOWED_LD_TYPES, true)) {
                    $warnings[] = 'JSON-LD @type not in whitelist ('.$type.'): '.$pageUrl;
                }
            } elseif (is_array($type)) {
                foreach ($type as $t) {
                    if (is_string($t) && ! in_array($t, self::ALLOWED_LD_TYPES, true)) {
                        $warnings[] = 'JSON-LD @type not in whitelist ('.$t.'): '.$pageUrl;
                    }
                }
            }
        }
    }

    private function fetchHtml(string $url, string $host, bool $useHttp): ?string
    {
        if ($useHttp) {
            try {
                $r = Http::timeout(15)->get($url);

                return $r->successful() ? $r->body() : null;
            } catch (\Throwable) {
                return null;
            }
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        $server = [
            'HTTP_HOST' => $host,
            'HTTPS' => ($parts['scheme'] ?? '') === 'https' ? 'on' : 'off',
            'REQUEST_URI' => ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : ''),
        ];

        $request = Request::create($url, 'GET', [], [], [], $server);
        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);
        try {
            $status = $response->getStatusCode();
            if ($status >= 400) {
                return null;
            }

            return $response->getContent();
        } finally {
            $kernel->terminate($request, $response);
        }
    }

    private function loadHtmlXPath(string $html): ?DOMXPath
    {
        $prev = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument;
            // Fake XML declaration hints UTF-8 to libxml; rare edge cases may leave a PI node in the tree.
            $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
            if (! $loaded && $dom->documentElement === null) {
                return null;
            }

            return new DOMXPath($dom);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    private function firstTitleText(DOMXPath $xp): ?string
    {
        $nodes = $xp->query('//title');
        if ($nodes === false || $nodes->length < 1) {
            return null;
        }

        return trim((string) $nodes->item(0)?->textContent);
    }

    private function metaContentByName(DOMXPath $xp, string $name): ?string
    {
        $nodes = $xp->query('//meta[@name]');
        if ($nodes === false) {
            return null;
        }
        $want = strtolower($name);
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            if (strtolower($node->getAttribute('name')) === $want) {
                return $node->getAttribute('content');
            }
        }

        return null;
    }

    private function metaContentByProperty(DOMXPath $xp, string $property): ?string
    {
        $nodes = $xp->query('//meta[@property]');
        if ($nodes === false) {
            return null;
        }
        $want = strtolower($property);
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            if (strtolower($node->getAttribute('property')) === $want) {
                return $node->getAttribute('content');
            }
        }

        return null;
    }

    private function linkHrefByRel(DOMXPath $xp, string $rel): ?string
    {
        $nodes = $xp->query('//link[@rel]');
        if ($nodes === false) {
            return null;
        }
        $want = strtolower($rel);
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            if (strtolower($node->getAttribute('rel')) === $want) {
                return $node->getAttribute('href');
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function collectJsonLdScriptBodies(DOMXPath $xp): array
    {
        $nodes = $xp->query('//script[@type]');
        if ($nodes === false) {
            return [];
        }

        $out = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            if (strtolower(trim($node->getAttribute('type'))) !== 'application/ld+json') {
                continue;
            }
            $raw = trim((string) $node->textContent);
            if ($raw !== '') {
                $out[] = $raw;
            }
        }

        return $out;
    }

    private function resolveAbsoluteUrl(string $ref, string $pageUrl): string
    {
        $ref = trim($ref);
        if ($ref === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $ref)) {
            return $ref;
        }
        if (str_starts_with($ref, '//')) {
            $scheme = parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$ref;
        }
        $parts = parse_url($pageUrl);
        if ($parts === false) {
            return '';
        }
        $scheme = $parts['scheme'] ?? 'https';
        $hostPart = $parts['host'] ?? '';
        if ($hostPart === '') {
            return '';
        }
        if (str_starts_with($ref, '/')) {
            return $scheme.'://'.$hostPart.$ref;
        }
        $path = $parts['path'] ?? '/';
        $dir = dirname($path);
        $dir = $dir === '\\' || $dir === '.' ? '' : rtrim(str_replace('\\', '/', $dir), '/');
        $prefix = $dir === '' ? '/' : $dir.'/';

        return $scheme.'://'.$hostPart.$prefix.$ref;
    }

    private function internalHttpStatus(string $absoluteUrl, string $method = 'HEAD'): ?int
    {
        $parts = parse_url($absoluteUrl);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $host = $parts['host'];
        $server = [
            'HTTP_HOST' => $host,
            'HTTPS' => ($parts['scheme'] ?? '') === 'https' ? 'on' : 'off',
            'REQUEST_URI' => ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : ''),
        ];

        $request = Request::create($absoluteUrl, $method, [], [], [], $server);
        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);
        try {
            return $response->getStatusCode();
        } finally {
            $kernel->terminate($request, $response);
        }
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @param  list<string>  $notices
     * @param  list<string>  $checked
     */
    private function lintLlmsTxt(
        string $base,
        bool $useHttp,
        array &$errors,
        array &$warnings,
        array &$notices,
        array &$checked,
    ): void {
        $url = $base.'/llms.txt';
        $checked[] = $url;
        $host = (string) (parse_url($base, PHP_URL_HOST) ?: '');
        $body = $this->fetchHtml($url, $host, $useHttp);
        if ($body === null) {
            $errors[] = 'Failed to fetch llms.txt';

            return;
        }

        if (trim($body) === '') {
            $errors[] = 'Empty llms.txt';

            return;
        }

        foreach ($this->platformHostNeedles() as $needle) {
            if ($needle !== '' && str_contains(strtolower($body), strtolower($needle))) {
                $warnings[] = 'llms.txt may reference platform host: '.$needle;
            }
        }

        if (! str_contains($body, $host) && ! str_contains($body, $base)) {
            $notices[] = 'llms.txt does not mention tenant host explicitly';
        }
    }

    /**
     * @return list<string>
     */
    private function platformHostNeedles(): array
    {
        $out = [];
        $ph = trim((string) config('app.platform_host', ''));
        if ($ph !== '') {
            $out[] = $ph;
        }
        $central = config('tenancy.central_domains', []);
        if (is_array($central)) {
            foreach ($central as $d) {
                if (is_string($d) && trim($d) !== '') {
                    $out[] = trim($d);
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @param  list<string>  $notices
     */
    private function computeScore(array $errors, array $warnings, array $notices): int
    {
        $score = 100;
        $score -= count($errors) * 20;
        $score -= count($warnings) * 5;
        $score -= count($notices) * 1;

        return max(0, min(100, $score));
    }
}
