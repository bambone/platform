<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\SeoMetaFields;
use App\Filament\Tenant\Resources\PageResource\Pages;
use App\Filament\Tenant\Resources\PageResource\RelationManagers\SectionsRelationManager;
use App\Models\Page;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationLabel = 'Страницы';

    protected static ?string $modelLabel = 'Страница';

    protected static ?string $pluralModelLabel = 'Страницы';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                        TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                        Select::make('template')->options(['default' => 'По умолчанию'])->default('default'),
                        Select::make('status')->options(Page::statuses())->required()->default('draft'),
                    ])->columns(2),
                SeoMetaFields::make(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (?string $state): string => $state ? (Page::statuses()[$state] ?? $state) : ''),
                TextColumn::make('sections_count')->counts('sections')->label('Секций'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Page::statuses()),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
