<?php

namespace App\Services\Seo\Data;

final readonly class TenantSeoLintResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @param  list<string>  $notices
     * @param  list<string>  $checkedPages
     */
    public function __construct(
        public array $errors,
        public array $warnings,
        public array $notices,
        public int $score,
        public array $checkedPages = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'notices' => $this->notices,
            'checked_pages' => $this->checkedPages,
        ];
    }
}
