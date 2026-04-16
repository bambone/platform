<?php

declare(strict_types=1);

namespace App\Services\LinkPreview;

use DateTimeImmutable;

final class ExternalArticlePreviewFetcher implements ExternalArticlePreviewFetcherInterface
{
    public function __construct(
        private SafeHtmlPageFetcher $http,
        private HtmlLinkPreviewParser $parser,
    ) {}

    public function fetch(string $rawUrl): ExternalArticlePreviewData
    {
        $v = LinkPreviewHttpUrlValidator::validateForFetch($rawUrl);
        if (! $v['ok']) {
            return ExternalArticlePreviewData::failed('', $v['error'], 'Invalid URL.');
        }

        $fetched = $this->http->fetch($v['url']);
        if (! $fetched['ok']) {
            return ExternalArticlePreviewData::failed(
                $fetched['finalUrl'],
                $fetched['error'],
                $fetched['message'],
            );
        }

        $parsed = $this->parser->parse($fetched['html'], $fetched['finalUrl']);
        $host = parse_url($fetched['finalUrl'], PHP_URL_HOST);
        $domain = is_string($host) ? strtolower($host) : '';

        $canonical = $parsed['canonicalUrl'] !== '' ? $parsed['canonicalUrl'] : $fetched['finalUrl'];

        return new ExternalArticlePreviewData(
            title: $parsed['title'],
            description: $parsed['description'],
            siteName: $parsed['siteName'] !== '' ? $parsed['siteName'] : $domain,
            domain: $domain,
            canonicalUrl: $canonical,
            imageUrl: $parsed['imageUrl'],
            imageWidth: $parsed['imageWidth'],
            imageHeight: $parsed['imageHeight'],
            fetchedAt: new DateTimeImmutable,
            ok: true,
            errorCode: '',
            errorMessage: '',
            finalUrl: $fetched['finalUrl'],
        );
    }
}
