<?php

namespace App\Integrations;

use App\Contracts\IntegrationContract;
use App\Models\Integration;

class RentProgIntegration implements IntegrationContract
{
    public function __construct(
        protected Integration $integration
    ) {}

    public function syncMotorcycles(): bool
    {
        $this->integration->log('sync_motorcycles', 'pending', null, null, 'Метод не реализован (stub)');

        return false;
    }

    public function syncAvailability(): bool
    {
        $this->integration->log('sync_availability', 'pending', null, null, 'Метод не реализован (stub)');

        return false;
    }

    public function syncBookings(): bool
    {
        $this->integration->log('sync_bookings', 'pending', null, null, 'Метод не реализован (stub)');

        return false;
    }

    public function testConnection(): bool
    {
        $this->integration->log('test_connection', 'pending', null, null, 'Метод не реализован (stub)');

        return false;
    }
}
