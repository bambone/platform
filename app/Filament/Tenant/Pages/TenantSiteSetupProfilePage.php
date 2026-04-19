<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Concerns\ResolvesTenantOnboardingBranch;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use App\TenantSiteSetup\SetupProfileRepository;
use App\TenantSiteSetup\TenantOnboardingBranchId;
use App\TenantSiteSetup\TenantSiteSetupFeature;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class TenantSiteSetupProfilePage extends Page
{
    use ResolvesTenantOnboardingBranch;

    protected static string|UnitEnum|null $navigationGroup = 'SiteLaunch';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Профиль сайта';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $title = 'Профиль сайта';

    protected static ?string $slug = 'site-setup-profile';

    protected string $view = 'filament.tenant.pages.tenant-site-setup-profile';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        if (! TenantSiteSetupFeature::enabled()) {
            return false;
        }

        return Gate::allows('manage_settings') && currentTenant() !== null;
    }

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'siteSetupProfileWhatIs',
                [
                    'Профиль запуска задаёт приоритеты чеклиста и подсказок «Быстрого запуска».',
                    '',
                    'На публичный сайт и тексты страниц эти поля не выводятся.',
                ],
                'Справка по профилю сайта',
            ),
        ];
    }

    public function mount(): void
    {
        abort_unless(Gate::allows('manage_settings'), 403);
        $tenant = currentTenant();
        abort_if($tenant === null, 404);

        $this->data = app(SetupProfileRepository::class)->getMerged($tenant->id);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Профиль сайта')
                    ->description(
                        'Здесь задаётся контекст запуска: что вы хотите от сайта в первую очередь. '
                        .'Это помогает кабинету расставить приоритеты в «Быстром запуске» (порядок шагов чеклиста и подсказки по дорожкам на странице запуска). '
                        .'На публичный сайт, тексты страниц и контакты эти поля не выводятся и их не видят посетители.'
                    )
                    ->schema([
                        ViewField::make('onboarding_branch_alert')
                            ->hiddenLabel()
                            ->view('filament.tenant.forms.onboarding-branch-alert')
                            ->viewData(fn (): array => [
                                'resolution' => $this->branchResolution,
                            ]),
                        Select::make('desired_branch')
                            ->label('Сценарий онбординга (ветка)')
                            ->helperText(
                                'MVP: заявки и CRM, запись по слотам или оба сценария. Пустое значение — как в поле «Главная цель» (цель «Запись» → ветка записи). '
                                .'Если выбрана запись, но модуль расписания не включён для аккаунта, покажем предупреждение и фактически ведём сценарий без автоматизации слотов.'
                            )
                            ->native(true)
                            ->nullable()
                            ->placeholder('— как в главной цели —')
                            ->options(collect(TenantOnboardingBranchId::cases())->mapWithKeys(
                                fn (TenantOnboardingBranchId $b) => [$b->value => $b->label()],
                            )->all()),
                        Select::make('business_focus')
                            ->label('Фокус бизнеса')
                            ->helperText('Опционально: тип деятельности для справки в кабинете. На порядок шагов и на содержимое сайта сейчас не влияет.')
                            ->native(true)
                            ->nullable()
                            ->placeholder('— не выбрано —')
                            ->options([
                                'services' => 'Услуги / консультации',
                                'education' => 'Обучение / программы',
                                'retail' => 'Товары / каталог',
                                'mixed' => 'Смешанная модель',
                            ]),
                        Select::make('primary_goal')
                            ->label('Главная цель сайта сейчас')
                            ->helperText('От выбора зависят приоритет шагов в чеклисте быстрого запуска и рекомендованные дорожки на странице запуска.')
                            ->native(true)
                            ->nullable()
                            ->placeholder('— не выбрано —')
                            ->options([
                                'leads' => 'Заявки и звонки',
                                'info' => 'Информация и доверие',
                                'booking' => 'Запись / бронирование',
                                'catalog' => 'Показать каталог / программы',
                            ]),
                        Textarea::make('additional_notes')
                            ->label('Комментарий для команды')
                            ->helperText('Заметка только для вашей команды в кабинете (поддержка, согласования). Не публикуется на сайте.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        abort_unless(Gate::allows('manage_settings'), 403);
        $tenant = currentTenant();
        abort_if($tenant === null, 404);

        $state = $this->getSchema('form')->getState();
        $repo = app(SetupProfileRepository::class);
        $merged = array_merge($repo->getMerged($tenant->id), $state);
        $merged['schema_version'] = $repo->schemaVersion();
        $repo->save($tenant->id, $merged);

        Notification::make()
            ->title('Профиль сайта сохранён')
            ->success()
            ->send();
    }
}
