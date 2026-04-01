<?php

namespace App\Terminology;

/**
 * Russian display strings when DB default_label is empty — keeps UI monolingual.
 * Must stay aligned with {@see Database\Seeders\DomainTerminologySeeder} generic base.
 *
 * @phpstan-type TermKeyMap array<string, string>
 */
final class DomainTermEmergencyLabels
{
    /**
     * Canonical RU labels for every {@see DomainTermKeys} value.
     *
     * @return TermKeyMap
     */
    public static function ruMap(): array
    {
        return [
            DomainTermKeys::LEAD => 'Обращение',
            DomainTermKeys::LEAD_PLURAL => 'Обращения',
            DomainTermKeys::BOOKING => 'Бронирование',
            DomainTermKeys::BOOKING_PLURAL => 'Бронирования',
            DomainTermKeys::APPOINTMENT => 'Запись',
            DomainTermKeys::APPOINTMENT_PLURAL => 'Записи',
            DomainTermKeys::REQUEST => 'Заявка',
            DomainTermKeys::REQUEST_PLURAL => 'Заявки',
            DomainTermKeys::CUSTOMER => 'Клиент',
            DomainTermKeys::CUSTOMER_PLURAL => 'Клиенты',
            DomainTermKeys::SERVICE => 'Услуга',
            DomainTermKeys::SERVICE_PLURAL => 'Услуги',
            DomainTermKeys::RESOURCE => 'Объект каталога',
            DomainTermKeys::RESOURCE_PLURAL => 'Объекты каталога',
            DomainTermKeys::STAFF_MEMBER => 'Сотрудник',
            DomainTermKeys::STAFF_MEMBER_PLURAL => 'Сотрудники',
            DomainTermKeys::LOCATION => 'Локация',
            DomainTermKeys::LOCATION_PLURAL => 'Локации',
            DomainTermKeys::CATEGORY => 'Категория',
            DomainTermKeys::CATEGORY_PLURAL => 'Категории',
            DomainTermKeys::FLEET_UNIT => 'Единица парка',
            DomainTermKeys::FLEET_UNIT_PLURAL => 'Единицы парка',
            DomainTermKeys::NAV_OPERATIONS => 'Операции',
            DomainTermKeys::NAV_CATALOG => 'Каталог',
            DomainTermKeys::NAV_CONTENT => 'Контент',
            DomainTermKeys::NAV_MARKETING => 'Маркетинг',
            DomainTermKeys::NAV_INFRASTRUCTURE => 'Инфраструктура',
            DomainTermKeys::NAV_SETTINGS => 'Настройки',
        ];
    }

    public static function ruOrNull(string $termKey): ?string
    {
        return self::ruMap()[$termKey] ?? null;
    }
}
