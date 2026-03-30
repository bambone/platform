<?php

namespace App\Product\CRM;

use App\Models\CrmRequest;
use App\Models\CrmRequestActivity;
use App\Models\CrmRequestNote;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Единственный слой операторских мутаций CRM-заявки (статус, заметки, приоритет, follow-up, резюме, первый просмотр).
 *
 * UI (Filament, Livewire, SelectColumn) не должен обновлять эти поля напрямую.
 */
final class CrmRequestOperatorService
{
    public function changeStatus(User $actor, CrmRequest $crm, string $toStatus): void
    {
        Gate::forUser($actor)->authorize('update', $crm);

        $toStatus = trim($toStatus);
        if (! in_array($toStatus, CrmRequest::canonicalStatusValues(), true)) {
            throw ValidationException::withMessages(['status' => __('Недопустимый статус.')]);
        }

        $crm->refresh();
        $fromStatus = $crm->status;
        if ($fromStatus === $toStatus) {
            return;
        }

        DB::transaction(function () use ($actor, $crm, $fromStatus, $toStatus): void {
            $crm->status = $toStatus;
            $crm->last_activity_at = now();

            if ($fromStatus === CrmRequest::STATUS_NEW && $crm->processed_at === null) {
                $crm->processed_at = now();
            }

            $crm->save();

            CrmRequestActivity::query()->create([
                'crm_request_id' => $crm->id,
                'type' => CrmRequestActivity::TYPE_STATUS_CHANGED,
                'meta' => [
                    'old' => $fromStatus,
                    'new' => $toStatus,
                ],
                'actor_user_id' => $actor->id,
            ]);
        });
    }

    public function addNote(User $actor, CrmRequest $crm, string $body, bool $isPinned = false): CrmRequestNote
    {
        Gate::forUser($actor)->authorize('update', $crm);

        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => __('Комментарий не может быть пустым.')]);
        }

        return DB::transaction(function () use ($actor, $crm, $body, $isPinned): CrmRequestNote {
            $note = CrmRequestNote::query()->create([
                'crm_request_id' => $crm->id,
                'user_id' => $actor->id,
                'body' => $body,
                'is_pinned' => $isPinned,
            ]);

            $now = now();
            $crm->update([
                'last_commented_at' => $now,
                'last_activity_at' => $now,
            ]);

            $preview = mb_strlen($body) > 120 ? mb_substr($body, 0, 117).'…' : $body;

            CrmRequestActivity::query()->create([
                'crm_request_id' => $crm->id,
                'type' => CrmRequestActivity::TYPE_NOTE_ADDED,
                'meta' => [
                    'note_id' => $note->id,
                    'preview' => $preview,
                ],
                'actor_user_id' => $actor->id,
            ]);

            return $note;
        });
    }

    public function updatePriority(User $actor, CrmRequest $crm, string $priority): void
    {
        Gate::forUser($actor)->authorize('update', $crm);

        if (! in_array($priority, CrmRequest::priorityValues(), true)) {
            throw ValidationException::withMessages(['priority' => __('Недопустимый приоритет.')]);
        }

        $crm->refresh();
        $old = $crm->priority ?? CrmRequest::PRIORITY_NORMAL;
        if ($old === $priority) {
            return;
        }

        DB::transaction(function () use ($actor, $crm, $old, $priority): void {
            $crm->priority = $priority;
            $crm->last_activity_at = now();
            $crm->save();

            CrmRequestActivity::query()->create([
                'crm_request_id' => $crm->id,
                'type' => CrmRequestActivity::TYPE_PRIORITY_CHANGED,
                'meta' => ['old' => $old, 'new' => $priority],
                'actor_user_id' => $actor->id,
            ]);
        });
    }

    /**
     * @param  CarbonInterface|null  $at  null clears follow-up
     */
    public function updateFollowUp(User $actor, CrmRequest $crm, ?\DateTimeInterface $at): void
    {
        Gate::forUser($actor)->authorize('update', $crm);

        $crm->refresh();
        $oldAt = $crm->next_follow_up_at;
        $newAt = $at !== null ? Carbon::instance($at) : null;

        $oldKey = $oldAt?->toIso8601String();
        $newKey = $newAt?->toIso8601String();
        if ($oldKey === $newKey) {
            return;
        }

        DB::transaction(function () use ($actor, $crm, $oldAt, $newAt): void {
            $crm->next_follow_up_at = $newAt;
            $crm->last_activity_at = now();
            $crm->save();

            CrmRequestActivity::query()->create([
                'crm_request_id' => $crm->id,
                'type' => CrmRequestActivity::TYPE_FOLLOW_UP_SET,
                'meta' => [
                    'old' => $oldAt?->toIso8601String(),
                    'at' => $newAt?->toIso8601String(),
                ],
                'actor_user_id' => $actor->id,
            ]);
        });
    }

    public function updateSummary(User $actor, CrmRequest $crm, ?string $summary): void
    {
        Gate::forUser($actor)->authorize('update', $crm);

        $summary = $summary !== null ? trim($summary) : null;
        if ($summary === '') {
            $summary = null;
        }

        $crm->refresh();
        $old = $crm->internal_summary;
        if ($old === $summary) {
            return;
        }

        $summaryMeta = self::buildSummaryActivityMeta($old, $summary);

        DB::transaction(function () use ($actor, $crm, $summary, $summaryMeta): void {
            $crm->internal_summary = $summary;
            $crm->last_activity_at = now();
            $crm->save();

            CrmRequestActivity::query()->create([
                'crm_request_id' => $crm->id,
                'type' => CrmRequestActivity::TYPE_SUMMARY_UPDATED,
                'meta' => $summaryMeta,
                'actor_user_id' => $actor->id,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildSummaryActivityMeta(?string $old, ?string $new): array
    {
        if ($new === null || $new === '') {
            if ($old !== null && $old !== '') {
                return ['cleared' => true];
            }

            return [];
        }

        $preview = mb_strlen($new) > 120 ? mb_substr($new, 0, 117).'…' : $new;

        $isFirst = $old === null || $old === '';

        return array_filter([
            'preview' => $preview,
            'first' => $isFirst ? true : null,
        ]);
    }

    public function markFirstViewed(User $actor, CrmRequest $crm): void
    {
        Gate::forUser($actor)->authorize('update', $crm);

        if ($crm->first_viewed_at !== null) {
            return;
        }

        $crm->update(['first_viewed_at' => now()]);
    }
}
