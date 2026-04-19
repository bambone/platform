<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MotorcycleResource\Pages;

use App\Filament\Tenant\Forms\LinkedBookableSchedulingForm;
use App\Filament\Tenant\Resources\MotorcycleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class EditMotorcycle extends EditRecord
{
    protected static string $resource = MotorcycleResource::class;

    #[On('motorcycle-settings-updated')]
    public function refreshMotorcycleFromChild(): void
    {
        $this->record->refresh();
    }

    /**
     * Карточка на edit собирается из изолированных Livewire-блоков; глобальный submit не сохраняет модель.
     */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void {}

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');
        $this->form->fill([]);
        $this->callHook('afterFill');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $record;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCancelFormAction(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $recordId = (int) $this->record->getKey();

        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Карточка')
                    ->persistTabInQueryString(LinkedBookableSchedulingForm::MOTORCYCLE_TAB_QUERY_KEY)
                    ->tabs([
                        LinkedBookableSchedulingForm::TAB_KEY_MAIN => Tab::make('Основное')
                            ->id(LinkedBookableSchedulingForm::TAB_KEY_MAIN)
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 12])
                                    ->schema([
                                        SchemaView::make('filament.tenant.resources.motorcycle-resource.blocks.main-column')
                                            ->viewData(['recordId' => $recordId])
                                            ->columnSpan(['default' => 12, 'lg' => 8]),
                                        SchemaView::make('filament.tenant.resources.motorcycle-resource.blocks.side-column')
                                            ->viewData(['recordId' => $recordId])
                                            ->columnSpan(['default' => 12, 'lg' => 4]),
                                    ]),
                            ]),
                        LinkedBookableSchedulingForm::TAB_KEY_ONLINE_BOOKING => Tab::make('Онлайн-запись')
                            ->id(LinkedBookableSchedulingForm::TAB_KEY_ONLINE_BOOKING)
                            ->icon(fn (): ?string => LinkedBookableSchedulingForm::schedulingLocked() ? 'heroicon-o-lock-closed' : null)
                            ->hiddenOn('create')
                            ->visible(fn (): bool => LinkedBookableSchedulingForm::schedulingUiVisible())
                            ->schema([
                                SchemaView::make('filament.tenant.resources.motorcycle-resource.blocks.scheduling-tab')
                                    ->viewData(['recordId' => $recordId]),
                            ])
                            ->columnSpan(['default' => 12, 'lg' => 12]),
                        LinkedBookableSchedulingForm::TAB_KEY_FLEET_UNITS => Tab::make('Единицы парка')
                            ->id(LinkedBookableSchedulingForm::TAB_KEY_FLEET_UNITS)
                            ->hiddenOn('create')
                            ->visible(fn (): bool => (bool) $this->record->uses_fleet_units)
                            ->icon(fn (): ?string => $this->shouldHighlightFleetUnitsTab()
                                ? 'heroicon-o-exclamation-triangle'
                                : null)
                            ->badge(fn (): ?string => $this->shouldHighlightFleetUnitsTab() ? '!' : null)
                            ->badgeColor(fn (): ?string => $this->shouldHighlightFleetUnitsTab() ? 'warning' : null)
                            ->badgeTooltip('Список единиц парка пуст — добавьте хотя бы одну строку на этой вкладке.')
                            ->schema([
                                SchemaView::make('filament.tenant.resources.motorcycle-resource.blocks.fleet-units-tab')
                                    ->viewData(['recordId' => $recordId]),
                            ])
                            ->columnSpan(['default' => 12, 'lg' => 12]),
                    ])
                    ->columnSpan(['default' => 12, 'lg' => 12]),
            ]);
    }

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

    /**
     * Вкладка «Единицы парка» появляется при включении парка; пока список пуст, подсвечиваем её в шапке табов.
     */
    protected function shouldHighlightFleetUnitsTab(): bool
    {
        if (! (bool) $this->record->uses_fleet_units) {
            return false;
        }

        return ! $this->record->rentalUnits()->exists();
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
