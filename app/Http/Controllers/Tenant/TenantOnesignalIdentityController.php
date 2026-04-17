<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantOnesignalPushIdentity;
use App\TenantPush\OneSignalExternalUserId;
use App\TenantPush\TenantPushNotificationBindingSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantOnesignalIdentityController extends Controller
{
    public function store(Request $request, TenantPushNotificationBindingSync $bindingSync): JsonResponse
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return response()->json(['message' => 'Tenant context missing'], 404);
        }

        $user = Auth::user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $externalId = OneSignalExternalUserId::format((int) $tenant->id, (int) $user->id);

        TenantOnesignalPushIdentity::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ],
            [
                'external_user_id' => $externalId,
                'is_active' => true,
                'last_seen_at' => now(),
            ],
        );

        $bindingSync->syncCrmRequestCreated($tenant);

        return response()->json(['success' => true, 'external_user_id' => $externalId]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $tenant = currentTenant();
        $user = Auth::user();
        if ($tenant === null || $user === null) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        TenantOnesignalPushIdentity::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        return response()->json(['success' => true]);
    }
}
