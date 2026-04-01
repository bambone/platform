<?php

namespace App\Filament\Tenant\Widgets;

use App\Filament\Tenant\Resources\MotorcycleResource;
use App\Filament\Tenant\Resources\PageResource;
use App\Models\Motorcycle;
use App\Models\Page;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CatalogHealthStatsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Здоровье каталога';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenant = currentTenant();
        $bookingLabel = $tenant !== null
            ? mb_strtolower(app(TenantTerminologyService::class)->label($tenant, DomainTermKeys::BOOKING))
            : 'бронирования';

        $noPhotos = Motorcycle::query()
            ->whereIn('status', ['available', 'maintenance'])
            ->doesntHave('media', 'and', function ($q) {
                $q->where('collection_name', 'cover');
            })
            ->count();

        $noPrice = Motorcycle::query()
            ->whereIn('status', ['available', 'maintenance'])
            ->where('price_per_day', '<=', 0)
            ->count();

        $pagesWithoutSeo = Page::whereDoesntHave('seoMeta')->where('status', 'published')->count();
        $motorcyclesWithoutSeo = Motorcycle::where('show_in_catalog', true)
            ->whereDoesntHave('seoMeta')
            ->count();
        $missingSeo = $pagesWithoutSeo + $motorcyclesWithoutSeo;

        $pagesBadMeta = Page::query()
            ->where(function ($query): void {
                $query
                    ->whereDoesntHave('seoMeta')
                    ->orWhereHas('seoMeta', function ($q): void {
                        $q->where(function ($inner): void {
                            $inner
                                ->whereNull('meta_title')
                                ->orWhere('meta_title', '');
                        });
                    });
            })
            ->count();

        $catalogUrl = MotorcycleResource::getUrl('index');
        $pagesUrl = PageResource::getUrl('index');

        return [
            Stat::make('Без фотографий', $noPhotos)
                ->icon('heroicon-o-photo')
                ->description($noPhotos > 0 ? 'Каталог без фото плохо конвертирует' : 'Всё отлично')
                ->descriptionIcon($noPhotos > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($noPhotos > 0 ? 'danger' : 'success')
                ->url($catalogUrl)
                ->extraAttributes([
                    'class' => 'fi-tenant-dash-stat'.($noPhotos > 0 ? ' fi-tenant-dash-stat--risk' : ' fi-tenant-dash-stat--ok'),
                ]),

            Stat::make('Без цены', $noPrice)
                ->icon('heroicon-o-banknotes')
                ->description($noPrice > 0 ? 'Исправьте цены для '.$bookingLabel : 'Всё отлично')
                ->descriptionIcon($noPrice > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($noPrice > 0 ? 'danger' : 'success')
                ->url($catalogUrl)
                ->extraAttributes([
                    'class' => 'fi-tenant-dash-stat'.($noPrice > 0 ? ' fi-tenant-dash-stat--risk' : ' fi-tenant-dash-stat--ok'),
                ]),

            Stat::make('Без SEO', $missingSeo)
                ->icon('heroicon-o-magnifying-glass')
                ->description($missingSeo > 0 ? 'Страницы и модели без мета-тегов' : 'Всё в порядке')
                ->descriptionIcon('heroicon-m-magnifying-glass')
                ->color($missingSeo > 0 ? 'warning' : 'success')
                ->url($pagesUrl)
                ->extraAttributes([
                    'class' => 'fi-tenant-dash-stat'.($missingSeo > 0 ? ' fi-tenant-dash-stat--warn' : ' fi-tenant-dash-stat--ok'),
                ]),

            Stat::make('Страницы без SEO', $pagesBadMeta)
                ->icon('heroicon-o-document-magnifying-glass')
                ->description($pagesBadMeta > 0 ? 'Добавьте meta-теги' : 'Всё отлично')
                ->descriptionIcon($pagesBadMeta > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($pagesBadMeta > 0 ? 'warning' : 'success')
                ->url($pagesUrl)
                ->extraAttributes([
                    'class' => 'fi-tenant-dash-stat'.($pagesBadMeta > 0 ? ' fi-tenant-dash-stat--warn' : ' fi-tenant-dash-stat--ok'),
                ]),
        ];
    }
}
