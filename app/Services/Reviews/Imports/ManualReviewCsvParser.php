<?php

declare(strict_types=1);

namespace App\Services\Reviews\Imports;

use App\Services\Reviews\Imports\Dto\ManualReviewCsvParseResult;
use Illuminate\Support\Str;

final class ManualReviewCsvParser
{
    /**
     * Ручной CSV из textarea: переносы внутри кавычек, UTF-8 BOM, разделитель , или ;
     *
     * @return ManualReviewCsvParseResult rows keyed по slug заголовков; пустые body строки отброшены
     */
    public function parse(string $raw): ManualReviewCsvParseResult
    {
        $bomStripped = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $normalized = trim((string) $bomStripped);
        if ($normalized === '') {
            return new ManualReviewCsvParseResult;
        }

        $firstLine = strtok($normalized, "\r\n");
        $firstLine = $firstLine !== false ? $firstLine : '';
        $delimiter = substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return new ManualReviewCsvParseResult(
                errors: ['Не удалось открыть временный поток для разбора CSV.'],
                errorCodes: [ManualReviewCsvParseErrorCode::TEMP_STREAM_FAILED],
            );
        }

        try {
            fwrite($stream, $normalized);
            rewind($stream);

            $headers = fgetcsv($stream, 0, $delimiter);
            if (! is_array($headers)) {
                return new ManualReviewCsvParseResult(
                    errors: ['Не удалось прочитать строку заголовков CSV.'],
                    errorCodes: [ManualReviewCsvParseErrorCode::UNREADABLE_HEADER],
                );
            }

            $headers = array_map(fn ($h) => Str::slug(trim((string) $h), '_'), $headers);
            $headerList = array_values(array_filter($headers, fn ($h): bool => $h !== ''));

            if ($headerList === []) {
                return new ManualReviewCsvParseResult(
                    errors: ['В CSV не найдены имена колонок в первой строке.'],
                    errorCodes: [ManualReviewCsvParseErrorCode::EMPTY_HEADERS],
                );
            }

            if (! in_array('body', $headerList, true)) {
                return new ManualReviewCsvParseResult(
                    errors: ['Не найдена колонка body. Укажите заголовок body (или body в первой строке с корректным разделителем).'],
                    errorCodes: [ManualReviewCsvParseErrorCode::MISSING_BODY_COLUMN],
                );
            }

            $out = [];
            while (($cells = fgetcsv($stream, 0, $delimiter)) !== false) {
                $isBlankRow = $cells === [] || $cells === [null]
                    || (count($cells) === 1 && trim((string) ($cells[0] ?? '')) === '');
                if ($isBlankRow) {
                    continue;
                }

                $row = [];
                foreach ($headers as $i => $key) {
                    if ($key === '') {
                        continue;
                    }
                    $row[$key] = $cells[$i] ?? null;
                }

                if (trim((string) ($row['body'] ?? '')) !== '') {
                    $out[] = $row;
                }
            }

            return new ManualReviewCsvParseResult(rows: $out);
        } finally {
            fclose($stream);
        }
    }
}
