<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\ExternalArticleUrlRule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ExternalArticleUrlRuleTest extends TestCase
{
    public function test_accepts_https_url(): void
    {
        $v = Validator::make(['u' => 'https://example.com/article'], ['u' => [new ExternalArticleUrlRule]]);
        $this->assertFalse($v->fails());
    }

    public function test_rejects_empty_when_rule_runs(): void
    {
        $v = Validator::make(['u' => ''], ['u' => ['required', new ExternalArticleUrlRule]]);
        $this->assertTrue($v->fails());
    }

    public function test_rejects_javascript(): void
    {
        $v = Validator::make(['u' => 'javascript:alert(1)'], ['u' => [new ExternalArticleUrlRule]]);
        $this->assertTrue($v->fails());
    }
}
