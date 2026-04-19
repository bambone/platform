<?php

namespace App\Filament\Tenant\Resources\NotificationSubscriptionResource\Pages;

use App\Filament\Tenant\Resources\NotificationSubscriptionResource;
use App\Filament\Tenant\Support\AssertNotificationSubscriptionDestinations;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateNotificationSubscription extends CreateRecord
{
    protected static string $resource = NotificationSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'notificationSubscriptionCreateWhatIs',
                [
                    'Зачем: автоматически оповещать команду при событиях (новая CRM-заявка, запись и т.д.).',
                    '',
                    'Как применять:',
                    '1. Выберите событие.',
                    '2. Включите «Включено».',
                    '3. Отметьте получателей — сначала создайте их в «Получатели уведомлений».',
                    '4. При необходимости задайте «Мин. важность».',
                    '',
                    'После сохранения правило начнёт участвовать в доставке.',
                ],
                'Справка по правилу уведомлений',
            ),
            ...parent::getHeaderActions(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            throw ValidationException::withMessages([
                'name' => 'Контекст клиента не найден.',
            ]);
        }
        $data['tenant_id'] = $tenant->id;
        $data['created_by_user_id'] = Auth::id();
        $data['user_id'] = Gate::allows('manage_notifications') ? null : Auth::id();
        $this->destinationIds = $data['destination_ids'] ?? [];
        AssertNotificationSubscriptionDestinations::forTenantForm($this->destinationIds);
        unset($data['destination_ids']);

        return $data;
    }

    /** @var list<int|string> */
    protected array $destinationIds = [];

    protected function afterCreate(): void
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

}
