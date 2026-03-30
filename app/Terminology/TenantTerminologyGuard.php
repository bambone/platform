<?php

namespace App\Terminology;

use App\Models\DomainTerm;

/**
 * Server-side checks for tenant-driven terminology edits (Filament and any future callers).
 */
final class TenantTerminologyGuard
{
    public static function assertTermEditableByTenant(DomainTerm $term): void
    {
        abort_unless($term->is_editable_by_tenant, 403);
    }
}
