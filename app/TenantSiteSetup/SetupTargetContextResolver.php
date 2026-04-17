<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Определяет, соответствует ли текущий запрос целевому контексту шага (маршрут и «можно честно продолжить отсюда»).
 */
final class SetupTargetContextResolver
{
    /**
     * Ключ вкладки relation manager на {@see \Filament\Resources\Pages\EditRecord} с combined tabs:
     * первый менеджер из {@see \App\Filament\Tenant\Resources\PageResource::getRelations()} — индекс 0.
     *
     * @see \Filament\Resources\Pages\Concerns\HasRelationManagers::$activeRelationManager
     */
    private const HOME_PAGE_SECTIONS_RELATION_TAB = '0';

    public function __construct(
        private readonly SetupItemUrlResolver $urls,
    ) {}

    /**
     * @return array{
     *     on_target_route: bool,
     *     can_complete_here: bool,
     *     target_url: ?string,
     *     settings_tab_active: ?string,
     *     settings_tab_matches: ?bool,
     *     page_edit_relation_tab: ?string,
     *     page_edit_relation_active: ?string,
     *     page_edit_relation_matches: ?bool,
     *     target_context_mismatch: ?string,
     * }
     */
    public function resolve(Tenant $tenant, SetupItemDefinition $def, Request $request): array
    {
        $targetUrl = $this->urls->urlFor($tenant, $def);
        $currentName = $request->route()?->getName();

        $settingsTabState = $this->settingsTabState($def, $request, $currentName);
        $pageRelationTabState = $this->pageEditRelationTabState($tenant, $def, $request, $currentName);
        $onTarget = $this->matchesTargetRoute($tenant, $def, $request, $currentName);
        if ($settingsTabState['blocks_target'] || $pageRelationTabState['blocks_target']) {
            $onTarget = false;
        }
        $canComplete = $this->canCompleteHere($tenant, $def, $request, $onTarget, $currentName);

        $mismatch = $settingsTabState['mismatch_code'] ?? $pageRelationTabState['mismatch_code'];

        return [
            'on_target_route' => $onTarget,
            'can_complete_here' => $canComplete,
            'target_url' => $targetUrl,
            'settings_tab_active' => $settingsTabState['active'],
            'settings_tab_matches' => $settingsTabState['matches'],
            'page_edit_relation_tab' => $pageRelationTabState['expected'],
            'page_edit_relation_active' => $pageRelationTabState['active'],
            'page_edit_relation_matches' => $pageRelationTabState['matches'],
            'target_context_mismatch' => $mismatch,
        ];
    }

    /**
     * @return array{active: ?string, matches: ?bool, blocks_target: bool, mismatch_code: ?string}
     */
    private function settingsTabState(SetupItemDefinition $def, Request $request, ?string $currentName): array
    {
        $settingsRoute = 'filament.admin.pages.settings';
        if ($currentName !== $settingsRoute || $def->filamentRouteName !== $settingsRoute) {
            return [
                'active' => null,
                'matches' => null,
                'blocks_target' => false,
                'mismatch_code' => null,
            ];
        }

        $expected = $def->settingsTabKey;
        $active = $this->effectiveSettingsTabQuery($request);
        if ($expected === null || $expected === '') {
            return [
                'active' => $active,
                'matches' => true,
                'blocks_target' => false,
                'mismatch_code' => null,
            ];
        }

        $matches = $active === $expected;
        $mismatch = $matches ? null : 'wrong_settings_tab';

        return [
            'active' => $active,
            'matches' => $matches,
            'blocks_target' => ! $matches,
            'mismatch_code' => $mismatch,
        ];
    }

    /**
     * Filament Tabs persist `settings_tab` in query; первый таб в {@see Settings} — general.
     */
    private function effectiveSettingsTabQuery(Request $request): string
    {
        $raw = $request->query('settings_tab');
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        return 'general';
    }

