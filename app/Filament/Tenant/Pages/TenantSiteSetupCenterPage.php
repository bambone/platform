<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use App\Models\TenantSetupItemState;
use App\TenantSiteSetup\SetupApplicabilityEvaluator;
use App\TenantSiteSetup\SetupCompletionEvaluator;
use App\TenantSiteSetup\SetupItemRegistry;
use App\TenantSiteSetup\SetupItemUrlResolver;
use App\TenantSiteSetup\SetupLaunchContextPresenter;
use App\TenantSiteSetup\SetupLaunchCtaSpec;
use App\TenantSiteSetup\SetupLaunchUiGroupMapper;
use App\TenantSiteSetup\SetupProgressService;
use App\TenantSiteSetup\SetupSessionService;
use App\TenantSiteSetup\SetupValueSnapshotResolver;
use App\TenantSiteSetup\TenantSiteSetupFeature;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class TenantSiteSetupCenterPage extends Page
{
    protected static ?string $navigationLabel = 'Обзор запуска';

    protected static string|UnitEnum|null $navigationGroup = 'SiteLaunch';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $title = 'Обзор запуска';

    protected static ?string $slug = 'site-setup';

    protected string $view = 'filament.tenant.pages.tenant-site-setup-center';

    public function mount(): void
    {
        abort_unless(Gate::allows('manage_settings'), 403);
        abort_if(currentTenant() === null, 404, 'Tenant not found.');

        if (request()->boolean('start_guided') && TenantSiteSetupFeature::enabled()) {
            $tenant = currentTenant();
            $user = Auth::user();
            if ($tenant !== null && $user !== null) {
                $sessions = app(SetupSessionService::class);
                $sessions->startOrResume($tenant, $user);
                $this->redirect($sessions->redirectUrlAfterGuidedEntry($tenant, $user, static::getUrl()));

                return;
            }
            $this->redirect(static::getUrl());
        }
    }

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
                'siteSetupCenterWhatIs',
                [
                    'Чеклист запуска сайта: статусы шагов и ссылки в кабинет.',
                    '',
                    '«Начать запуск» открывает гид.',
                    '«Новая очередь» сбрасывает сессию и строит шаги заново.',
                ],
                'Справка по обзору запуска',
            ),
            Action::make('startGuided')
                ->label(fn (): string => $this->launchPrimaryCta['label'])
                ->icon('heroicon-o-play')
                ->action(function (): void {
                    $tenant = currentTenant();
                    $user = Auth::user();
                    if ($tenant === null || $user === null) {
                        return;
                    }
                    $sessions = app(SetupSessionService::class);
                    $sessions->startOrResume($tenant, $user);
                    $this->redirect($sessions->redirectUrlAfterGuidedEntry($tenant, $user, static::getUrl()));
                })
                ->visible(fn (): bool => TenantSiteSetupFeature::enabled()),
            Action::make('startFreshGuided')
                ->label('Новая очередь')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Начать очередь шагов заново?')
                ->modalDescription('Текущая сессия на паузе будет сброшена, очередь шагов пересчитается с нуля. После подтверждения откроется первый шаг новой очереди (как при «Начать запуск»).')
                ->action(function (): void {
                    $tenant = currentTenant();
                    $user = Auth::user();
                    if ($tenant === null || $user === null) {
                        return;
                    }
                    $sessions = app(SetupSessionService::class);
                    $sessions->startFreshGuidedSession($tenant, $user);
                    $this->redirect($sessions->redirectUrlAfterGuidedEntry($tenant, $user, static::getUrl()));
                })
                ->visible(fn (): bool => TenantSiteSetupFeature::enabled() && $this->hasPausedSession),
        ];
    }

    public function getHasPausedSessionProperty(): bool
    {
        $tenant = currentTenant();
        $user = Auth::user();
        if ($tenant === null || $user === null) {
            return false;
        }

        return app(SetupSessionService::class)->pausedSession($tenant, $user) !== null;
    }

    public function getHasActiveSessionProperty(): bool
    {
        $tenant = currentTenant();
        $user = Auth::user();
        if ($tenant === null || $user === null) {
            return false;
        }

        return app(SetupSessionService::class)->activeSession($tenant, $user) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getNextPendingStepProperty(): ?array
    {
        $next = $this->summary['next_pending_items'] ?? [];
        if (! is_array($next) || $next === []) {
            return null;
        }

        $first = $next[0];

        return is_array($first) ? $first : null;
    }

    /**
     * Primary CTA для верхнего блока (согласовано с виджетом дашборда).
     *
     * @return array{label: string, href: string}
     */
    public function getLaunchPrimaryCtaProperty(): array
    {
        $tenant = currentTenant();
        $user = Auth::user();
        $overview = static::getUrl();
        if ($tenant === null || $user === null) {
            return ['label' => 'Открыть обзор запуска', 'href' => $overview];
        }

        return app(SetupLaunchCtaSpec::class)->dashboardPrimary($tenant, $user, $this->summary, $overview);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummaryProperty(): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }

        return app(SetupProgressService::class)->summary($tenant);
    }

    /**
     * Дорожки, цель сайта и пояснения для секции «Запуск» (P1).
     *
     * @return array<string, mixed>
     */
    public function getLaunchContextProperty(): array
    {
        $tenant = currentTenant();
        $user = Auth::user();
        if ($tenant === null || $user === null) {
            return [];
        }

        return app(SetupLaunchContextPresenter::class)->present($tenant, $user)->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCategoryRowsProperty(): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }

        $defs = SetupItemRegistry::definitions();
        $snap = app(SetupValueSnapshotResolver::class);
        $rows = [];
        foreach ($defs as $key => $def) {
            $state = TenantSetupItemState::query()
                ->where('tenant_id', $tenant->id)
                ->where('item_key', $key)
                ->first();
            $exec = $state?->current_status ?? 'pending';
            $executionLabel = match ($exec) {
                'snoozed' => 'Отложено',
                'not_needed' => 'Не требуется',
                'completed' => 'Выполнено',
                default => 'Ожидание',
            };
            $rows[] = [
                'key' => $key,
                'category' => $def->categoryKey,
                'title' => $def->title,
                'snapshot' => $snap->snapshot($tenant, $def),
                'execution_status' => $exec,
                'execution_label' => $executionLabel,
                'can_restore' => in_array($exec, ['snoozed', 'not_needed'], true),
                'url' => app(SetupItemUrlResolver::class)->urlFor($tenant, $def),
            ];
        }

        return $rows;
    }

    /**
     * Секции для карточек «Базовый запуск» / «Контент» / «Коммуникация».
     *
     * @return list<array{key: string, label: string, items: list<array<string, mixed>>}>
     */
    public function getUiGroupSectionsProperty(): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }

        $applicability = app(SetupApplicabilityEvaluator::class);
        $completion = app(SetupCompletionEvaluator::class);
        $urls = app(SetupItemUrlResolver::class);
        $snap = app(SetupValueSnapshotResolver::class);
        $defs = SetupItemRegistry::definitions();

        $sections = [];
        foreach (SetupLaunchUiGroupMapper::orderedUiGroups() as $ug) {
            $sections[$ug] = [
                'key' => $ug,
                'label' => SetupLaunchUiGroupMapper::uiGroupLabel($ug),
                'items' => [],
            ];
        }

        foreach ($defs as $key => $def) {
            if ($applicability->evaluateItem($tenant, $def, Auth::user()) !== 'applicable') {
                continue;
            }

            $ug = SetupLaunchUiGroupMapper::uiGroupForItemKey($key);
            if (! isset($sections[$ug])) {
                continue;
            }

            $state = TenantSetupItemState::query()
                ->where('tenant_id', $tenant->id)
                ->where('item_key', $key)
                ->first();
            $exec = $state?->current_status ?? 'pending';
            $executionLabel = match ($exec) {
                'snoozed' => 'Отложено',
                'not_needed' => 'Не требуется',
                'completed' => 'Выполнено',
                default => 'Ожидание',
            };
            $isDone = $completion->isComplete($tenant, $def)
                || $exec === 'completed'
                || ($exec === 'not_needed' && $def->notNeededAllowed);

            $sections[$ug]['items'][] = [
                'key' => $key,
                'title' => $def->title,
                'description' => $def->description,
                'snapshot' => $snap->snapshot($tenant, $def),
                'execution_label' => $executionLabel,
                'is_done' => $isDone,
                'url' => $urls->urlFor($tenant, $def),
            ];
        }

        return array_values($sections);
    }
}
