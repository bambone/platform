<?php

declare(strict_types=1);

namespace App\Services\LinkPreview;

/**
 * Извлечение og/twitter/title/description/canonical из HTML.
 */
final class HtmlLinkPreviewParser
{
    /**
     * @return array{title: string, description: string, siteName: string, canonicalUrl: string, imageUrl: string, imageWidth: ?int, imageHeight: ?int}
     */
    public function parse(string $html, string $baseUrl): array
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        if (! $loaded) {
            return [
                'title' => '',
                'description' => '',
                'siteName' => '',
                'canonicalUrl' => '',
                'imageUrl' => '',
                'imageWidth' => null,
                'imageHeight' => null,
            ];
        }

        $xpath = new \DOMXPath($dom);

        $ogTitle = $this->firstMetaContent($xpath, '//meta[@property="og:title"]');
        $twTitle = $this->firstMetaContent($xpath, '//meta[@name="twitter:title"]');
        $docTitle = $this->documentTitle($dom);
        $title = $this->firstNonEmpty($ogTitle, $twTitle, $docTitle);

        $ogDesc = $this->firstMetaContent($xpath, '//meta[@property="og:description"]');
        $twDesc = $this->firstMetaContent($xpath, '//meta[@name="twitter:description"]');
        $metaDesc = $this->firstMetaContent($xpath, '//meta[@name="description"]');
        $description = $this->firstNonEmpty($ogDesc, $twDesc, $metaDesc);

        $siteName = $this->firstMetaContent($xpath, '//meta[@property="og:site_name"]');

        $canonicalHref = '';
        foreach ($dom->getElementsByTagName('link') as $link) {
            if (strtolower((string) $link->getAttribute('rel')) === 'canonical') {
                $canonicalHref = trim((string) $link->getAttribute('href'));
                break;
            }
        }

        $ogImage = $this->firstMetaContent($xpath, '//meta[@property="og:image"]');
        $twImage = $this->firstMetaContent($xpath, '//meta[@name="twitter:image"]');
        $imageUrl = $this->firstNonEmpty($ogImage, $twImage);
        $imageUrl = $imageUrl !== '' ? $this->absoluteUrl($baseUrl, $imageUrl) : '';

        $w = $this->firstMetaContent($xpath, '//meta[@property="og:image:width"]');
        $h = $this->firstMetaContent($xpath, '//meta[@property="og:image:height"]');
        $imageWidth = is_numeric($w) ? (int) $w : null;
        $imageHeight = is_numeric($h) ? (int) $h : null;

        $canonicalUrl = $canonicalHref !== '' ? $this->absoluteUrl($baseUrl, $canonicalHref) : '';

        return [
            'title' => $this->normalizeWhitespace($title),
            'description' => $this->normalizeWhitespace($description),
            'siteName' => $this->normalizeWhitespace($siteName),
            'canonicalUrl' => $canonicalUrl,
            'imageUrl' => $imageUrl,
            'imageWidth' => $imageWidth,
            'imageHeight' => $imageHeight,
        ];
    }

    private function firstMetaContent(\DOMXPath $xpath, string $query): string
    {
        $nodes = @$xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        $node = $nodes->item(0);
        if (! $node instanceof \DOMElement) {
            return '';
        }

        return trim(html_entity_decode((string) $node->getAttribute('content'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function documentTitle(\DOMDocument $dom): string
    {
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length === 0) {
            return '';
        }

        return trim(html_entity_decode((string) $titles->item(0)?->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @return string first non-empty
     */
    private function firstNonEmpty(string ...$candidates): string
    {
        foreach ($candidates as $c) {
            if (trim($c) !== '') {
                return trim($c);
            }
        }

        return '';
    }

    private function normalizeWhitespace(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    private function absoluteUrl(string $base, string $relativeOrAbsolute): string
    {
        $relativeOrAbsolute = trim($relativeOrAbsolute);
        if ($relativeOrAbsolute === '') {
            return '';
        }
        if (str_starts_with($relativeOrAbsolute, 'http://') || str_starts_with($relativeOrAbsolute, 'https://')) {
            return $relativeOrAbsolute;
        }

        $baseParts = parse_url($base);
        if ($baseParts === false || ! isset($baseParts['scheme'], $baseParts['host'])) {
            return $relativeOrAbsolute;
        }
        $scheme = $baseParts['scheme'];
        $host = $baseParts['host'];
        $port = isset($baseParts['port']) ? ':'.$baseParts['port'] : '';
        $origin = $scheme.'://'.$host.$port;

        if (str_starts_with($relativeOrAbsolute, '//')) {
            return $scheme.':'.$relativeOrAbsolute;
        }

        $pathBase = $baseParts['path'] ?? '/';
        if (str_starts_with($relativeOrAbsolute, '/')) {
            return $origin.$relativeOrAbsolute;
        }

        $dir = preg_replace('#/[^/]*$#', '/', $pathBase) ?? '/';

        return $origin.$dir.$relativeOrAbsolute;
    }
}
