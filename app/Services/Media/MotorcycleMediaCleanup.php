<?php

namespace App\Services\Media;

use App\Models\Motorcycle;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Документирует и централизует ожидания по очистке медиа у {@see Motorcycle}.
 *
 * Фактическое удаление файлов выполняет Spatie MediaLibrary через {@see InteractsWithMedia}:
 * при удалении модели вызывается {@code deleteAllMedia()}, для моделей с {@see SoftDeletes}
 * это происходит только при {@code forceDeleting === true}. При мягком удалении файлы сохраняются для возможного восстановления.
 */
final class MotorcycleMediaCleanup
{
    /**
     * Явная точка расширения, если понадобится дополнительная логика до/после штатного Spatie-cleanup.
     */
    public static function noteDeletionBehavior(): void
    {
        // Инварианты зафиксированы в тестах; тело намеренно пустое.
    }
}
