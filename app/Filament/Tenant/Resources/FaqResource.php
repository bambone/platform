<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Support\AdminEmptyState;
use App\Filament\Support\HintIconTooltip;
use App\Filament\Tenant\Resources\FaqResource\Pages;
use App\Models\Faq;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static ?string $navigationLabel = 'FAQ';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 25;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $modelLabel = 'Вопрос';

    protected static ?string $pluralModelLabel = 'FAQ';

    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Текст на сайте')
                    ->description('Вопрос и ответ выводятся на публичной странице /faq и при необходимости в блоке FAQ на главной (если включено ниже).')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        TextInput::make('question')
                            ->label('Вопрос')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Формулировка так, как её увидит посетитель')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip(fn () => HintIconTooltip::lines(
                                'Краткий понятный вопрос без служебных пометок.',
                                'Отображается как заголовок раскрывающегося пункта на /faq.',
                            )),
                        Textarea::make('answer')
                            ->label('Ответ')
                            ->required()
                            ->rows(6)
                            ->columnSpanFull()
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip(fn () => HintIconTooltip::lines(
                                'Развёрнутый ответ: факты, условия, ссылки.',
                                'Можно обычный текст с абзацами; HTML на сайте зависит от темы.',
                            )),
                    ])
                    ->columns(2),

                Section::make('Группировка и публикация')
                    ->description('Категория и порядок — для удобства на странице FAQ; статус определяет видимость на сайте.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        TextInput::make('category')
                            ->label('Группа (категория)')
                            ->maxLength(255)
                            ->placeholder('Например: Занятия, Оплата')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip(fn () => HintIconTooltip::lines(
                                'Необязательно. Одинаковая строка у нескольких вопросов объединяет их в подзаголовок на /faq (например «Обучение», «Автомобиль»).',
                                'Пусто — пункт без группы.',
                            )),
                        TextInput::make('sort_order')
                            ->label('Порядок в списке')
                            ->numeric()
                            ->default(0)
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip(fn () => HintIconTooltip::lines(
                                'Меньшее число — выше внутри своей категории.',
                                'Удобно задать 10, 20, 30, чтобы потом вставлять пункты между ними.',
                            )),
                        Select::make('status')
                            ->label('Статус')
                            ->options(Faq::statuses())
                            ->required()
                            ->default('published')
                            ->native(true)
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip(fn () => HintIconTooltip::lines(
                                'На сайте показываются только пункты «Опубликован».',
                                'Черновик и «Скрыт» остаются в админке.',
                            )),
                        Toggle::make('show_on_home')
                            ->label('Показывать на главной')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip(fn () => HintIconTooltip::lines(
                                'Если включено, пункт может попасть в блок FAQ на главной (секция темы / конструктор страницы).',
                                'Полный список вопросов — всегда на странице /faq.',
                            )),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminEmptyState::applyInitial(
            $table
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
                ->recordActions([EditAction::make()]),
            'Вопросов в базе пока нет',
            'Добавьте пункты FAQ — они появятся на странице /faq и в блоках конструктора.'
                .AdminEmptyState::hintFiltersAndSearch(),
            'heroicon-o-question-mark-circle',
            [CreateAction::make()->label('Добавить вопрос')],
        );
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
