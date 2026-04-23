<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

/**
 * Роли ассетов в {@see BlackDuckMediaCatalog} (builder-first, не публичные URL в Blade).
 */
enum BlackDuckMediaRole: string
{
    case HomeServiceCard = 'home_service_card';
    case HomeProofFeature = 'home_proof_feature';
    case WorksFeatured = 'works_featured';
    case WorksGallery = 'works_gallery';
    case ServiceGallery = 'service_gallery';
    case BeforeAfterBefore = 'before_after_before';
    case BeforeAfterAfter = 'before_after_after';
    case FeaturedVideo = 'featured_video';
    case VideoPoster = 'video_poster';
}
