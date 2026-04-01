<?php

namespace Tests;

use App\Tenant\CurrentTenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Разрешает TenantStorage::for($id) в unit-тестах без HTTP-тенанта; в feature-тестах middleware подменит binding.
        app()->instance(CurrentTenant::class, new CurrentTenant(null, null, true));
    }
}
