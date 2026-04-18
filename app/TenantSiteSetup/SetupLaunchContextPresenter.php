<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\Tenant;
use App\Models\User;

/**
 * Собирает {@see SetupLaunchContext} для UI без изменения {@see SetupTracksResolver}.
 */
final class SetupLaunchContextPresenter
{
    public function __construct(
        private readonly SetupTracksResolver $tracksResolver,
        private readonly SetupProfileRepository $profiles,
        private readonly SetupTrackReasonCatalog $reasonCatalog,
        private readonly SetupTrackApplicabilitySummaryService $applicabilitySummary,
        private readonly SetupPrimaryGoalPresenter $primaryGoalPresenter,
    ) {}

    public function present(Tenant $tenant, ?User $user): SetupLaunchContext
    {
        $profile = $this->profiles->getMerged((int) $tenant->id);
        $snapshot = SetupCapabilitySnapshot::capture($tenant, $user);
        $resolved = $this->tracksResolver->resolve($tenant, $user, $profile, $snapshot);
        $metricsByTrack = $this->applicabilitySummary->summarize($tenant, $user);
        $goal = $this->primaryGoalPresenter->present((int) $tenant->id);

        $activeSet = array_flip($resolved->activeTracks);
        $suppressed = $resolved->suppressedTracks;

        $rows = [];
        foreach (SetupOnboardingTrack::cases() as $track) {
            $key = $track->value;
            $m = $metricsByTrack[$key] ?? new SetupTrackApplicabilityMetrics(0, 0, 0, 0);
            $recommended = $goal->recommendedTracks[$key] ?? false;

            if (isset($suppressed[$key])) {
                $reason = $this->reasonCatalog->forCodeOrFallback((string) $suppressed[$key]);
                $rows[] = new SetupLaunchTrackRow(
                    key: $key,
                    label: $this->trackLabel($track),
                    state: SetupLaunchUiTrackState::Suppressed,
                    reasonCode: (string) $suppressed[$key],
                    reasonTitle: $reason->title,
                    reasonBody: $reason->body,
                    actionHint: $reason->actionHint,
                    itemsTotal: $m->itemsTotal,
                    itemsApplicable: $m->itemsApplicable,
                    itemsNotApplicableBySystem: $m->itemsNotApplicableBySystem,
                    itemsCompleted: $m->itemsCompleted,
                    recommended: $recommended,
                );

                continue;
            }

            if (! isset($activeSet[$key])) {
                continue;
            }

            $inactiveByScope = $m->itemsTotal > 0 && $m->itemsApplicable === 0;
            if ($inactiveByScope) {
                $rows[] = new SetupLaunchTrackRow(
                    key: $key,
                    label: $this->trackLabel($track),
                    state: SetupLaunchUiTrackState::InactiveByScope,
                    reasonCode: null,
                    reasonTitle: 'Нет применимых шагов в текущей конфигурации',
                    reasonBody: 'В реестре есть пункты этой дорожки, но для вашей темы или настроек они сейчас не применимы.',
                    actionHint: 'Смените тему или проверьте ограничения пунктов в чеклисте ниже.',
                    itemsTotal: $m->itemsTotal,
                    itemsApplicable: $m->itemsApplicable,
                    itemsNotApplicableBySystem: $m->itemsNotApplicableBySystem,
                    itemsCompleted: $m->itemsCompleted,
                    recommended: $recommended,
                );

                continue;
            }

            if ($m->itemsTotal === 0) {
                $rows[] = new SetupLaunchTrackRow(
                    key: $key,
                    label: $this->trackLabel($track),
                    state: SetupLaunchUiTrackState::InactiveByScope,
                    reasonCode: null,
                    reasonTitle: 'Пункты дорожки ещё не добавлены',
                    reasonBody: 'Для этой дорожки пока нет шагов в чеклисте — контур расширяется по мере развития продукта.',
                    actionHint: null,
                    itemsTotal: 0,
                    itemsApplicable: 0,
                    itemsNotApplicableBySystem: 0,
                    itemsCompleted: 0,
                    recommended: $recommended,
                );

                continue;
            }

            [$t, $b] = $this->activeAvailabilityCopy($track, $snapshot);
            $rows[] = new SetupLaunchTrackRow(
                key: $key,
                label: $this->trackLabel($track),
                state: SetupLaunchUiTrackState::Active,
                reasonCode: null,
                reasonTitle: $t,
                reasonBody: $b,
                actionHint: null,
                itemsTotal: $m->itemsTotal,
                itemsApplicable: $m->itemsApplicable,
                itemsNotApplicableBySystem: $m->itemsNotApplicableBySystem,
                itemsCompleted: $m->itemsCompleted,
                recommended: $recommended,
            );
        }

        return new SetupLaunchContext(
            primaryGoal: $goal,
            tracks: $rows,
            layers: $resolved->activeLayers,
            suppressedCount: count($suppressed),
        );
    }

