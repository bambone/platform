<?php

namespace App\Filament\Tenant\Resources\NotificationSubscriptionResource\Pages;

use App\Filament\Tenant\Resources\NotificationSubscriptionResource;
use App\NotificationCenter\NotificationRuleDraftGenerator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListNotificationSubscriptions extends ListRecords
{
    protected static string $resource = NotificationSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateDraftRules')
                ->label('Сгенерировать черновики правил')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->modalHeading('Сгенерировать черновики правил')
                ->modalDescription(
                    'Будут созданы правила для события «Новая заявка» по уникальным парам источник/тип из ваших CRM-заявок. По умолчанию правила выключены — включите их после проверки. Уже существующие совпадающие правила пропускаются.',
                )
                ->action(function (): void {
                    $out = app(NotificationRuleDraftGenerator::class)->generateForCurrentUser();
                    Notification::make()
                        ->title('Готово')
                        ->body('Создано: '.$out['created'].', пропущено (уже есть): '.$out['skipped'].'.')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
