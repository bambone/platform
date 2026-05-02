<?php

declare(strict_types=1);

namespace App\Services\Reviews\Imports\Dto;

final class ManualReviewCsvParseResult
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $warnings
     * @param  list<string>  $errors
     * @param  list<string>  $errorCodes
     */
    public function __construct(
        public readonly array $rows = [],
        public readonly array $warnings = [],
        public readonly array $errors = [],
        public readonly array $errorCodes = [],
    ) {}

    public function isOk(): bool
    {
        return $this->errors === [];
    }

    public function hasErrorCode(string $code): bool
    {
        return in_array($code, $this->errorCodes, true);
    }
}
