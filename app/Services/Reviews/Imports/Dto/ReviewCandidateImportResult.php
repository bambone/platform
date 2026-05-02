<?php

declare(strict_types=1);

namespace App\Services\Reviews\Imports\Dto;

/**
 * Итог массового импорта кандидатов в отзывы.
 */
final class ReviewCandidateImportResult
{
    /**
     * @param  list<int>  $importedReviewIds  id созданных {@see Review} (после сохранённых транзакций)
     * @param  list<string>  $errors  текст ошибок по строкам (без секретов)
     */
    public function __construct(
        public readonly array $importedReviewIds,
        public readonly int $skippedAlreadyImportedCount,
        public readonly array $errors = [],
    ) {}

    public function importedCount(): int
    {
        return count($this->importedReviewIds);
    }

    /**
     * @return list<string>
     */
    public function summaryLines(): array
    {
        $lines = ['Импортировано: '.$this->importedCount()];
        if ($this->skippedAlreadyImportedCount > 0) {
            $lines[] = 'Пропущено уже импортированных: '.$this->skippedAlreadyImportedCount;
        }

        return $lines;
    }

    public function formattedBodyWithErrors(int $maxErrors = 10): string
    {
        $lines = $this->summaryLines();

        if ($this->errors !== []) {
            $lines[] = '';
            $lines[] = 'Ошибки:';
            foreach (array_slice($this->errors, 0, $maxErrors) as $err) {
                $lines[] = $err;
            }
            if (count($this->errors) > $maxErrors) {
                $lines[] = '… ещё '.(count($this->errors) - $maxErrors);
            }
        }

        return implode("\n", $lines);
    }

    public function mergedWith(self $other): self
    {
        return new self(
            importedReviewIds: array_merge($this->importedReviewIds, $other->importedReviewIds),
            skippedAlreadyImportedCount: $this->skippedAlreadyImportedCount + $other->skippedAlreadyImportedCount,
            errors: array_merge($this->errors, $other->errors),
        );
    }
}
