<?php

namespace App\Filament\Tenant\Resources\MotorcycleResource\Pages;

use App\Filament\Tenant\Resources\MotorcycleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMotorcycle extends EditRecord
{
    protected static string $resource = MotorcycleResource::class;

    public function getHeading(): string
    {
        return $this->record->name ?? 'Редактирование мотоцикла';
    }

    public function getSubheading(): ?string
    {
        return $this->record->brand && $this->record->model
            ? "{$this->record->brand} {$this->record->model}"
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\DeleteAction::make()
                    ->modalHeading('Удалить мотоцикл'),
                Actions\ForceDeleteAction::make()
                    ->modalHeading('Окончательно удалить'),
                Actions\RestoreAction::make()
                    ->label('Восстановить'),
            ])
                ->label('Действия')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
                ->color('gray'),
        ];
    }
}
