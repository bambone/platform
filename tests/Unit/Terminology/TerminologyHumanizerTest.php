<?php

namespace Tests\Unit\Terminology;

use App\Terminology\DomainTermKeys;
use App\Terminology\TerminologyHumanizer;
use Tests\TestCase;

class TerminologyHumanizerTest extends TestCase
{
    public function test_known_keys_use_russian_emergency_map(): void
    {
        $this->assertSame('Обращения', TerminologyHumanizer::humanize(DomainTermKeys::LEAD_PLURAL));
        $this->assertSame('Единицы парка', TerminologyHumanizer::humanize(DomainTermKeys::FLEET_UNIT_PLURAL));
        $this->assertSame('Настройки', TerminologyHumanizer::humanize(DomainTermKeys::NAV_SETTINGS));
    }

    public function test_unknown_keys_use_latin_headline(): void
    {
        $this->assertSame('No Such Term', TerminologyHumanizer::humanize('no.such.term'));
    }
}
