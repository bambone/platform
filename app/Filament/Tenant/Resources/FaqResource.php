<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\FaqResource\Pages;
use App\Models\Faq;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static ?string $navigationLabel = 'FAQ';

    protected static ?string $modelLabel = 'Вопрос';

    protected static ?string $pluralModelLabel = 'FAQ';

    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('question')->required()->maxLength(255),
                        Textarea::make('answer')->required()->rows(4),
                        TextInput::make('category')->maxLength(255),
                        TextInput::make('sort_order')->numeric()->default(0),
                        Select::make('status')->options(Faq::statuses())->required()->default('published'),
                        Toggle::make('show_on_home')->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('question')->searchable()->limit(50),
                TextColumn::make('category')->placeholder('—'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (?string $state): string => $state ? (Faq::statuses()[$state] ?? $state) : ''),
                IconColumn::make('show_on_home')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Faq::statuses()),
            ])
            ->defaultSort('sort_order')
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}
