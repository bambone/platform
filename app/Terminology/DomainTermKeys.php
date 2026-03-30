<?php

namespace App\Terminology;

/**
 * Canonical term_key values (code is source of truth; {@see Database\Seeders\DomainTerminologySeeder} syncs DB).
 */
final class DomainTermKeys
{
    public const LEAD = 'lead';

    public const LEAD_PLURAL = 'lead.plural';

    public const BOOKING = 'booking';

    public const BOOKING_PLURAL = 'booking.plural';

    public const APPOINTMENT = 'appointment';

    public const APPOINTMENT_PLURAL = 'appointment.plural';

    public const REQUEST = 'request';

    public const REQUEST_PLURAL = 'request.plural';

    public const CUSTOMER = 'customer';

    public const CUSTOMER_PLURAL = 'customer.plural';

    public const SERVICE = 'service';

    public const SERVICE_PLURAL = 'service.plural';

    public const RESOURCE = 'resource';

    public const RESOURCE_PLURAL = 'resource.plural';

    public const STAFF_MEMBER = 'staff_member';

    public const STAFF_MEMBER_PLURAL = 'staff_member.plural';

    public const LOCATION = 'location';

    public const LOCATION_PLURAL = 'location.plural';

    public const CATEGORY = 'category';

    public const CATEGORY_PLURAL = 'category.plural';

    /** Единица парка / флота (RentalUnit и т.п.) */
    public const FLEET_UNIT = 'fleet_unit';

    public const FLEET_UNIT_PLURAL = 'fleet_unit.plural';

    public const NAV_OPERATIONS = 'nav.operations';

    public const NAV_CATALOG = 'nav.catalog';

    public const NAV_CONTENT = 'nav.content';

    public const NAV_SETTINGS = 'nav.settings';

    /**
     * @return list<string>
     */
    public static function coreKeys(): array
    {
        return [
            self::LEAD,
            self::LEAD_PLURAL,
            self::BOOKING,
            self::BOOKING_PLURAL,
            self::APPOINTMENT,
            self::APPOINTMENT_PLURAL,
            self::REQUEST,
            self::REQUEST_PLURAL,
            self::CUSTOMER,
            self::CUSTOMER_PLURAL,
            self::SERVICE,
            self::SERVICE_PLURAL,
            self::RESOURCE,
            self::RESOURCE_PLURAL,
            self::STAFF_MEMBER,
            self::STAFF_MEMBER_PLURAL,
            self::LOCATION,
            self::LOCATION_PLURAL,
            self::CATEGORY,
            self::CATEGORY_PLURAL,
            self::FLEET_UNIT,
            self::FLEET_UNIT_PLURAL,
        ];
    }

    /**
     * @return list<string>
     */
    public static function navigationKeys(): array
    {
        return [
            self::NAV_OPERATIONS,
            self::NAV_CATALOG,
            self::NAV_CONTENT,
            self::NAV_SETTINGS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(self::coreKeys(), self::navigationKeys())));
    }
}
