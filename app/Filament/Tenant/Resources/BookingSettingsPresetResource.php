<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\BookingSettingsPresetResource\Pages;
use App\Models\BookingSettingsPreset;
use App\Scheduling\BookableServiceSettingsMapper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Tenant\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class BookingSettingsPresetResource extends Resource
{
    protected static ?string $model = BookingSettingsPreset::class;

    protected static ?string $navigationLabel = 'Группы настроек записи';

    protected static ?string $modelLabel = 'Группа настроек записи';

    protected static ?string $pluralModelLabel = 'Группы настроек записи';

    protected static string|UnitEnum|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 12;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function canAccess(): bool
    {
        $tenant = currentTenant();

        return $tenant !== null
            && $tenant->scheduling_module_enabled
            && Gate::allows('manage_scheduling');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', currentTenant()?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('О группе')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Параметры записи')
                    ->description('Эти значения можно массово применить к услугам и к онлайн-записи с карточек каталога.')
                    ->schema(self::payloadFieldComponents()),
            ]);
    }

    /**
     * @return array<Component>
     */
    public static function payloadFieldComponents(): array
    {
        return [
            TextInput::make('payload_duration_minutes')
                ->label('Длительность приёма')
                ->suffix('мин')
                ->numeric()
                ->minValue(1)
                ->default(60)
                ->required(),
            TextInput::make('payload_slot_step_minutes')
                ->label('Шаг между слотами')
                ->suffix('мин')
                ->numeric()
                ->minValue(5)
                ->default(15)
                ->required(),
            TextInput::make('payload_buffer_before_minutes')
                ->label('Запас до начала')
                ->suffix('мин')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            TextInput::make('payload_buffer_after_minutes')
                ->label('Запас после окончания')
                ->suffix('мин')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            TextInput::make('payload_min_booking_notice_minutes')
                ->label('Минимум времени до начала слота')
                ->suffix('мин')
                ->numeric()
                ->minValue(0)
                ->default(120)
                ->required(),
            TextInput::make('payload_max_booking_horizon_days')
                ->label('Запись не дальше, чем')
                ->suffix('дн.')
                ->numeric()
                ->minValue(1)
                ->default(60)
                ->required(),
            Toggle::make('payload_requires_confirmation')
                ->label('Подтверждать заявку вручную')
                ->default(true),
            TextInput::make('payload_sort_weight')
                ->label('Порядок в списке')
                ->numeric()
                ->default(0)
                ->required(),
            Toggle::make('payload_sync_title_from_source')
                ->label('Синхронизировать название услуги с карточкой каталога')
                ->default(true)
                ->helperText('Для услуг, привязанных к мотоциклу или единице парка.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function foldPayloadFormFieldsIntoPayload(array $data): array
    {
        $raw = [
            'duration_minutes' => $data['payload_duration_minutes'] ?? null,
            'slot_step_minutes' => $data['payload_slot_step_minutes'] ?? null,
            'buffer_before_minutes' => $data['payload_buffer_before_minutes'] ?? null,
            'buffer_after_minutes' => $data['payload_buffer_after_minutes'] ?? null,
            'min_booking_notice_minutes' => $data['payload_min_booking_notice_minutes'] ?? null,
            'max_booking_horizon_days' => $data['payload_max_booking_horizon_days'] ?? null,
            'requires_confirmation' => array_key_exists('payload_requires_confirmation', $data)
                ? (bool) $data['payload_requires_confirmation']
                : null,
            'sort_weight' => $data['payload_sort_weight'] ?? null,
            'sync_title_from_source' => array_key_exists('payload_sync_title_from_source', $data)
                ? (bool) $data['payload_sync_title_from_source']
                : null,
        ];

        foreach (array_keys($raw) as $k) {
            unset($data['payload_'.$k]);
        }

        $mapper = app(BookableServiceSettingsMapper::class);
        $data['payload'] = $mapper->extractWhitelisted($raw);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function spreadPayloadToFormFields(BookingSettingsPreset $record): array
    {
        $p = $record->payload ?? [];

        return [
            'payload_duration_minutes' => (int) ($p['duration_minutes'] ?? 60),
            'payload_slot_step_minutes' => (int) ($p['slot_step_minutes'] ?? 15),
            'payload_buffer_before_minutes' => (int) ($p['buffer_before_minutes'] ?? 0),
            'payload_buffer_after_minutes' => (int) ($p['buffer_after_minutes'] ?? 0),
            'payload_min_booking_notice_minutes' => (int) ($p['min_booking_notice_minutes'] ?? 120),
            'payload_max_booking_horizon_days' => (int) ($p['max_booking_horizon_days'] ?? 60),
            'payload_requires_confirmation' => (bool) ($p['requires_confirmation'] ?? true),
            'payload_sort_weight' => (int) ($p['sort_weight'] ?? 0),
            'payload_sync_title_from_source' => (bool) ($p['sync_title_from_source'] ?? true),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Название')->searchable()->sortable(),
                TextColumn::make('description')->label('Описание')->limit(40)->toggleable(),
                TextColumn::make('updated_at')->label('Обновлено')->dateTime()->sortable(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookingSettingsPresets::route('/'),
            'create' => Pages\CreateBookingSettingsPreset::route('/create'),
            'edit' => Pages\EditBookingSettingsPreset::route('/{record}/edit'),
        ];
    }
}
