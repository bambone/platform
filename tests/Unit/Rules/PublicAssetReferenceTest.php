<?php

namespace Tests\Unit\Rules;

use App\Rules\PublicAssetReference;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PublicAssetReferenceTest extends TestCase
{
    public function test_accepts_empty_url_and_valid_key(): void
    {
        $v = Validator::make(
            ['x' => ''],
            ['x' => [new PublicAssetReference]],
        );
        $this->assertFalse($v->fails());

        $v = Validator::make(
            ['x' => 'https://example.com/a.jpg'],
            ['x' => [new PublicAssetReference]],
        );
        $this->assertFalse($v->fails());

        $v = Validator::make(
            ['x' => 'tenants/1/public/site/a.jpg'],
            ['x' => [new PublicAssetReference]],
        );
        $this->assertFalse($v->fails());

        $v = Validator::make(
            ['x' => 'site/brand/gallery-2.jpg'],
            ['x' => [new PublicAssetReference]],
        );
        $this->assertFalse($v->fails());

        $v = Validator::make(
            ['x' => 'storage/tenants/1/public/site/a.jpg'],
            ['x' => [new PublicAssetReference]],
        );
        $this->assertFalse($v->fails());
    }

    public function test_rejects_invalid(): void
    {
        $v = Validator::make(
            ['x' => '/etc/passwd'],
            ['x' => [new PublicAssetReference]],
        );
        $this->assertTrue($v->fails());

        $v = Validator::make(
            ['x' => 'site/../../../etc/passwd'],
            ['x' => [new PublicAssetReference]],
        );
        $this->assertTrue($v->fails());
    }
}
