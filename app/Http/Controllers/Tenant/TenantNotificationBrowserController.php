<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\NotificationEvent;
use App\Models\TenantUserNotificationUiPreference;
use App\Services\Platform\PlatformNotificationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantNotificationBrowserController extends Controller
{
    public function vapidPublic(PlatformNotificationSettings $platform): JsonResponse
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return response()->json(['message' => 'Tenant context missing'], 404);
        }

        return response()->json([
            'vapid_public_key' => $platform->vapidPublicKey(),
        ]);
    }

    public function savePreferences(Request $request): JsonResponse
    {
        $tenant = currentTenant();
        $user = Auth::user();
        if ($tenant === null || $user === null) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'browser_notifications_enabled' => ['sometimes', 'boolean'],
            'sound_enabled' => ['sometimes', 'boolean'],
            'last_permission_state' => ['sometimes', 'string', 'max:32'],
        ]);

        $prefs = TenantUserNotificationUiPreference::query()->firstOrNew([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);
        if (! $prefs->exists) {
            $prefs->browser_notifications_enabled = false;
            $prefs->sound_enabled = false;
            $prefs->last_permission_state = 'default';
        }
        foreach ($validated as $key => $value) {
            $prefs->{$key} = $value;
        }
        $prefs->save();

        return response()->json([
            'success' => true,
            'preferences' => [
                'browser_notifications_enabled' => $prefs->browser_notifications_enabled,
                'sound_enabled' => $prefs->sound_enabled,
                'last_permission_state' => $prefs->last_permission_state,
            ],
        ]);
    }

    public function loadPreferences(): JsonResponse
    {
        $tenant = currentTenant();
        $user = Auth::user();
        if ($tenant === null || $user === null) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $prefs = TenantUserNotificationUiPreference::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ],
            [
                'browser_notifications_enabled' => false,
                'sound_enabled' => false,
                'last_permission_state' => 'default',
            ],
        );

        return response()->json([
            'preferences' => [
                'browser_notifications_enabled' => $prefs->browser_notifications_enabled,
                'sound_enabled' => $prefs->sound_enabled,
                'last_permission_state' => $prefs->last_permission_state,
            ],
        ]);
    }

    /**
     * Max notification_events.id for CRM new-request events (for polling fallback when sound should trigger).
     */
    public function crmWatermark(): JsonResponse
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return response()->json(['message' => 'Tenant context missing'], 404);
        }

        $maxId = (int) NotificationEvent::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'crm_request.created')
            ->max('id');

        return response()->json(['watermark' => $maxId]);
    }
}
