<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

/**
 * Источник данных секции {@code case_list} на {@code /raboty}: явная метка в {@code data_json}.
 *
 * @see BlackDuckContentRefresher::updateRabotyPage()
 */
final class BlackDuckRabotyCaseListContentSource
{
    /** Filament / команды вроде fill-case-study-cards — не перезаписывать из медиакаталога без {@code --overwrite-editorial-case-list}. */
    public const MANUAL_DB = 'manual_db';

    /** Собрано из {@see BlackDuckMediaCatalog::worksStoryCardItems()} при refresh-content. */
    public const CATALOG = 'catalog';
}
