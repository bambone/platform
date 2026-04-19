<?php

declare(strict_types=1);

namespace App\Livewire\Tenant\Motorcycles;

use App\Enums\MotorcycleLocationMode;
use App\Filament\Support\HintIconTooltip;
use App\Filament\Tenant\Resources\TenantLocationResource;
use App\Models\Motorcycle;
use App\Models\RentalUnit;
use App\Models\TenantLocation;
use App\Services\Catalog\RentalUnitCsvExchangeService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MotorcycleRentalUnitsPanel extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public int $motorcycleId;

    /**
     * Вложенный Livewire без Filament Page: таблица должна быть инициализирована до первого рендера.
     * Порядок вызовов (флаг → mount → booted) соответствует контракту Filament `InteractsWithTable`;
     * при обновлении Filament/Livewire этот блок стоит перепроверить на двойную инициализацию.
     */
    public function mount(int $motorcycleId): void
    {
        $this->motorcycleId = $motorcycleId;
        $this->shouldMountInteractsWithTable = true;
        $this->mountInteractsWithTable();
        $this->bootedInteractsWithTable();
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    private function resolveMotorcycle(): Motorcycle
    {
        return Motorcycle::query()->whereKey($this->motorcycleId)->firstOrFail();
    }

    /**
     * Обновляет родительскую страницу редактирования (бейдж на вкладке «Единицы парка» и т.п.).
     */
    private function dispatchMotorcycleSettingsUpdated(): void
    {
        $this->dispatch('motorcycle-settings-updated');
    }

    /**
     * @return array<int|string, string>
     */
    private function activeTenantLocationOptions(): array
    {
        return TenantLocation::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private function unitFormFields(): array
    {
        $m = $this->resolveMotorcycle();
        $perUnit = ($m->location_mode ?? MotorcycleLocationMode::Everywhere) === MotorcycleLocationMode::PerUnit;
        $locationOptions = $this->activeTenantLocationOptions();

        $fields = [
            TextInput::make('unit_label')
                ->label('Метка / название')
                ->maxLength(255)
                ->hintIcon('heroicon-o-information-circle')
                ->hintIconTooltip(fn () => HintIconTooltip::lines(
                    'Краткое имя для админки, таблицы и экспорта.',
                    'Уникальность не требуется.',
                )),
            Select::make('status')
                ->label('Статус')
                ->options(RentalUnit::statuses())
                ->required()
                ->native(true)
                ->hintIcon('heroicon-o-information-circle')
                ->hintIconTooltip(fn () => HintIconTooltip::lines(
                    'Активна — участвует в выдаче и бронировании.',
                    'На обслуживании — временно недоступна.',
                    'Неактивна — скрыта из выдачи.',
                )),
            TextInput::make('external_id')
                ->label('Внешний ID / артикул')
                ->maxLength(255)
                ->hintIcon('heroicon-o-information-circle')
                ->hintIconTooltip(fn () => HintIconTooltip::lines(
                    'Если ведёте учёт во внешней программе или синхронизируетесь по артикулу.',
                    'Укажите тот же идентификатор, что и во внешней системе.',
                )),
            Textarea::make('notes')
                ->label('Заметка')
                ->rows(2)
                ->columnSpanFull()
                ->hintIcon('heroicon-o-information-circle')
                ->hintIconTooltip(fn () => HintIconTooltip::lines(
                    'Внутренняя пометка для команды.',
                    'На публичный сайт не выводится.',
                )),
        ];

        if ($perUnit) {
            $locationsField = CheckboxList::make('tenant_location_ids')
                ->label('Локации')
                ->hintIcon('heroicon-o-information-circle')
                ->hintIconTooltip(fn () => HintIconTooltip::lines(
                    'Где эта единица доступна для выдачи.',
                    'При режиме «локации по единицам» укажите хотя бы одну точку.',
                ))
                ->options($locationOptions)
                ->columns(2)
                ->columnSpanFull();

            if ($locationOptions === []) {
                $createUrl = TenantLocationResource::getUrl('create');
                $locationsField->helperText(new HtmlString(
                    'В справочнике нет активных локаций. '
                    .'<a href="'.e($createUrl).'" class="fi-link text-primary-600 underline dark:text-primary-400">Добавить локацию</a>.'
                ));
            }

            $fields[] = $locationsField;
        }

        return $fields;
    }

    public function table(Table $table): Table
    {
        $m = $this->resolveMotorcycle();
        $perUnit = ($m->location_mode ?? MotorcycleLocationMode::Everywhere) === MotorcycleLocationMode::PerUnit;

        return $table
            ->query(
                RentalUnit::query()
                    ->where('motorcycle_id', $this->motorcycleId)
            )
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('unit_label')->label('Метка')->placeholder('—')->searchable(),
                TextColumn::make('status')->label('Статус')->formatStateUsing(
                    fn (?string $state): string => $state ? (RentalUnit::statuses()[$state] ?? $state) : '',
                ),
                TextColumn::make('external_id')->label('Внешний ID')->placeholder('—'),
                TextColumn::make('locations_list')
                    ->label('Локации')
                    ->placeholder('—')
                    ->visible($perUnit)
                    ->getStateUsing(function (RentalUnit $record): string {
                        return $record->tenantLocations()->pluck('name')->implode(', ');
                    }),
                TextColumn::make('locations_inherit')
                    ->label('Локации')
                    ->visible(! $perUnit)
                    ->state('Как у карточки'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Добавить единицу')
                    ->schema(fn (Schema $schema): Schema => $schema->components([
                        Section::make('Единица парка')
                            ->schema($this->unitFormFields())
                            ->columns(2),
                    ]))
                    ->using(function (array $data): Model {
                        $m = $this->resolveMotorcycle();
                        $perUnit = ($m->location_mode ?? MotorcycleLocationMode::Everywhere) === MotorcycleLocationMode::PerUnit;
                        $locIds = isset($data['tenant_location_ids']) && is_array($data['tenant_location_ids'])
                            ? array_values(array_map('intval', array_filter($data['tenant_location_ids'])))
                            : [];
                        unset($data['tenant_location_ids']);
                        $data['tenant_id'] = $m->tenant_id;
                        $data['motorcycle_id'] = $m->id;
                        $data['config'] = $data['config'] ?? null;
                        $unit = RentalUnit::query()->create($data);
                        if ($perUnit) {
                            $unit->tenantLocations()->sync($locIds);
                        }

                        $this->dispatchMotorcycleSettingsUpdated();

                        return $unit;
                    }),
                Action::make('exportCsv')
                    ->label('Экспорт CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (): StreamedResponse {
                        $m = $this->resolveMotorcycle();
                        $csv = app(RentalUnitCsvExchangeService::class)->exportToCsvString($m);
                        $name = 'rental-units-moto-'.$m->id.'.csv';

                        return response()->streamDownload(
                            function () use ($csv): void {
                                echo $csv;
                            },
                            $name,
                            ['Content-Type' => 'text/csv; charset=UTF-8'],
                        );
                    }),
                Action::make('importCsv')
                    ->label('Импорт CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema(fn (Schema $schema): Schema => $schema->components([
                        Section::make('Импорт')
                            ->schema([
                                FileUpload::make('file')
                                    ->label('Файл CSV')
                                    ->acceptedFileTypes(['text/csv', 'text/plain', '.csv'])
                                    ->required(),
                            ]),
                    ]))
                    ->action(function (array $data): void {
                        $m = $this->resolveMotorcycle();
                        /** @var TemporaryUploadedFile|string|null $file */
                        $file = $data['file'] ?? null;
                        if ($file === null) {
                            return;
                        }
                        $body = $file instanceof TemporaryUploadedFile
                            ? $file->get()
                            : (is_string($file) ? (string) file_get_contents($file) : '');
                        $perUnit = ($m->location_mode ?? MotorcycleLocationMode::Everywhere) === MotorcycleLocationMode::PerUnit;
                        $result = app(RentalUnitCsvExchangeService::class)->importFromCsvString($m, $body, $perUnit);
                        $msg = 'Создано: '.$result['created'].', обновлено: '.$result['updated'].'.';
                        if ($result['errors'] !== []) {
                            $msg .= ' '.implode(' ', array_slice($result['errors'], 0, 5));
                            Notification::make()->title('Импорт завершён с замечаниями')->body($msg)->warning()->send();
                        } else {
                            Notification::make()->title('Импорт выполнен')->body($msg)->success()->send();
                        }
                        $this->resetTable();
                        $this->dispatchMotorcycleSettingsUpdated();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(fn (Schema $schema): Schema => $schema->components([
                        Section::make('Единица парка')
                            ->schema($this->unitFormFields())
                            ->columns(2),
                    ]))
                    ->fillForm(function (RentalUnit $record): array {
                        $m = $this->resolveMotorcycle();
                        $perUnit = ($m->location_mode ?? MotorcycleLocationMode::Everywhere) === MotorcycleLocationMode::PerUnit;
                        $data = [
                            'unit_label' => $record->unit_label,
                            'status' => $record->status,
                            'external_id' => $record->external_id,
                            'notes' => $record->notes,
                        ];
                        if ($perUnit) {
                            $data['tenant_location_ids'] = $record->tenantLocations()->pluck('tenant_locations.id')->all();
                        }

                        return $data;
                    })
                    ->using(function (RentalUnit $record, array $data): void {
                        $m = $this->resolveMotorcycle();
                        $perUnit = ($m->location_mode ?? MotorcycleLocationMode::Everywhere) === MotorcycleLocationMode::PerUnit;
                        $locIds = isset($data['tenant_location_ids']) && is_array($data['tenant_location_ids'])
                            ? array_values(array_map('intval', array_filter($data['tenant_location_ids'])))
                            : [];
                        unset($data['tenant_location_ids']);
                        $record->update($data);
                        if ($perUnit) {
                            $record->tenantLocations()->sync($locIds);
                        } else {
                            $record->tenantLocations()->sync([]);
                        }
                        $this->dispatchMotorcycleSettingsUpdated();
                    }),
                DeleteAction::make()
                    ->using(function (Model $record): ?bool {
                        /** @var RentalUnit $record */
                        $deleted = $record->delete();
                        if ($deleted) {
                            $this->dispatchMotorcycleSettingsUpdated();
                        }

                        return $deleted;
                    }),
            ])
            ->defaultSort('id');
    }

    public function render(): View
    {
        $m = $this->resolveMotorcycle();
        $count = RentalUnit::query()->where('motorcycle_id', $this->motorcycleId)->count();

        return view('livewire.tenant.motorcycles.motorcycle-rental-units-panel', [
            'motorcycle' => $m,
            'unitsCount' => $count,
        ]);
    }
}
