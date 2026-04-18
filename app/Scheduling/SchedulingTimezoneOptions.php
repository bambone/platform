<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateTime;
use DateTimeZone;

/**
 * Подписи часовых поясов для поиска по названию и по смещению (в т.ч. ввод «2» → +02, -02, +02:30).
 */
final class SchedulingTimezoneOptions
{
    /** Продуктовый дефолт для новых форм (явная норма для пустого выбора). */
    public const DEFAULT_IDENTIFIER = 'Europe/Moscow';

    private static ?array $cache = null;

    public static function defaultForNewForm(): string
    {
        return self::DEFAULT_IDENTIFIER;
    }

    /**
     * Возвращает канонический IANA-идентификатор из {@see self::all()} или null, если строка не распознана.
     * Пустая строка → null.
     */
    public static function tryResolveToKnownIdentifier(?string $candidate): ?string
    {
        $all = self::all();
        $trim = trim((string) $candidate);
        if ($trim === '') {
            return null;
        }
        if (isset($all[$trim])) {
            return $trim;
        }
        foreach ($all as $id => $_) {
            if (strcasecmp($id, $trim) === 0) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Приводит строку к известному идентификатору из {@see self::all()} (регистр игнорируется).
     * Пустое значение → {@see defaultForNewForm()}.
     * Непустое нераспознанное значение возвращается как есть (без тихой подмены на Москву).
     */
    public static function normalizeToKnown(?string $candidate): string
    {
        $resolved = self::tryResolveToKnownIdentifier($candidate);
        if ($resolved !== null) {
            return $resolved;
        }

        $trim = trim((string) $candidate);
        if ($trim === '') {
            return self::defaultForNewForm();
        }

        return $trim;
    }

    /**
     * @return array<string, string> идентификатор IANA => подпись
     */
    public static function all(): array
    {
        return self::$cache ??= self::build();
    }

    /**
     * @return array<string, string>
     */
    private static function build(): array
    {
        $out = [];
        foreach (DateTimeZone::listIdentifiers() as $id) {
            try {
                $tz = new DateTimeZone($id);
                $dt = new DateTime('now', $tz);
                $offset = $dt->format('P');
            } catch (\Throwable) {
                continue;
            }
            $out[$id] = "{$id} · UTC{$offset}";
        }
        ksort($out);

        return $out;
    }
}
