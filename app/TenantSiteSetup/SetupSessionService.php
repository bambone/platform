<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\TenantSetupSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SetupSessionService
{
    public function __construct(
        private readonly JourneyVersion $journeyVersion,
        private readonly SetupProfileRepository $profiles,
        private readonly SetupJourneyBuilder $journeyBuilder,
        private readonly SetupItemStateService $itemStates,
        private readonly PageBuilderSetupTargetResolver $pageBuilderHints,
        private readonly SetupTargetContextResolver $targetContext,
    ) {}

    public function startOrResume(Tenant $tenant, User $user): TenantSetupSession
    {
        Gate::authorize('manage_settings');
        $paused = $this->pausedSession($tenant, $user);
        if ($paused !== null) {
            return $this->resumePausedSession($tenant, $user, $paused);
        }

        $active = $this->activeSession($tenant, $user);
        if ($active !== null) {
            $version = $this->journeyVersion->compute($tenant, $this->profiles);
            if ($active->journey_version !== $version) {
                $active->update([
                    'journey_version' => $version,
                    'visible_step_keys_json' => $this->journeyBuilder->visibleStepKeys($tenant, $user),
                ]);
                $active->refresh();
            }

            return $active;
        }

        return $this->createNewSession($tenant, $user);
    }

    /**
     * Abandon active/paused guided sessions and start a new queue from scratch.
     */
    public function startFreshGuidedSession(Tenant $tenant, User $user): TenantSetupSession
    {
        Gate::authorize('manage_settings');
        DB::transaction(function () use ($tenant, $user): void {
            TenantSetupSession::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->whereIn('session_status', ['active', 'paused'])
                ->update(['session_status' => 'abandoned']);
        });

        return $this->createNewSession($tenant, $user);
    }

    public function pausedSession(Tenant $tenant, User $user): ?TenantSetupSession
    {
        return TenantSetupSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('session_status', 'paused')
            ->orderByDesc('id')
            ->first();
    }

    private function createNewSession(Tenant $tenant, User $user): TenantSetupSession
    {
        $version = $this->journeyVersion->compute($tenant, $this->profiles);
        $keys = $this->journeyBuilder->visibleStepKeys($tenant, $user);

        return DB::transaction(function () use ($tenant, $user, $version, $keys): TenantSetupSession {
            TenantSetupSession::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->whereIn('session_status', ['active', 'paused'])
                ->update(['session_status' => 'abandoned']);

            $first = $keys[0] ?? null;
            $def = $first !== null ? SetupItemRegistry::definitions()[$first] ?? null : null;

            return TenantSetupSession::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'session_status' => 'active',
                'current_item_key' => $first,
                'current_route_name' => $def?->filamentRouteName,
                'journey_version' => $version,
                'step_index' => 0,
                'visible_step_keys_json' => $keys,
                'meta_json' => [],
                'started_at' => now(),
            ]);
        });
    }

    private function resumePausedSession(Tenant $tenant, User $user, TenantSetupSession $paused): TenantSetupSession
    {
        $version = $this->journeyVersion->compute($tenant, $this->profiles);
        $keys = $this->journeyBuilder->visibleStepKeys($tenant, $user);

        return DB::transaction(function () use ($tenant, $user, $paused, $version, $keys): TenantSetupSession {
            TenantSetupSession::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('session_status', 'active')
                ->where('id', '!=', $paused->id)
                ->update(['session_status' => 'abandoned']);

            $paused->update([
                'session_status' => 'active',
                'paused_at' => null,
                'journey_version' => $version,
                'visible_step_keys_json' => $keys,
            ]);
            $paused->refresh();
            $this->realignCurrentPointerAfterResume($paused, $keys);

            return $paused->fresh();
        });
    }

    /**
     * @param  list<string>  $keys
     */
    private function realignCurrentPointerAfterResume(TenantSetupSession $session, array $keys): void
    {
        if ($keys === []) {
            $this->completeSession($session);

            return;
        }

        $defs = SetupItemRegistry::definitions();
        $current = $session->current_item_key;
        if ($current !== null && in_array($current, $keys, true)) {
            $idx = array_search($current, $keys, true);
            $def = $defs[$current] ?? null;
            $session->update([
                'step_index' => $idx === false ? 0 : (int) $idx,
                'current_route_name' => $def?->filamentRouteName,
            ]);

            return;
        }

        $first = $keys[0];
        $def = $defs[$first] ?? null;
        $session->update([
            'current_item_key' => $first,
            'step_index' => 0,
            'current_route_name' => $def?->filamentRouteName,
        ]);
    }

    public function pause(Tenant $tenant, User $user): void
    {
        Gate::authorize('manage_settings');
        TenantSetupSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('session_status', 'active')
            ->update([
                'session_status' => 'paused',
                'paused_at' => now(),
            ]);
    }

    /**
     * Следующий видимый шаг в guided-сессии **без** записи статуса по текущему пункту в {@see TenantSetupItemState}.
     * Одна и та же операция для кнопок «Дальше» (когда можно завершить шаг на месте) и «Пропустить шаг» (временно
     * сдвинуть очередь). Явные решения по пункту — через {@see snoozeCurrentAndAdvance} и {@see markNotNeededCurrentAndAdvance}.
     */
    /**
     * @return bool true если очередь guided исчерпана и сессия завершена
     */
    public function advanceToNext(Tenant $tenant, User $user): bool
    {
        Gate::authorize('manage_settings');
        $session = $this->requireActiveSession($tenant, $user);
        $defs = SetupItemRegistry::definitions();
        $keys = $this->journeyBuilder->visibleStepKeys($tenant, $user);
        $session->update(['visible_step_keys_json' => $keys]);

        $current = $session->current_item_key;
        $idx = $current !== null ? array_search($current, $keys, true) : false;
        if ($idx === false) {
            $nextKey = $keys[0] ?? null;
            $nextIdx = 0;
        } else {
            $nextKey = $keys[(int) $idx + 1] ?? null;
            $nextIdx = (int) $idx + 1;
        }

        if ($nextKey === null) {
            $this->completeSession($session);

            return true;
        }

        $nextDef = $defs[$nextKey] ?? null;
        $session->update([
            'current_item_key' => $nextKey,
            'step_index' => $nextIdx,
            'current_route_name' => $nextDef?->filamentRouteName,
        ]);

        return false;
    }

    public function snoozeCurrentAndAdvance(Tenant $tenant, User $user): void
    {
        Gate::authorize('manage_settings');
        $session = $this->requireActiveSession($tenant, $user);
        $currentKey = $session->current_item_key;
        if ($currentKey === null) {
            abort(422, 'Нет текущего шага.');
        }

        $this->itemStates->markSnoozed($tenant, $user, $currentKey, 'guided_snooze', null);
        $this->repositionAfterUserChoice($tenant, $user, $session, $currentKey);
    }

    public function markNotNeededCurrentAndAdvance(Tenant $tenant, User $user): void
    {
        Gate::authorize('manage_settings');
        $session = $this->requireActiveSession($tenant, $user);
        $currentKey = $session->current_item_key;
        if ($currentKey === null) {
            abort(422, 'Нет текущего шага.');
        }

        $this->itemStates->markNotNeeded($tenant, $user, $currentKey, 'guided_not_needed', null);
        $this->repositionAfterUserChoice($tenant, $user, $session, $currentKey);
    }

    /**
     * @param  list<string>  $keys
     */
    private function repositionAfterUserChoice(Tenant $tenant, User $user, TenantSetupSession $session, string $previousKey): void
    {
        $defs = SetupItemRegistry::definitions();
        $keys = $this->journeyBuilder->visibleStepKeys($tenant, $user);
        $session->update(['visible_step_keys_json' => $keys]);

        if ($keys === []) {
            $this->completeSession($session);

            return;
        }

        $nextKey = $this->pickNextGuidedKey($previousKey, $keys);
        if ($nextKey === null) {
            $this->completeSession($session);

            return;
        }

        $idx = array_search($nextKey, $keys, true);
        $def = $defs[$nextKey] ?? null;
        $session->update([
            'current_item_key' => $nextKey,
            'step_index' => $idx === false ? 0 : (int) $idx,
            'current_route_name' => $def?->filamentRouteName,
        ]);
    }

    /**
     * @param  list<string>  $keys
     */
    private function pickNextGuidedKey(string $previousKey, array $keys): ?string
    {
        if ($keys === []) {
            return null;
        }

        $defs = SetupItemRegistry::definitions();
        $prevOrder = $defs[$previousKey]->sortOrder ?? -1;

        $withHigher = array_values(array_filter(
            $keys,
            fn (string $k): bool => ($defs[$k]->sortOrder ?? 999) > $prevOrder,
        ));
        usort(
            $withHigher,
            fn (string $a, string $b): int => ($defs[$a]->sortOrder ?? 0) <=> ($defs[$b]->sortOrder ?? 0),
        );
        if ($withHigher !== []) {
            return $withHigher[0];
        }

        $sorted = $keys;
        usort(
            $sorted,
            fn (string $a, string $b): int => ($defs[$a]->sortOrder ?? 0) <=> ($defs[$b]->sortOrder ?? 0),
        );

        return $sorted[0] ?? null;
    }

    /**
     * Если текущий шаг исчез из очереди (например, данные закрыли пункт без «Дальше»),
     * сдвигаем указатель или завершаем сессию — иначе overlay теряет консистентность.
     */
    private function realignSessionIfCurrentStepNoLongerInJourney(Tenant $tenant, User $user, TenantSetupSession $session): void
    {
        $freshKeys = $this->journeyBuilder->visibleStepKeys($tenant, $user);
        $session->update(['visible_step_keys_json' => $freshKeys]);

        $currentKey = $session->current_item_key;
        if ($currentKey === null) {
            return;
        }
        if (in_array($currentKey, $freshKeys, true)) {
            return;
        }

        if ($freshKeys === []) {
            $this->completeSession($session);

            return;
        }

        $nextKey = $this->pickNextGuidedKey($currentKey, $freshKeys);
        if ($nextKey === null) {
            $this->completeSession($session);

            return;
        }

        $defs = SetupItemRegistry::definitions();
        $def = $defs[$nextKey] ?? null;
        $idx = array_search($nextKey, $freshKeys, true);
        $session->update([
            'current_item_key' => $nextKey,
            'step_index' => $idx === false ? 0 : (int) $idx,
            'current_route_name' => $def?->filamentRouteName,
        ]);
    }

    private function requireActiveSession(Tenant $tenant, User $user): TenantSetupSession
    {
        $session = $this->activeSession($tenant, $user);
        if ($session === null) {
            abort(404, 'Активная сессия мастера не найдена.');
        }

        return $session;
    }

    private function completeSession(TenantSetupSession $session): void
    {
        $session->update([
            'session_status' => 'completed',
            'completed_at' => now(),
            'current_item_key' => null,
            'current_route_name' => null,
        ]);
    }

    public function activeSession(Tenant $tenant, User $user): ?TenantSetupSession
    {
        return TenantSetupSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('session_status', 'active')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function overlayPayload(?Tenant $tenant, ?User $user, ?Request $request = null): ?array
    {
        if (! TenantSiteSetupFeature::enabled() || $tenant === null || $user === null) {
            return null;
        }

        $session = $this->activeSession($tenant, $user);
        if ($session === null) {
            return null;
        }

        $version = $this->journeyVersion->compute($tenant, $this->profiles);
        if ($session->journey_version !== $version) {
            $session->update([
                'journey_version' => $version,
                'visible_step_keys_json' => $this->journeyBuilder->visibleStepKeys($tenant, $user),
            ]);
            $session->refresh();
        }

        $this->realignSessionIfCurrentStepNoLongerInJourney($tenant, $user, $session);
        $session->refresh();

        if ($session->session_status !== 'active') {
            return null;
        }

        $keys = $session->visible_step_keys_json ?? [];
        $currentKey = $session->current_item_key;
        $defs = SetupItemRegistry::definitions();
        $def = $currentKey !== null && isset($defs[$currentKey]) ? $defs[$currentKey] : null;
        $hints = $def !== null ? $this->pageBuilderHints->overlayHints($def) : [
            'target_fallback_keys' => [],
            'page_builder_fallback_section_types' => [],
            'fallback_setup_action' => null,
        ];

        $sessionUrl = route('filament.admin.tenant-site-setup.session');

        $request ??= request();
        $ctx = $def !== null
            ? $this->targetContext->resolve($tenant, $def, $request)
            : [
                'on_target_route' => false,
                'can_complete_here' => false,
                'target_url' => null,
                'settings_tab_active' => null,
                'settings_tab_matches' => null,
                'page_edit_relation_tab' => null,
                'page_edit_relation_active' => null,
                'page_edit_relation_matches' => null,
                'target_context_mismatch' => null,
            ];

        $targetUrl = $ctx['target_url'];
        $primaryIsTargetNavigation = ! $ctx['can_complete_here']
            && is_string($targetUrl)
            && $targetUrl !== '';

        $pageBuilderAutoOpen = $this->pageBuilderAutoOpenConfig($tenant, $def);

        $payload = [
            'session_id' => $session->id,
            'current_item_key' => $currentKey,
            'current_title' => $def?->title,
            'target_item_key' => $currentKey,
            'target_title' => $def?->title,
            'target_url' => $targetUrl,
            'on_target_route' => $ctx['on_target_route'],
            'can_complete_here' => $ctx['can_complete_here'],
            'primary_is_target_navigation' => $primaryIsTargetNavigation,
            'step_index' => (int) $session->step_index,
            'steps_total' => max(1, count($keys)),
            'target_key' => $def?->targetKey,
            'target_fallback_keys' => $hints['target_fallback_keys'],
            'page_builder_fallback_section_types' => $hints['page_builder_fallback_section_types'],
            'fallback_setup_action' => $hints['fallback_setup_action'],
            'route_name' => $def?->filamentRouteName,
            'session_action_url' => $sessionUrl,
            'can_snooze' => $def?->skipAllowed ?? false,
            'can_not_needed' => (bool) ($def?->notNeededAllowed),
            'launch_critical' => $def?->launchCritical ?? false,
            'settings_tab' => $def?->settingsTabKey,
            'settings_section_id' => $def?->settingsSectionId,
            'readiness_tier' => $def?->readinessTier?->value,
            'guided_next_hint' => $def !== null ? $def->guidedNextHint->value : 'save_then_next',
            'guided_inline_placement' => ($ctx['on_target_route'] ?? false)
                && in_array(
                    (string) ($def?->filamentRouteName ?? ''),
                    [
                        'filament.admin.pages.settings',
                        'filament.admin.pages.site-setup-booking-notifications',
                    ],
                    true,
                )
                ? 'floating'
                : 'inline',
            'settings_tab_active' => $ctx['settings_tab_active'] ?? null,
            'settings_tab_matches' => $ctx['settings_tab_matches'] ?? null,
            'page_edit_relation_tab' => $ctx['page_edit_relation_tab'] ?? null,
            'page_edit_relation_active' => $ctx['page_edit_relation_active'] ?? null,
            'page_edit_relation_matches' => $ctx['page_edit_relation_matches'] ?? null,
            'target_context_mismatch' => $ctx['target_context_mismatch'] ?? null,
            'page_builder_auto_open' => $pageBuilderAutoOpen,
        ];

        if (config('app.debug') && config('features.tenant_site_setup_guided_debug')) {
            $snap = SetupCapabilitySnapshot::capture($tenant, $user);
            $tracks = app(SetupTracksResolver::class)->resolve(
                $tenant,
                $user,
                $this->profiles->getMerged((int) $tenant->id),
                $snap,
            );
            $payload['guided_dev_debug'] = [
                'current_item_key' => $currentKey,
                'page_builder_auto_open' => $pageBuilderAutoOpen,
                'route_name' => $def?->filamentRouteName,
                'settings_tab_expected' => $def?->settingsTabKey,
                'settings_tab_active' => $ctx['settings_tab_active'] ?? null,
                'settings_tab_matches' => $ctx['settings_tab_matches'] ?? null,
                'page_edit_relation_tab' => $ctx['page_edit_relation_tab'] ?? null,
                'page_edit_relation_active' => $ctx['page_edit_relation_active'] ?? null,
                'page_edit_relation_matches' => $ctx['page_edit_relation_matches'] ?? null,
                'target_context_mismatch' => $ctx['target_context_mismatch'] ?? null,
                'on_target_route' => $ctx['on_target_route'],
                'can_complete_here' => $ctx['can_complete_here'],
                'target_key' => $def?->targetKey,
                'has_visible_program' => $snap->hasVisibleServiceProgram,
                'active_tracks' => $tracks->activeTracks,
                'suppressed_tracks' => $tracks->suppressedTracks,
            ];
        }

        return $payload;
    }

    /**
     * Контракт авто-открытия редактора page builder: только для шага заголовка hero (поле в slide-over).
     * Шаг CTA/контактов не открывает hero-редактор — см. {@see pageBuilderAutoOpenConfig}.
     *
     * @return array{
     *     enabled: bool,
     *     section_id: int|null,
     *     reason: string|null,
     *     expected_primary_target_kind: string|null,
     *     prefer_primary_target_ms: int,
     *     max_auto_open_attempts: int,
     * }
     */
    private function pageBuilderAutoOpenConfig(Tenant $tenant, ?SetupItemDefinition $def): array
    {
        $defaults = [
            'enabled' => false,
            'section_id' => null,
            'reason' => null,
            'expected_primary_target_kind' => null,
            'prefer_primary_target_ms' => 1600,
            'max_auto_open_attempts' => 5,
        ];

        if ($def === null || $def->key !== 'pages.home.hero_title') {
            return $defaults;
        }

        $page = Page::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'home')
            ->first();

        if ($page === null) {
            return $defaults;
        }

        $section = PageSection::query()
            ->where('page_id', $page->id)
            ->where(function ($q): void {
                $q->where('section_type', 'hero')->orWhere('section_key', 'hero');
            })
            ->orderBy('sort_order')
            ->first();

        if ($section === null) {
            return $defaults;
        }

        return [
            'enabled' => true,
            'section_id' => (int) $section->id,
            'reason' => 'hero_editor_for_primary_field',
            'expected_primary_target_kind' => 'hero_title_field',
            'prefer_primary_target_ms' => 1600,
            'max_auto_open_attempts' => 5,
        ];
    }

    /**
     * После «Начать/Продолжить запуск» ведём на экран текущего шага (если известен URL), иначе — на обзор.
     */
    public function redirectUrlAfterGuidedEntry(Tenant $tenant, User $user, string $overviewUrl): string
    {
        $session = $this->activeSession($tenant, $user);
        if ($session === null) {
            return $overviewUrl;
        }

        $key = $session->current_item_key;
        if ($key === null) {
            return $overviewUrl;
        }

        $defs = SetupItemRegistry::definitions();
        $def = $defs[$key] ?? null;
        if ($def === null) {
            return $overviewUrl;
        }

        $url = app(SetupItemUrlResolver::class)->urlFor($tenant, $def);

        return is_string($url) && $url !== '' ? $url : $overviewUrl;
    }
}
