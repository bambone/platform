<?php

namespace App\Filament\Tenant\Widgets;

use App\Filament\Tenant\Concerns\ResolvesTenantOnboardingBranch;
use App\Filament\Tenant\Pages\TenantSiteSetupCenterPage;
use App\TenantSiteSetup\SetupLaunchContextPresenter;
use App\TenantSiteSetup\SetupLaunchCtaSpec;
use App\TenantSiteSetup\SetupLaunchUiTrackState;
use App\TenantSiteSetup\SetupProgressService;
use App\TenantSiteSetup\SetupSessionService;
use App\TenantSiteSetup\TenantSiteSetupFeature;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SiteReadinessWidget extends Widget
{
    use ResolvesTenantOnboardingBranch;

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.tenant.widgets.site-readiness-widget';

    public static function canView(): bool
    {
        if (! TenantSiteSetupFeature::enabled()) {
            return false;
        }

        return Gate::allows('manage_settings') && currentTenant() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSummaryProperty(): ?array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return null;
        }

        return app(SetupProgressService::class)->summary($tenant);
    }

    public function getCenterUrlProperty(): ?string
    {
        return TenantSiteSetupCenterPage::getUrl();
    }

    /**
     * @return array{label: string, href: string}|null
     */
    public function getPrimaryCtaProperty(): ?array
    {
        $tenant = currentTenant();
        $user = Auth::user();
        $summary = $this->summary;
        if ($tenant === null || $user === null || $summary === null) {
            return null;
        }

        return app(SetupLaunchCtaSpec::class)->dashboardPrimary(
            $tenant,
            $user,
            $summary,
            TenantSiteSetupCenterPage::getUrl(),
        );
    }

    public function getSessionStatusLabelProperty(): string
    {
        $tenant = currentTenant();
        $user = Auth::user();
        if ($tenant === null || $user === null) {
            return '';
        }
        $svc = app(SetupSessionService::class);
        if ($svc->pausedSession($tenant, $user) !== null) {
            return 'На паузе';
        }
        if ($svc->activeSession($tenant, $user) !== null) {
            return 'Базовый запуск в процессе';
        }

        $summary = $this->summary;
        if ($summary !== null) {
            $applicable = (int) ($summary['applicable_count'] ?? 0);
            $completed = (int) ($summary['completed_count'] ?? 0);
            if ($applicable > 0 && $completed >= $applicable) {
                return 'Чеклист завершён';
            }
            $qA = (int) ($summary['quick_launch_applicable'] ?? 0);
            $qC = (int) ($summary['quick_launch_completed'] ?? 0);
            if ($qA > 0 && $qC >= $qA) {
                return 'Базовый запуск завершён';
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getNextPendingItemProperty(): ?array
    {
        $summary = $this->summary;
        if ($summary === null) {
            return null;
        }
        $items = $summary['next_pending_items'] ?? [];
        if (! is_array($items) || $items === []) {
            return null;
        }
        $first = $items[0];

        return is_array($first) ? $first : null;
    }

    public function getRemainingCountProperty(): int
    {
        $summary = $this->summary;
        if ($summary === null) {
            return 0;
        }
        $a = (int) ($summary['applicable_count'] ?? 0);
        $c = (int) ($summary['completed_count'] ?? 0);

        return max(0, $a - $c);
    }

    /**
     * Компактные строки для цели и скрытых дорожек (P1).
     *
     * @return array{primary_goal_label: string, primary_goal_hint: string, suppressed_line: string, overview_url: string}
     */
    public function getLaunchContextSummaryProperty(): array
    {
        $tenant = currentTenant();
        $user = Auth::user();
        $overview = TenantSiteSetupCenterPage::getUrl();
        if ($tenant === null || $user === null) {
            return [
                'primary_goal_label' => '',
                'primary_goal_hint' => '',
                'suppressed_line' => '',
                'overview_url' => $overview,
            ];
        }

        $ctx = app(SetupLaunchContextPresenter::class)->present($tenant, $user);
        $labels = [];
        foreach ($ctx->tracks as $row) {
            if ($row->state === SetupLaunchUiTrackState::Suppressed) {
                $labels[] = $row->label;
            }
        }

        return [
            'primary_goal_label' => $ctx->primaryGoal->label,
            'primary_goal_hint' => $ctx->primaryGoal->hint,
            'suppressed_line' => $ctx->suppressedCount > 0
                ? 'Скрытые дорожки: '.implode(', ', array_slice($labels, 0, 4)).(count($labels) > 4 ? '…' : '')
                : '',
            'overview_url' => $overview,
        ];
    }

    public function getWhatsNextHintProperty(): string
    {
        $s = $this->launchContextSummary;
        if ($s['suppressed_line'] !== '') {
            return $s['suppressed_line'];
        }

        return $s['primary_goal_hint'] !== ''
            ? $s['primary_goal_hint']
            : 'Дальше: расширенный контур и другие разделы панели.';
    }
}
