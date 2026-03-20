<?php

namespace App\Services;

use App\Models\Tenant;

class CurrentTenantManager
{
    protected ?Tenant $tenant = null;

    protected bool $resolved = false;

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->resolved = true;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->resolved = false;
    }

    public function getId(): ?int
    {
        return $this->tenant?->id;
    }
}
