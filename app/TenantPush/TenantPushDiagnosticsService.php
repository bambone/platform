<?php

declare(strict_types=1);

namespace App\TenantPush;

use App\Models\Tenant;
use App\Models\TenantPushDiagnostic;

final class TenantPushDiagnosticsService
{
    public function record(
        Tenant $tenant,
        string $checkType,
        string $status,
        TenantPushDiagnosticCode $code,
        ?string $message = null,
        ?array $details = null,
    ): TenantPushDiagnostic {
        return TenantPushDiagnostic::query()->create([
            'tenant_id' => $tenant->id,
            'check_type' => $checkType,
            'status' => $status,
            'code' => $code->value,
            'message' => $message,
            'details_json' => $details,
            'checked_at' => now(),
        ]);
    }
}
