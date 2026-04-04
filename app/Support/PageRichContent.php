<?php

namespace App\Support;

use App\Support\Storage\TenantStorageDisks;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Рендер контента страниц из Filament RichEditor: в БД может лежать HTML или JSON-документ TipTap.
 * JSON на витрине без преобразования даёт «псевдо-таблицу» из текста — поэтому перед выводом нормализуем в HTML.
 */
final class PageRichContent
{
    private const MAX_JSON_DECODE_DEPTH = 4;

    public static function toHtml(mixed $raw): string
    {
        return self::toHtmlInternal($raw, 0);
    }

    /**
     * Plain-text excerpt for admin previews (no HTML in lists).
     */
    public static function toPlainTextExcerpt(mixed $raw, int $maxLen = 200): string
    {
        $html = self::toHtml($raw);
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        if ($plain === '') {
            return '';
        }

        if ($maxLen <= 0 || strlen($plain) <= $maxLen) {
            return $plain;
        }

        return substr($plain, 0, $maxLen).'…';
    }

    private static function toHtmlInternal(mixed $raw, int $depth): string
    {
        if ($depth > self::MAX_JSON_DECODE_DEPTH) {
            return '';
        }

        if ($raw === null) {
            return '';
        }

        if ($raw instanceof Htmlable) {
            return self::toHtmlInternal($raw->toHtml(), $depth);
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return '';
            }

            if (($trimmed[0] ?? '') === '{' || ($trimmed[0] ?? '') === '[') {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_string($decoded)) {
                        return self::toHtmlInternal($decoded, $depth + 1);
                    }
                    if (is_array($decoded) && ($decoded['type'] ?? null) === 'doc') {
                        return self::tiptapDocumentToHtml($decoded);
                    }
                }
            }

            return $trimmed;
        }

        if (is_array($raw)) {
            if (($raw['type'] ?? null) === 'doc') {
                return self::tiptapDocumentToHtml($raw);
            }

            if (isset($raw['content']) && (is_string($raw['content']) || is_array($raw['content']))) {
                return self::toHtmlInternal($raw['content'], $depth + 1);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private static function tiptapDocumentToHtml(array $document): string
    {
        return RichContentRenderer::make($document)
            ->fileAttachmentsDisk(TenantStorageDisks::publicDiskName())
            ->fileAttachmentsVisibility('public')
            ->toUnsafeHtml();
    }
}
