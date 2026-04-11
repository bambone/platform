<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\TenantSpatieMediaLibraryFileUpload;
use App\Filament\Tenant\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationLabel = 'Отзывы';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 20;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $modelLabel = 'Отзыв';

    protected static ?string $pluralModelLabel = 'Отзывы';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('city')->maxLength(255),
                        TextInput::make('headline')->label('Заголовок / лид')->maxLength(255),
                        TextInput::make('category_key')->label('Категория (ключ)')->maxLength(64),
                        Textarea::make('text_short')->label('Короткий текст')->rows(2),
                        Textarea::make('text_long')->label('Полный текст')->rows(5),
                        Textarea::make('text')->label('Текст (legacy)')->rows(3)
                            ->helperText('Заполняется автоматически из полного/короткого при сохранении, если пусто.'),
                        TextInput::make('rating')->numeric()->minValue(1)->maxValue(5)->default(5),
                        Select::make('media_type')
                            ->label('Тип медиа')
                            ->options(['text' => 'Текст', 'video' => 'Видео'])
                            ->default('text'),
                        TextInput::make('video_url')->label('URL видео')->url()->maxLength(2048),
                        Select::make('motorcycle_id')
                            ->relationship('motorcycle', 'name')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('date'),
                        TextInput::make('source')->maxLength(255),
                    ])->columns(2),

                Section::make('Медиа и статус')
                    ->schema([
                        TenantSpatieMediaLibraryFileUpload::make('avatar')
                            ->collection('avatar')
                            ->disk(config('media-library.disk_name'))
                            ->visibility('public')
                            ->conversionsDisk(config('media-library.disk_name'))
                            ->image()
                            ->label('Аватар'),
                        TextInput::make('sort_order')->numeric()->default(0),
                        Select::make('status')->options(Review::statuses())->required()->default('published'),
                        Toggle::make('is_featured')->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('city')->placeholder('—'),
                TextColumn::make('text')->limit(40)->placeholder('—'),
                TextColumn::make('rating'),
                TextColumn::make('motorcycle.name')->placeholder('—'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (?string $state): string => $state ? (Review::statuses()[$state] ?? $state) : ''),
                IconColumn::make('is_featured')->boolean(),
                TextColumn::make('created_at')->date('d.m.Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Review::statuses()),
            ])
            ->defaultSort('sort_order')
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
