<?php

namespace App\Filament\Tenant\Resources\CalendarConnectionResource\RelationManagers;

use App\Filament\Shared\TimezoneSelect;
use App\Models\CalendarSubscription;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalendarSubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Календари в аккаунте';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Подписка')
                    ->schema([
                        TextInput::make('external_calendar_id')->label('Внешний ID календаря')->required()->maxLength(255),
                        TextInput::make('title')->label('Заголовок')->maxLength(255),
                        TimezoneSelect::make('timezone')->nullable(),
                        Toggle::make('use_for_busy')->label('Учитывать busy')->default(true),
                        Toggle::make('use_for_write')->label('Писать события')->default(false),
                        Toggle::make('is_active')->label('Активно')->default(true),
                        TextInput::make('stale_after_seconds')
                            ->label('Устаревание, сек')
                            ->numeric()
                            ->minValue(60)
                            ->nullable(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('external_calendar_id')->label('ID')->limit(24),
                TextColumn::make('title')->label('Название')->placeholder('—'),
                IconColumn::make('use_for_busy')->label('Busy')->boolean(),
                IconColumn::make('use_for_write')->label('Write')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        return DB::transaction(function () use ($data): Model {
                            $connectionId = (int) $this->getOwnerRecord()->getKey();
                            $data['calendar_connection_id'] = $connectionId;

                            $external = CalendarSubscription::canonicalizeExternalCalendarId($data['external_calendar_id'] ?? '');
                            $data['external_calendar_id'] = $external;

                            if (CalendarSubscription::query()
                                ->where('calendar_connection_id', $connectionId)
                                ->where('external_calendar_id', $external)
                                ->exists()) {
                                throw ValidationException::withMessages([
                                    'external_calendar_id' => 'Календарь с таким внешним ID уже привязан к этому подключению.',
                                ]);
                            }

                            return CalendarSubscription::query()->create($data);
                        });
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->using(function (CalendarSubscription $record, array $data): CalendarSubscription {
                        return DB::transaction(function () use ($record, $data): CalendarSubscription {
                            $connectionId = (int) $this->getOwnerRecord()->getKey();
                            $external = CalendarSubscription::canonicalizeExternalCalendarId(
                                (string) ($data['external_calendar_id'] ?? $record->external_calendar_id)
                            );
                            $data['external_calendar_id'] = $external;

                            if (CalendarSubscription::query()
                                ->where('calendar_connection_id', $connectionId)
                                ->where('external_calendar_id', $external)
                                ->whereKeyNot($record->getKey())
                                ->exists()) {
                                throw ValidationException::withMessages([
                                    'external_calendar_id' => 'Календарь с таким внешним ID уже привязан к этому подключению.',
                                ]);
                            }

                            $record->update($data);

                            return $record->refresh();
                        });
                    }),
                DeleteAction::make(),
            ]);
    }
}
