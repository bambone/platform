<?php

namespace App\Filament\Tenant\Pages;

use App\Models\DomainTerm;
use App\Models\TenantTermOverride;
use App\Terminology\TenantTerminologyGuard;
use App\Terminology\TenantTerminologyService;
use App\Terminology\TerminologyHumanizer;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class TerminologySettings extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Терминология';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static ?string $title = 'Терминология и названия';

    protected static ?string $slug = 'terminology';

    protected static ?int $navigationSort = 30;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected string $view = 'filament-panels::pages.page';

    public static function canAccess(): bool
    {
        return Gate::allows('manage_terminology');
    }

    public function mount(): void
    {
        abort_unless(Gate::allows('manage_terminology'), 403);
        abort_if(currentTenant() === null, 403);

        $this->mountInteractsWithTable();
        $this->bootedInteractsWithTable();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $t = currentTenant();
        if ($t === null) {
            return null;
        }
        $name = $t->domainLocalizationPreset?->name;

        return $name !== null && $name !== ''
            ? 'Текущий пресет: '.$name.'. Ниже — отображаемые названия; системные ключи в коде не меняются.'
            : 'Пресет терминологии не назначен — используются только значения по умолчанию и ваши переименования.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return DomainTerm::query()
            ->where('is_active', true)
            ->orderBy('group')
            ->orderBy('term_key');
    }

    /**
     * @return array<string, array{label: string, short_label: ?string, source: string}>
     */
    private function dictionary(): array
    {
        $t = currentTenant();
        if ($t === null) {
            return [];
        }

        return app(TenantTerminologyService::class)->dictionary($t);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('term_key')
                    ->label('Ключ')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group')
                    ->label('Группа')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('resolved_label')
                    ->label('Подпись')
                    ->getStateUsing(function (DomainTerm $record): string {
                        $d = $this->dictionary();

                        return $d[$record->term_key]['label']
                            ?? ($record->default_label !== null && $record->default_label !== '' ? $record->default_label : TerminologyHumanizer::humanize($record->term_key));
                    }),
                TextColumn::make('source')
                    ->label('Источник')
                    ->badge()
                    ->getStateUsing(function (DomainTerm $record): string {
                        return $this->dictionary()[$record->term_key]['source'] ?? 'default';
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'override' => 'Своя',
                        'preset' => 'Пресет',
                        'fallback' => 'Автоподпись',
                        'default' => 'Система',
                        default => 'Система',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'override' => 'warning',
                        'preset' => 'info',
                        'fallback' => 'danger',
                        'default' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('Группа')
                    ->options(fn (): array => DomainTerm::query()
                        ->where('is_active', true)
                        ->distinct()
                        ->orderBy('group')
                        ->pluck('group', 'group')
                        ->all()),
            ])
            ->actions([
                Action::make('editLabel')
                    ->label('Изменить')
                    ->visible(fn (DomainTerm $record): bool => $record->is_editable_by_tenant)
                    ->form([
                        TextInput::make('label')
                            ->label('Подпись')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('short_label')
                            ->label('Короткая подпись')
                            ->maxLength(255),
                    ])
                    ->fillForm(function (DomainTerm $record): array {
                        $d = $this->dictionary();

                        return [
                            'label' => $d[$record->term_key]['label'] ?? $record->default_label,
                            'short_label' => $d[$record->term_key]['short_label'] ?? '',
                        ];
                    })
                    ->action(function (array $data, DomainTerm $record): void {
                        TenantTerminologyGuard::assertTermEditableByTenant($record);
                        $tenant = currentTenant();
                        abort_if($tenant === null, 403);
                        TenantTermOverride::query()->updateOrCreate(
                            [
                                'tenant_id' => $tenant->id,
                                'term_id' => $record->id,
                            ],
                            [
                                'label' => $data['label'],
                                'short_label' => filled($data['short_label'] ?? null) ? $data['short_label'] : null,
                                'source' => 'manual',
                            ]
                        );
                        Notification::make()->title('Подпись сохранена')->success()->send();
                    }),
                Action::make('resetOne')
                    ->label('Сброс')
                    ->color('gray')
                    ->visible(fn (DomainTerm $record): bool => $record->is_editable_by_tenant)
                    ->requiresConfirmation()
                    ->modalHeading('Сбросить переименование?')
                    ->modalDescription('Будет снова использоваться значение из пресета или системы.')
                    ->action(function (DomainTerm $record): void {
                        TenantTerminologyGuard::assertTermEditableByTenant($record);
                        $tenant = currentTenant();
                        abort_if($tenant === null, 403);
                        TenantTermOverride::query()
                            ->where('tenant_id', $tenant->id)
                            ->where('term_id', $record->id)
                            ->delete();
                        app(TenantTerminologyService::class)->forgetTenant($tenant->id);
                        Notification::make()->title('Сброшено')->success()->send();
                    }),
            ])
            ->headerActions([
                Action::make('resetAll')
                    ->label('Сбросить все переименования')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Сбросить все свои подписи?')
                    ->modalDescription('Все переименования tenant будут удалены; останутся пресет и системные значения.')
                    ->action(function (): void {
                        $tenant = currentTenant();
                        abort_if($tenant === null, 403);
                        TenantTermOverride::query()->where('tenant_id', $tenant->id)->delete();
                        app(TenantTerminologyService::class)->forgetTenant($tenant->id);
                        Notification::make()->title('Все переименования сброшены')->success()->send();
                    }),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }
}
