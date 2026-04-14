<?php

namespace App\Filament\Tenant\Resources\NotificationSubscriptionResource\Pages;

use App\Filament\Tenant\Resources\NotificationSubscriptionResource;
use App\Models\NotificationDestination;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditNotificationSubscription extends EditRecord
{
    protected static string $resource = NotificationSubscriptionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing('destinations');
        $data['destination_ids'] = $this->record->destinations->pluck('id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->destinationIds = $data['destination_ids'] ?? [];
        $this->assertDestinationIdsBelongToTenant($this->destinationIds);
        unset($data['destination_ids']);

        return $data;
    }

    /** @var list<int|string> */
    protected array $destinationIds = [];

    protected function afterSave(): void
    {
        $sync = [];
        $order = 0;
        foreach ($this->destinationIds as $id) {
            $sync[(int) $id] = [
                'delivery_mode' => 'immediate',
                'delay_seconds' => null,
                'order_index' => $order++,
                'is_enabled' => true,
            ];
        }
        $this->record->destinations()->sync($sync);
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function assertDestinationIdsBelongToTenant(array $ids): void
    {
        $ids = array_map('intval', $ids);
        if ($ids === []) {
            return;
        }

        $tenant = currentTenant();
        if ($tenant === null) {
            throw ValidationException::withMessages([
                'destination_ids' => 'Контекст клиента не найден.',
            ]);
        }

        $count = NotificationDestination::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $ids)
            ->count();

        if ($count !== count($ids)) {
            throw ValidationException::withMessages([
                'destination_ids' => 'Некорректные получатели.',
            ]);
        }
    }
}
