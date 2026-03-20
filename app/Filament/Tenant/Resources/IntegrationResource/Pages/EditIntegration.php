<?php

namespace App\Filament\Tenant\Resources\IntegrationResource\Pages;

use App\Filament\Tenant\Resources\IntegrationResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditIntegration extends EditRecord
{
    protected static string $resource = IntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Синхронизировать')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $adapter = $this->record->getAdapter();
                    if ($adapter) {
                        $adapter->syncMotorcycles();
                        Notification::make()
                            ->title('Синхронизация запущена')
                            ->body('Проверьте вкладку «Логи» для результата.')
                            ->success()
                            ->send();
                    }
                })
                ->visible(fn () => $this->record->getAdapter() !== null),
            DeleteAction::make(),
        ];
    }
}
