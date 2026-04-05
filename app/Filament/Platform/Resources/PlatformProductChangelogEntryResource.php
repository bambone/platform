<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\PlatformProductChangelogEntryResource\Pages;
use App\Models\PlatformProductChangelogEntry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class PlatformProductChangelogEntryResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = PlatformProductChangelogEntry::class;

    protected static ?string $navigationLabel = 'Чейнджлог продукта';

    protected static ?string $modelLabel = 'Запись чейнджлога';

    protected static ?string $pluralModelLabel = 'Чейнджлог продукта';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';

    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Запись')
                    ->schema([
                        DatePicker::make('entry_date')
                            ->label('Дата')
                            ->required(),
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->required()
                            ->maxLength(255),
                        ToggleButtons::make('_changelog_markdown_mode')
                            ->label('Режим текста')
                            ->helperText('«Отображение» — как в кабинете клиента (Markdown → HTML).')
                            ->options([
                                'edit' => 'Исходный код',
                                'preview' => 'Отображение',
                            ])
                            ->default('edit')
                            ->inline()
                            ->live()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        MarkdownEditor::make('summary')
                            ->label('Кратко (Markdown)')
                            ->fileAttachments(false)
                            ->minHeight('6rem')
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => ($get('_changelog_markdown_mode') ?? 'edit') === 'edit'),
                        MarkdownEditor::make('body')
                            ->label('Полный текст (Markdown)')
                            ->fileAttachments(false)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => ($get('_changelog_markdown_mode') ?? 'edit') === 'edit'),
                        ViewField::make('changelog_markdown_preview')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => ($get('_changelog_markdown_mode') ?? 'edit') === 'preview')
                            ->view('filament.platform.resources.platform-product-changelog-entry.markdown-preview')
                            ->viewData(function (Get $get): array {
                                return [
                                    'summaryHtml' => Str::markdown($get('summary') ?? ''),
                                    'bodyHtml' => Str::markdown($get('body') ?? ''),
                                ];
                            })
                            ->columnSpanFull(),
                        TextInput::make('sort_weight')
                            ->label('Порядок внутри дня (больше — выше)')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_published')
                            ->label('Опубликовано')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_date')
                    ->label('Дата')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->wrap(),
                IconColumn::make('is_published')
                    ->label('Пуб.')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('publication')
                    ->label('Статус')
                    ->schema([
                        Select::make('value')
                            ->label('Статус')
                            ->options([
                                '' => 'Все',
                                'published' => 'Опубликовано',
                                'draft' => 'Черновик',
                            ])
                            ->default(''),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $v = (string) ($data['value'] ?? '');
                        if ($v === 'published') {
                            $query->where('is_published', true);
                        }
                        if ($v === 'draft') {
                            $query->where('is_published', false);
                        }
                    }),
                Filter::make('entry_period')
                    ->label('Дата записи')
                    ->schema([
                        DatePicker::make('from')->label('С'),
                        DatePicker::make('until')->label('По'),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        if (! empty($data['from'])) {
                            $query->whereDate('entry_date', '>=', $data['from']);
                        }
                        if (! empty($data['until'])) {
                            $query->whereDate('entry_date', '<=', $data['until']);
                        }
                    }),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('entry_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformProductChangelogEntries::route('/'),
            'create' => Pages\CreatePlatformProductChangelogEntry::route('/create'),
            'edit' => Pages\EditPlatformProductChangelogEntry::route('/{record}/edit'),
        ];
    }
}
