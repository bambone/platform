<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Motorcycle;
use App\Models\Page;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BusinessHealthWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenant = currentTenant();
        $bookingLabel = $tenant !== null
            ? mb_strtolower(app(TenantTerminologyService::class)->label($tenant, DomainTermKeys::BOOKING))
            : 'бронирования';

        // 1. Critical: No Photos (Available/Active motorcycles)
        $noPhotos = Motorcycle::query()
            ->whereIn('status', ['available', 'maintenance'])
            ->doesntHave('media', 'and', function ($q) {
                $q->where('collection_name', 'cover');
            })
            ->count();

        // 2. Critical: No Price
        $noPrice = Motorcycle::query()
            ->whereIn('status', ['available', 'maintenance'])
            ->where('price_per_day', '<=', 0)
            ->count();

        // 3. Warning: no meta title (SEO lives on seo_meta, not pages.seo_title)
        $noSeo = Page::query()
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

        return [
            Stat::make('Без фотографий', $noPhotos)
                ->description($noPhotos > 0 ? 'Каталог без фото плохо конвертирует' : 'Всё отлично')
                ->descriptionIcon($noPhotos > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($noPhotos > 0 ? 'danger' : 'success')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'onclick' => "window.location.href='/motorcycles?tableFilters[media][value]=without_cover'", // Assume this filter exists or link to catalog
                ]),

            Stat::make('Без цены', $noPrice)
                ->description($noPrice > 0 ? 'Исправьте цены для '.$bookingLabel : 'Всё отлично')
                ->descriptionIcon($noPrice > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($noPrice > 0 ? 'danger' : 'success'),

            Stat::make('Страницы без SEO', $noSeo)
                ->description($noSeo > 0 ? 'Добавьте meta-теги' : 'Всё отлично')
                ->descriptionIcon($noSeo > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($noSeo > 0 ? 'warning' : 'success'),
        ];
    }
}
