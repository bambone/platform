<?php

namespace Tests\Unit\MediaPresentation;

use App\MediaPresentation\Casts\PresentationDataCast;
use App\Models\TenantServiceProgram;
use Tests\TestCase;

final class PresentationDataCastTest extends TestCase
{
    public function test_set_accepts_json_string_and_normalizes(): void
    {
        $cast = new PresentationDataCast();
        $model = new TenantServiceProgram;
        $json = '{"version":1,"viewport_focal_map":{"mobile":{"x":40,"y":60}}}';
        $out = $cast->set($model, 'cover_presentation_json', $json, []);
        $this->assertIsString($out);
        $decoded = json_decode($out, true);
        $this->assertIsArray($decoded);
        $this->assertEquals(40.0, (float) $decoded['viewport_focal_map']['mobile']['x']);
    }

    public function test_set_invalid_json_string_returns_null(): void
    {
        $cast = new PresentationDataCast();
        $model = new TenantServiceProgram;
        $this->assertNull($cast->set($model, 'cover_presentation_json', 'not json', []));
    }
}
