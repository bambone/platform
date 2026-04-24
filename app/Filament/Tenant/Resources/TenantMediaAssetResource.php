<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Filament\Support\AdminEmptyState;
use App\Filament\Tenant\Resources\TenantMediaAssetResource\Pages;
use App\Models\TenantMediaAsset;
use App\Support\Storage\TenantPublicAssetResolver;
use App\Tenant\BlackDuck\BlackDuckMediaRole;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class TenantMediaAssetResource extends Resource
{
    protected static ?string $model = TenantMediaAsset::class;

    protected static ?string $navigationLabel = 'Медиа (каталог)';

    protected static ?string $modelLabel = 'Медиа-объект';

    protected static ?string $pluralModelLabel = 'Медиа (каталог)';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 40;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    public static function canAccess(): bool
    {
        $t = currentTenant();

        return $t !== null && (string) $t->theme_key === 'black_duck';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', currentTenant()?->id)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public static function form(Schema $schema): Schema
    {
        $roleOptions = array_combine(
            array_map(fn (BlackDuckMediaRole $r) => $r->value, BlackDuckMediaRole::cases()),
            array_map(fn (BlackDuckMediaRole $r) => $r->value, BlackDuckMediaRole::cases()),
        ) ?: [];

        return $schema->components([
            Section::make('Идентификация и видимость')
                ->schema([
                    Select::make('role')
                        ->label('Роль')
                        ->options($roleOptions)
                        ->required()
                        ->searchable(),
                    TextInput::make('service_slug')
                        ->label('Slug услуги (опционально)')
                        ->maxLength(128),
                    TextInput::make('page_slug')
                        ->label('Slug страницы (опционально)')
                        ->maxLength(128),
                    TextInput::make('sort_order')
                        ->label('Порядок')
                        ->numeric()
                        ->required(),
                    Toggle::make('is_featured')->label('Featured'),
                    Toggle::make('show_on_home')->label('Показывать на главной')->nullable(),
                    Toggle::make('show_on_works')->label('Показывать на /raboty')->nullable(),
                    Toggle::make('show_on_service')->label('Показывать на посадочной услуги')->nullable(),
                ])
                ->columns(2),

            Section::make('Файл в public storage')
                ->description('Здесь хранится object key относительно `tenants/{id}/public/…`, например `site/brand/proof/wg-01.webp`. Файл должен существовать в публичном хранилище; URL на сайте соберётся автоматически.')
                ->schema([
                    TextInput::make('logical_path')
                        ->label('logical_path')
                        ->required()
                        ->maxLength(512)
                        ->columnSpanFull(),
                    TextInput::make('poster_logical_path')
                        ->label('poster_logical_path (опционально)')
                        ->maxLength(512)
                        ->columnSpanFull(),
                ])
                ->columns(1),

            Section::make('Тексты и метаданные')
                ->schema([
                    TextInput::make('title')->label('Заголовок')->maxLength(255),
                    TextInput::make('caption')->label('Подпись')->maxLength(255),
                    TextInput::make('summary')->label('Короткое описание')->maxLength(255),
                    TextInput::make('alt')->label('Alt')->maxLength(255),
                    TextInput::make('service_label')->label('Метка услуги')->maxLength(255),
                    TextInput::make('badge')->label('Бейдж')->maxLength(64),
                    TextInput::make('cta_label')->label('CTA label')->maxLength(128),
                    TextInput::make('aspect_hint')->label('Aspect hint')->maxLength(64),
                    TextInput::make('display_variant')->label('Display variant')->maxLength(64),
                    TextInput::make('before_after_group')->label('Before/After group')->maxLength(128),
                    TextInput::make('works_group')->label('Works group')->maxLength(128),
                    TextInput::make('kind')->label('Kind')->maxLength(64),
                    TextInput::make('source_ref')->label('Source ref')->maxLength(255),
                    Textarea::make('tags_json')
                        ->label('Tags (JSON array)')
                        ->rows(2)
                        ->helperText('JSON-массив строк, например: ["PPF","Антигравий"]. Пусто = без тегов.')
                        ->formatStateUsing(function ($state): string {
                            if (is_array($state)) {
                                return json_encode(array_values($state), JSON_UNESCAPED_UNICODE) ?: '[]';
                            }

                            $s = trim((string) $state);
                            return $s !== '' ? $s : '[]';
                        })
                        ->dehydrateStateUsing(function ($state): ?array {
                            $s = trim((string) $state);
                            if ($s === '') {
                                return null;
                            }
                            $d = json_decode($s, true);
                            if (! is_array($d)) {
                                return null;
                            }
                            $out = [];
                            foreach ($d as $v) {
                                $t = trim((string) $v);
                                if ($t !== '') {
                                    $out[] = $t;
                                }
                            }

                            return $out !== [] ? $out : null;
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table = AdminEmptyState::applyInitial(
            $table,
            heading: 'Каталог медиа пуст',
            description: 'Импортируйте `site/brand/media-catalog.json` в БД командой `tenant:black-duck:import-media-catalog-to-db` и обновите секции.',
            icon: 'heroicon-o-photo',
        );

        return $table->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('role')->label('Role')->badge()->sortable(),
                TextColumn::make('logical_path')
                    ->label('Path')
                    ->limit(48)
                    ->tooltip(fn (TenantMediaAsset $r): string => $r->logical_path)
                    ->url(fn (TenantMediaAsset $r): ?string => TenantPublicAssetResolver::resolveForCurrentTenant($r->logical_path))
                    ->openUrlInNewTab(),
                TextColumn::make('service_slug')->label('Service')->toggleable(),
                IconColumn::make('is_featured')->label('★')->boolean()->toggleable(),
                IconColumn::make('show_on_works')->label('/raboty')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('caption')->label('Caption')->limit(40)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(array_combine(
                        array_map(fn (BlackDuckMediaRole $r) => $r->value, BlackDuckMediaRole::cases()),
                        array_map(fn (BlackDuckMediaRole $r) => $r->value, BlackDuckMediaRole::cases()),
                    ) ?: []),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantMediaAssets::route('/'),
            'create' => Pages\CreateTenantMediaAsset::route('/create'),
            'edit' => Pages\EditTenantMediaAsset::route('/{record}/edit'),
        ];
    }
}

