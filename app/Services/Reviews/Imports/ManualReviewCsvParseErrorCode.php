<?php

declare(strict_types=1);

namespace App\Services\Reviews\Imports;

/**
 * Коды ошибок {@see ManualReviewCsvParser} для UI (не привязываться к тексту сообщения).
 */
final class ManualReviewCsvParseErrorCode
{
    public const UNREADABLE_HEADER = 'unreadable_header';

    public const EMPTY_HEADERS = 'empty_headers';

    public const MISSING_BODY_COLUMN = 'missing_body_column';

    public const TEMP_STREAM_FAILED = 'temp_stream_failed';

    public const UNKNOWN = 'unknown';
}