    /**
     * @return array{
     *     expected: ?string,
     *     active: ?string,
     *     matches: ?bool,
     *     blocks_target: bool,
     *     mismatch_code: ?string,
     * }
     */
    private function pageEditRelationTabState(Tenant $tenant, SetupItemDefinition $def, Request $request, ?string $currentName): array
    {
        if (! $this->isHomePageBuilderItem($def->key)) {
            return [
                'expected' => null,
                'active' => null,
                'matches' => null,
                'blocks_target' => false,
                'mismatch_code' => null,
            ];
        }

        if ($currentName !== 'filament.admin.resources.pages.edit' || ! $this->isHomePageRecord($tenant, $request)) {
            return [
                'expected' => null,
                'active' => null,
                'matches' => null,
                'blocks_target' => false,
                'mismatch_code' => null,
            ];
        }

        $expected = self::HOME_PAGE_SECTIONS_RELATION_TAB;
        $active = $this->effectivePageEditRelationQuery($request);
        $matches = $active === $expected;
        $mismatch = $matches ? null : 'wrong_page_edit_relation_tab';

        return [
            'expected' => $expected,
            'active' => $active,
            'matches' => $matches,
            'blocks_target' => ! $matches,
            'mismatch_code' => $mismatch,
        ];
    }

    /**
     * Filament: {@see \Filament\Resources\Pages\Concerns\HasRelationManagers::$activeRelationManager} в query как `relation`.
     */
    private function effectivePageEditRelationQuery(Request $request): ?string
    {
        $raw = $request->query('relation');
        if ($raw === null || $raw === '') {
            return null;
        }

        return (string) $raw;
    }

    private function matchesTargetRoute(Tenant $tenant, SetupItemDefinition $def, Request $request, ?string $currentName): bool
    {
        if ($currentName === null) {
            return false;
        }

        if ($this->isProgramsListRouteStep($def->key)) {
            return in_array($currentName, $this->tenantServiceProgramRouteNames(), true);
        }

        if ($this->isHomePageBuilderItem($def->key)) {
            if ($currentName !== 'filament.admin.resources.pages.edit') {
                return false;
            }

            return $this->isHomePageRecord($tenant, $request);
        }

        if ($def->filamentRouteName !== null && Route::has($def->filamentRouteName)) {
            return $currentName === $def->filamentRouteName;
        }

        return false;
    }

    private function canCompleteHere(
        Tenant $tenant,
        SetupItemDefinition $def,
        Request $request,
        bool $onTarget,
        ?string $currentName,
    ): bool {
        if (! $onTarget) {
            return false;
        }

        if ($def->key === 'programs.first_published_program') {
            return in_array($currentName, [
                'filament.admin.resources.tenant-service-programs.create',
                'filament.admin.resources.tenant-service-programs.edit',
            ], true);
        }

        if ($def->key === 'programs.two_visible_programs') {
            return in_array($currentName, $this->tenantServiceProgramRouteNames(), true);
        }

        if ($this->isHomePageBuilderItem($def->key)) {
            return $this->isHomePageRecord($tenant, $request);
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function tenantServiceProgramRouteNames(): array
    {
        return [
            'filament.admin.resources.tenant-service-programs.index',
            'filament.admin.resources.tenant-service-programs.create',
            'filament.admin.resources.tenant-service-programs.edit',
        ];
    }

    private function isProgramsListRouteStep(string $key): bool
    {
        return $key === 'programs.first_published_program'
            || $key === 'programs.two_visible_programs';
    }

    private function isHomePageBuilderItem(string $key): bool
    {
        return $key === 'pages.home.hero_title'
            || $key === 'pages.home.hero_cta_or_contact_block';
    }

    private function isHomePageRecord(Tenant $tenant, Request $request): bool
    {
        $record = $request->route('record');
        if ($record instanceof Page) {
            return $record->slug === 'home'
                && (int) $record->tenant_id === (int) $tenant->id;
        }

        if (is_numeric($record)) {
            $page = Page::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey((int) $record)
                ->first();

            return $page !== null && $page->slug === 'home';
        }

        return false;
    }
}
