<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\PlanResource\Pages;
use App\Filament\Support\PlanUiSchema;
use App\Models\Plan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = Plan::class;

    protected static ?string $navigationLabel = 'Тарифы';

    protected static ?string $modelLabel = 'Тариф';

    protected static ?string $pluralModelLabel = 'Тарифы';

    protected static ?string $panel = 'platform';

    public static function form(Schema $schema): Schema
    {
        $limitComponents = [];
        foreach (PlanUiSchema::limitFields() as $key => $meta) {
            $limitComponents[] = TextInput::make('limit_'.$key)
                ->label($meta['label'])
                ->helperText($meta['helper'])
                ->numeric()
                ->minValue(0)
                ->placeholder('Например: '.($key === 'max_models' ? '10' : '100'));
        }

        return $schema
            ->components([
                Section::make('О тарифе')
                    ->description('Тариф определяет лимиты и доступные функции для клиентов платформы, которым он назначен.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название тарифа')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Например: Pro'),
                        TextInput::make('slug')
                            ->label('URL-идентификатор')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Используется внутри системы. Только латиница, цифры и дефис, например: pro.'),
                        TextInput::make('sort_order')
                            ->label('Порядок в списке')
                            ->numeric()
                            ->default(0)
                            ->helperText('Меньшее число — выше в списках выбора.'),
                        Toggle::make('is_active')
                            ->label('Тариф доступен для выбора')
                            ->helperText('Выключенные тарифы обычно не предлагаются новым клиентам.')
                            ->default(true),
                    ])->columns(2),

                Section::make('Лимиты тарифа')
                    ->description('Ограничения по объёму использования. Пустое поле можно оставить, если лимит не применяется.')
                    ->schema($limitComponents)
                    ->columns(2),

                Section::make('Доступные функции')
                    ->description('Отметьте возможности, включённые в этот тариф. Сайт и кабинет клиента могут скрывать недоступные разделы.')
                    ->schema([
                        CheckboxList::make('plan_features')
                            ->label('Функции')
                            ->options(PlanUiSchema::featureOptions())
                            ->columns(2)
                            ->bulkToggleable()
                            ->gridDirection('row'),
                    ]),

                Section::make('Дополнительные лимиты (для разработчика)')
                    ->description('Не используйте без необходимости. Служит для редких числовых лимитов, которых нет в списке выше. Неверные ключи могут не учитываться приложением.')
                    ->schema([
                        KeyValue::make('limits_extra')
                            ->label('Дополнительные параметры')
                            ->keyLabel('Параметр')
                            ->valueLabel('Значение')
                            ->addActionLabel('Добавить')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Идентификатор')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Удалить тарифы?')
                        ->modalDescription('Клиенты с этим тарифом могут остаться без привязки к тарифу. Проверьте назначения перед удалением.'),
                ]),
            ])
            ->emptyStateHeading('Тарифы ещё не созданы')
            ->emptyStateDescription('Создайте первый тариф, чтобы назначать его клиентам платформы.')
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
