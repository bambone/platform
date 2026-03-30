<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\DomainTermResource\Pages;
use App\Models\DomainTerm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DomainTermResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = DomainTerm::class;

    protected static ?string $navigationLabel = 'Термины (словарь)';

    protected static ?string $modelLabel = 'Системный термин';

    protected static ?string $pluralModelLabel = 'Системные термины';

    protected static ?string $panel = 'platform';

    protected static string|\UnitEnum|null $navigationGroup = 'Система';

    protected static ?int $navigationSort = 25;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('group')->orderBy('term_key');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ключ и группа')
                    ->description('term_key — стабильный идентификатор в коде; tenant не может его менять.')
                    ->schema([
                        TextInput::make('term_key')
                            ->label('Системный ключ (term_key)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit')
                            ->helperText('Только латиница, цифры, точки и подчёркивания. После создания ключ не меняют.'),
                        TextInput::make('group')
                            ->label('Группа')
                            ->required()
                            ->maxLength(64)
                            ->placeholder('crm, catalog, navigation, …'),
                        TextInput::make('default_label')
                            ->label('Универсальная подпись по умолчанию')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Описание для платформы')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Поведение')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                        Toggle::make('is_editable_by_tenant')
                            ->label('Tenant может переименовать')
                            ->default(true),
                        Toggle::make('is_required')
                            ->label('Обязателен в словаре')
                            ->default(true),
                        TextInput::make('value_type')
                            ->label('Тип значения')
                            ->default('text')
                            ->maxLength(32)
                            ->helperText('Сейчас используется text; поле для будущего расширения.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('term_key')
                    ->label('Ключ')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group')
                    ->label('Группа')
                    ->sortable(),
                TextColumn::make('default_label')
                    ->label('Подпись по умолчанию')
                    ->wrap(),
                IconColumn::make('is_active')
                    ->label('Акт.')
                    ->boolean(),
                IconColumn::make('is_editable_by_tenant')
                    ->label('Tenant')
                    ->boolean(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Удалить выбранные термины?')
                        ->modalDescription('Удаление сломает пресеты и overrides, ссылающиеся на эти term_id. Обычно термины не удаляют, а отключают.'),
                ]),
            ])
            ->defaultSort('term_key');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDomainTerms::route('/'),
            'create' => Pages\CreateDomainTerm::route('/create'),
            'edit' => Pages\EditDomainTerm::route('/{record}/edit'),
        ];
    }
}
