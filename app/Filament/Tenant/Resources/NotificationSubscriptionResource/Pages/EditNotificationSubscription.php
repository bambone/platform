<?php

namespace App\Filament\Tenant\Resources\NotificationSubscriptionResource\Pages;

use App\Filament\Tenant\Resources\NotificationSubscriptionResource;
use App\Filament\Tenant\Support\AssertNotificationSubscriptionDestinations;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class EditNotificationSubscription extends EditRecord
{
    protected static string $resource = NotificationSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'notificationSubscriptionEditWhatIs',
                [
                    'Зачем: автоматически оповещать команду при событиях (новая CRM-заявка, запись и т.д.).',
                    '',
                    'Как применять:',
                    '1. Выберите событие.',
                    '2. Включите «Включено».',
                    '3. Отметьте получателей — это записи из «Получатели уведомлений»; без них доставать некуда.',
                    '4. При необходимости задайте «Мин. важность», чтобы правило не срабатывало на слишком низкий уровень.',
                    '',
                    'Название — для себя, по нему проще искать правило в списке.',
                    'Черновики по CRM можно массово создать на списке правил (кнопка на списке).',
                ],
                'Справка по правилу уведомлений',
            ),
            ...parent::getHeaderActions(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing('destinations');
        $data['destination_ids'] = $this->record->destinations->pluck('id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->destinationIds = $data['destination_ids'] ?? [];
        AssertNotificationSubscriptionDestinations::forTenantForm($this->destinationIds);
        unset($data['destination_ids']);

        unset($data['tenant_id'], $data['user_id']);
        $data['tenant_id'] = $this->record->tenant_id;
        $data['user_id'] = Gate::allows('manage_notifications')
            ? $this->record->user_id
            : Auth::id();

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

}
