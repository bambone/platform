<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\SchedulingTargetResource\Pages;

use App\Filament\Tenant\Resources\SchedulingTargetResource;
use App\Scheduling\Enums\SchedulingScope;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSchedulingTarget extends CreateRecord
{
    protected static string $resource = SchedulingTargetResource::class;

    public function mount(): void
    {
        if (! SchedulingTargetResource::canStartCreatingTarget()) {
            Notification::make()
                ->title('Сначала создайте ресурс расписания')
                ->body(
                    'Без календарного ресурса (сотрудник, зал и т.д.) поле «Ресурсы» в форме цели останется пустым. Добавьте ресурс в разделе «Запись: основа» → «Ресурсы расписания», затем вернитесь сюда.'
                )
                ->warning()
                ->send();
            $this->redirect(SchedulingTargetResource::getUrl('index'));

            return;
        }

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['scheduling_scope'] = SchedulingScope::Tenant;
        $data['tenant_id'] = currentTenant()?->id;

        return $data;
    }
}