    private function trackLabel(SetupOnboardingTrack $track): string
    {
        return match ($track) {
            SetupOnboardingTrack::Base => 'Базовый контур',
            SetupOnboardingTrack::Branding => 'Оформление и бренд',
            SetupOnboardingTrack::Contacts => 'Контакты',
            SetupOnboardingTrack::Content => 'Контент страниц',
            SetupOnboardingTrack::Programs => 'Программы и услуги',
            SetupOnboardingTrack::Seo => 'SEO',
            SetupOnboardingTrack::Catalog => 'Каталог',
            SetupOnboardingTrack::Scheduling => 'Запись и расписание',
            SetupOnboardingTrack::Notifications => 'Уведомления',
            SetupOnboardingTrack::Reviews => 'Отзывы',
            SetupOnboardingTrack::Push => 'Push и PWA',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function activeAvailabilityCopy(SetupOnboardingTrack $track, SetupCapabilitySnapshot $snapshot): array
    {
        return match ($track) {
            SetupOnboardingTrack::Base => [
                'Дорожка доступна',
                'Базовые шаги запуска относятся ко всем клиентам.',
            ],
            SetupOnboardingTrack::Branding => [
                'Дорожка доступна',
                'Настройки оформления и идентичности сайта доступны в кабинете.',
            ],
            SetupOnboardingTrack::Contacts => [
                'Дорожка доступна',
                'Контакты и каналы связи можно настроить в разделах кабинета.',
            ],
            SetupOnboardingTrack::Content => [
                'Дорожка доступна',
                $snapshot->userCanManageHomepage || $snapshot->userCanManagePages
                    ? 'Редактирование страниц и блоков доступно по вашим правам.'
                    : 'Часть действий может требовать прав на страницы или главную.',
            ],
            SetupOnboardingTrack::Programs => [
                'Дорожка доступна',
                $snapshot->hasVisibleServiceProgram
                    ? 'У вас уже есть опубликованные программы — можно развивать сценарий.'
                    : 'Раздел программ доступен; наполнение зависит от темы и данных.',
            ],
            SetupOnboardingTrack::Seo => [
                'Дорожка доступна',
                $snapshot->userCanManageSeoFiles
                    ? 'SEO-файлы и связанные действия доступны по правам.'
                    : 'Часть SEO-действий может быть ограничена правами.',
            ],
            SetupOnboardingTrack::Catalog => [
                'Дорожка доступна',
                'Каталог и витрина наполняются в соответствии с темой и разделами кабинета.',
            ],
            SetupOnboardingTrack::Scheduling => [
                'Модуль расписания включён',
                'Доступ к расписанию и записям есть у текущей роли.',
            ],
            SetupOnboardingTrack::Notifications => [
                'Уведомления доступны',
                'Есть доступ к центру уведомлений, адресатам или подпискам.',
            ],
            SetupOnboardingTrack::Reviews => [
                'Отзывы доступны',
                'Раздел отзывов доступен по правам.',
            ],
            SetupOnboardingTrack::Push => [
                'Push / PWA доступны',
                'Раздел push-настроек доступен для этого проекта.',
            ],
        };
    }
}
