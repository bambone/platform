<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Tenant\Widgets\BlackDuckDbFirstHealthWidget;
use App\Models\Tenant;
use App\Services\CurrentTenantManager;
use App\Tenant\BlackDuck\BlackDuckTenantRuntimeHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BlackDuckDbFirstHealthWidgetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function widget_shows_on_black_duck_and_hides_on_other_themes(): void
    {
        $bd = Tenant::query()->create([
            'name' => 'BD',
            'slug' => 'bd-h-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        app(CurrentTenantManager::class)->setTenant($bd);
        $this->assertTrue(BlackDuckDbFirstHealthWidget::canView());

        $ex = Tenant::query()->create([
            'name' => 'Expert',
            'slug' => 'ex-h-'.substr(uniqid(), -6),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        app(CurrentTenantManager::class)->setTenant($ex);
        $this->assertFalse(BlackDuckDbFirstHealthWidget::canView());
    }

    #[Test]
    public function widget_flags_match_runtime_health_delegates(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD2',
            'slug' => 'bd-h2-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        app(CurrentTenantManager::class)->setTenant($tenant);

        $c = Livewire::test(BlackDuckDbFirstHealthWidget::class);
        $flags = $c->get('flags');
        $this->assertIsArray($flags);
        $this->assertSame(BlackDuckTenantRuntimeHealth::isMediaRuntimeEmptyInDatabase($tid), $flags['media_empty_db']);
        $this->assertSame(BlackDuckTenantRuntimeHealth::isServiceCatalogDegradedForInquiryForm($tid), $flags['service_catalog_degraded']);
    }
}
