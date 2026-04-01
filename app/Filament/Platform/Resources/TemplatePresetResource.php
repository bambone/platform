<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\TemplatePresetResource\Pages;
use App\Filament\Support\FilamentInlineMarkdown;
use App\Models\TemplatePreset;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TemplatePresetResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = TemplatePreset::class;

    protected static ?string $navigationLabel = 'Шаблоны сайтов';

    protected static ?string $modelLabel = 'Шаблон';

    protected static ?string $pluralModelLabel = 'Шаблоны';

    protected static ?string $panel = 'platform';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('О шаблоне')
                    ->description(FilamentInlineMarkdown::toHtml(
                        'Шаблон — это **стартовая копия** сайта для нового клиента. После создания клиента его сайт живёт отдельно: изменения шаблона не переписывают уже созданные сайты.'
                    ))
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Например: Базовый прокат'),
                        TextInput::make('slug')
                            ->label('URL-идентификатор')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Внутреннее имя; латиница и дефис.'),
                        Textarea::make('description')
                            ->label('Описание для администраторов')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Не показывается на сайте клиента; помогает выбрать шаблон в списке.'),
                        TextInput::make('sort_order')
                            ->label('Порядок в списке')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Доступен при создании клиента')
                            ->default(true)
                            ->helperText('Выключенные шаблоны не предлагаются в мастере и формах.'),
                    ])->columns(2),

                Section::make('Технический конфиг (для разработчика)')
                    ->description('Структурированные параметры клонирования. Неверные ключи могут сломать развёртывание шаблона — меняйте только по согласованию с разработкой.')
                    ->schema([
                        KeyValue::make('config_json')
                            ->label('Параметры')
                            ->keyLabel('Ключ')
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
                        ->modalHeading('Удалить шаблоны?')
                        ->modalDescription('Новые клиенты не смогут выбрать удалённый шаблон. Уже созданные сайты не изменятся.'),
                ]),
            ])
            ->emptyStateHeading('Шаблонов пока нет')
            ->emptyStateDescription('Создайте шаблон, чтобы быстро запускать новых клиентов с готовой структурой сайта.')
            ->emptyStateIcon('heroicon-o-document-duplicate');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTemplatePresets::route('/'),
            'create' => Pages\CreateTemplatePreset::route('/create'),
            'edit' => Pages\EditTemplatePreset::route('/{record}/edit'),
        ];
    }
}
