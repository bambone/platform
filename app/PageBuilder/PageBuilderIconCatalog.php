<?php

namespace App\PageBuilder;

use Closure;
use Illuminate\Support\Str;

/**
 * Единый whitelist ключей иконок для секций page builder и совпадающий вывод на витрине.
 *
 * @phpstan-type IconDef array{key: string, label: string, heroicon: string, aliases: list<string>, groups: list<string>}
 */
final class PageBuilderIconCatalog
{
    /** @var list<IconDef>|null */
    private static ?array $definitions = null;

    /**
     * @return list<IconDef>
     */
    public static function all(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        self::$definitions = [
            self::def('check', 'Галочка', 'heroicon-o-check-circle', ['галочка', 'ok', 'успех'], ['info_cards', 'features']),
            self::def('shield', 'Щит', 'heroicon-o-shield-check', ['щит', 'безопасность'], ['info_cards', 'features']),
            self::def('shield-check', 'Щит (ключ shield-check)', 'heroicon-o-shield-check', ['щит', 'shield'], ['features']),
            self::def('clock', 'Часы', 'heroicon-o-clock', ['время', 'часы'], ['info_cards', 'features']),
            self::def('phone', 'Телефон', 'heroicon-o-phone', ['звонок', 'телефон'], ['info_cards', 'features']),
            self::def('map', 'Карта', 'heroicon-o-map', ['карта', 'гео'], ['info_cards', 'features']),
            self::def('star', 'Звезда', 'heroicon-o-star', ['звезда', 'рейтинг'], ['info_cards', 'features']),
            self::def('info', 'Инфо', 'heroicon-o-information-circle', ['информация', 'инфо'], ['info_cards', 'features']),
            self::def('coast', 'Побережье / серпантины', 'heroicon-o-sun', ['море', 'берег', 'побережье', 'coast'], ['features']),
            self::def('city', 'Город', 'heroicon-o-building-office-2', ['город', 'urban'], ['features']),
            self::def('highway', 'Трасса', 'heroicon-o-arrow-trending-up', ['трасса', 'шоссе', 'скорость'], ['features']),
            self::def('mountain', 'Горы', 'heroicon-o-map-pin', ['горы', 'маршрут'], ['features']),
            self::def('wrench', 'Сервис', 'heroicon-o-wrench-screwdriver', ['сервис', 'ремонт', 'настройка'], ['features']),
            self::def('banknotes', 'Оплата', 'heroicon-o-banknotes', ['оплата', 'цена', 'деньги'], ['features']),
            self::def('document', 'Документы', 'heroicon-o-document-text', ['документ', 'договор'], ['features']),
            self::def('calendar', 'Бронь', 'heroicon-o-calendar-days', ['календарь', 'дата', 'бронь'], ['features']),
            self::def('users', 'Команда', 'heroicon-o-user-group', ['команда', 'люди'], ['features']),
            self::def('truck', 'Доставка / выдача', 'heroicon-o-truck', ['выдача', 'логистика'], ['features']),
            self::def('sparkles', 'Премиум', 'heroicon-o-sparkles', ['премиум', 'качество'], ['features']),
            self::def('bolt', 'Быстро', 'heroicon-o-bolt', ['быстро', 'мгновенно'], ['features']),
            self::def('heart', 'Забота', 'heroicon-o-heart', ['забота', 'лайк'], ['features']),
        ];

        return self::$definitions;
    }

    /**
     * @return list<IconDef>
     */
    public static function forGroup(string $group): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $d): bool => in_array($group, $d['groups'], true),
        ));
    }

    public static function allowedKeysForGroup(string $group): array
    {
        return array_map(
            static fn (array $d): string => $d['key'],
            self::forGroup($group),
        );
    }

    public static function isAllowedKey(string $key, string $group): bool
    {
        $key = strtolower(trim($key));

        return in_array($key, self::allowedKeysForGroup($group), true);
    }

    /**
     * Единая проверка для {@see PageBuilderIconPicker} и тестов (strict whitelist vs legacy slug).
     */
    public static function validateIconValue(mixed $value, string $group, bool $allowLegacyFallback, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $s = Str::lower(trim((string) $value));
        if ($allowLegacyFallback) {
            if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $s)) {
                $fail(__('Недопустимый ключ иконки.'));
            }

            return;
        }
        if (! self::isAllowedKey($s, $group)) {
            $fail(__('Выберите иконку из списка.'));
        }
    }

    /**
     * @return IconDef|null
     */
    public static function find(string $key): ?array
    {
        $key = strtolower(trim($key));
        foreach (self::all() as $d) {
            if ($d['key'] === $key) {
                return $d;
            }
        }

        return null;
    }

    public static function heroiconForKey(string $key): ?string
    {
        $d = self::find($key);

        return $d !== null ? $d['heroicon'] : null;
    }

    /**
     * @return array<string, string> key => label for Filament select-style maps
     */
    public static function optionsForGroup(string $group): array
    {
        $out = [];
        foreach (self::forGroup($group) as $d) {
            $out[$d['key']] = $d['label'];
        }

        return $out;
    }

    /**
     * @param  list<IconDef>  $icons
     * @return list<array{key: string, label: string, heroicon: string, search: string}>
     */
    public static function forJsonFrontend(array $icons): array
    {
        $out = [];
        foreach ($icons as $d) {
            $search = strtolower($d['key'].' '.$d['label'].' '.implode(' ', $d['aliases']));
            $out[] = [
                'key' => $d['key'],
                'label' => $d['label'],
                'heroicon' => $d['heroicon'],
                'search' => $search,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_map(static fn (array $d): string => $d['key'], self::all());
    }

    /**
     * @param  list<string>  $aliases
     * @param  list<string>  $groups
     * @return IconDef
     */
    private static function def(string $key, string $label, string $heroicon, array $aliases, array $groups): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'heroicon' => $heroicon,
            'aliases' => $aliases,
            'groups' => $groups,
        ];
    }
}
