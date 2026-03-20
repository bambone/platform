<?php

namespace App\Contracts;

use App\Models\Integration;

interface IntegrationContract
{
    public function __construct(Integration $integration);

    public function syncMotorcycles(): bool;

    public function syncAvailability(): bool;

    public function syncBookings(): bool;

    public function testConnection(): bool;
}
