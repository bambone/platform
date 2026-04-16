<?php

declare(strict_types=1);

namespace App\Services\LinkPreview;

/**
 * Контракт безопасного link-preview для секции «Внешний материал» (мокается в тестах).
 */
interface ExternalArticlePreviewFetcherInterface
{
    public function fetch(string $rawUrl): ExternalArticlePreviewData;
}
