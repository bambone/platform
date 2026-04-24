<?php

namespace App\Filament\Tenant\Widgets;

use App\Tenant\BlackDuck\BlackDuckTenantRuntimeHealth;
use Filament\Widgets\Widget;

/**
 * DB-first: пустой импорт медиа/услуг — «тихий» пустой каталог и форма контактов без селектора.
 */
class BlackDuckDbFirstHealthWidget extends Widget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    protected string $view = 'filament.tenant.widgets.black-duck-db-first-health';

    public static function canView(): bool
    {
        $t = currentTenant();

        return $t !== null && (string) ($t->theme_key ?? '') === 'black_duck';
    }

    /**
     * @return array{media_empty_db: bool, service_catalog_degraded: bool}
     */
    public function getFlagsProperty(): array
    {
        $tid = (int) (currentTenant()->id ?? 0);
        if ($tid < 1) {
            return ['media_empty_db' => false, 'service_catalog_degraded' => false];
        }

        return [
            'media_empty_db' => BlackDuckTenantRuntimeHealth::isMediaRuntimeEmptyInDatabase($tid),
            'service_catalog_degraded' => BlackDuckTenantRuntimeHealth::isServiceCatalogDegradedForInquiryForm($tid),
        ];
    }
}
