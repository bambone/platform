<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

/**
 * Роли ассетов в {@see BlackDuckMediaCatalog} (builder-first, runtime — только локальные logical_path).
 */
enum BlackDuckMediaRole: string
{
    case HomeServiceCard = 'home_service_card';
    case HomeProofBefore = 'home_proof_before';
    case HomeProofAfter = 'home_proof_after';
    case WorksFeaturedVideo = 'works_featured_video';
    case WorksFeaturedPoster = 'works_featured_poster';
    case WorksGallery = 'works_gallery';
    case WorksBeforeAfterBefore = 'works_before_after_before';
    case WorksBeforeAfterAfter = 'works_before_after_after';
    case WorksCaseCard = 'works_case_card';
    case ServiceGallery = 'service_gallery';
    /** Опционально: видео на посадочной услуги (например PPF), только с постером. */
    case ServiceFeaturedVideo = 'service_featured_video';
}
