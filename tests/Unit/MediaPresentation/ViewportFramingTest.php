<?php

namespace Tests\Unit\MediaPresentation;

use App\MediaPresentation\ViewportFraming;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewportFraming::class)]
final class ViewportFramingTest extends TestCase
{
    public function test_from_array_height_factor_camel_in_transitional_saves_clamped_value(): void
    {
        $v = ViewportFraming::fromArray([
            'x' => 10,
            'y' => 20,
            'scale' => 1,
            'heightFactor' => 1.12,
        ]);

        $this->assertNotNull($v);
        $this->assertSame(1.1, $v->heightFactor);
    }

    public function test_from_array_prefers_snake_height_factor(): void
    {
        $v = ViewportFraming::fromArray([
            'x' => 10,
            'y' => 20,
            'scale' => 1,
            'height_factor' => 0.5,
            'heightFactor' => 2,
        ]);

        $this->assertSame(0.5, $v->heightFactor);
    }

    public function test_round_trip_to_array_does_not_drift_focal_at_one_point_six_for_scale(): void
    {
        $a = ['x' => 33.3, 'y' => 44.4, 'scale' => 1.15, 'height_factor' => 1.2];
        $v = ViewportFraming::fromArray($a);
        $b = $v->toArray();
        $v2 = ViewportFraming::fromArray($b);
        $this->assertEqualsWithDelta(33.3, $v2->x, 0.01);
        $this->assertEqualsWithDelta(44.4, $v2->y, 0.01);
        $this->assertEqualsWithDelta(1.15, $v2->scale, 0.0001);
        $this->assertEqualsWithDelta(1.2, $v2->heightFactor, 0.0001);
    }
}
