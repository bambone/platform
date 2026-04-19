<?php

namespace App\Filament\Tenant\Support;

use App\Models\NotificationDestination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AssertNotificationSubscriptionDestinations
{
    /**
     * @param  list<int|string>  $ids
     */
    public static function forTenantForm(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $tenant = currentTenant();
        if ($tenant === null) {
            throw ValidationException::withMessages([
                'destination_ids' => 'Контекст клиента не найден.',
            ]);
        }

        $q = NotificationDestination::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $ids);

        if (! Gate::allows('manage_notifications')) {
            $q->where('user_id', Auth::id());
        }

        if ($q->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'destination_ids' => 'Некорректные получатели.',
            ]);
        }
    }
}
