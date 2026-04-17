<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

/**
 * Честный текст про кнопку «Дальше» в guided-режиме: она сдвигает очередь, а не «сохраняет шаг».
 */
enum SetupGuidedNextHint: string
{
    /** Типичный экран Filament с явной кнопкой «Сохранить». */
    case SaveThenNext = 'save_then_next';
    /** Изменение применяется без отдельного «Сохранить» (редко). */
    case ChangeThenNext = 'change_then_next';
    /** Засчёт в прогрессе после сохранения данных — отдельно от «Дальше» по очереди. */
    case AutoAfterSave = 'auto_after_save';
}
