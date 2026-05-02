<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Reviews\Imports;

use App\Services\Reviews\Imports\ReviewCandidateImportService;
use Exception;
use PHPUnit\Framework\TestCase;

final class ReviewCandidateImportSanitizeTest extends TestCase
{
    public function test_sanitize_strips_sensitive_query_params_or_whole_signed_url_segment(): void
    {
        $e = new Exception('Failed https://x.example/path?token=abc123sig&signature=zzz other');
        $out = ReviewCandidateImportService::sanitizeExceptionMessage($e);
        $this->assertStringNotContainsString('abc123sig', $out);
        $this->assertStringNotContainsString('signature=zzz', $out);
    }

    public function test_sanitize_replaces_bearer_tokens(): void
    {
        $e = new Exception('Auth failed Bearer eyJhbG.rest');
        $out = ReviewCandidateImportService::sanitizeExceptionMessage($e);
        $this->assertStringContainsString('Bearer ***', $out);
        $this->assertStringNotContainsString('eyJ', $out);
    }
}
