<?php

namespace Tests\Unit\Support;

use App\Support\MotorcycleLegacyCoverImporter;
use Tests\TestCase;

class MotorcycleLegacyCoverImporterTest extends TestCase
{
    public function test_resolves_motolevins_prefix_under_public_images(): void
    {
        $relative = 'motolevins/bikes/test-bike.jpg';
        $full = public_path('images/'.$relative);
        @mkdir(dirname($full), 0777, true);
        file_put_contents($full, 'x');

        $this->assertSame($full, MotorcycleLegacyCoverImporter::absolutePathFromLegacyValue($relative));

        @unlink($full);
    }

    public function test_returns_null_when_file_missing(): void
    {
        $this->assertNull(MotorcycleLegacyCoverImporter::absolutePathFromLegacyValue('motolevins/bikes/missing-xyz.jpg'));
    }
}
