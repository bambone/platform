<?php

namespace App\Livewire\Crm;

use App\Models\CrmRequest;
use App\Models\User;
use App\Product\CRM\BookingWorkspace\CrmRequestBookingWorkspaceAssembler;
use App\Product\CRM\CrmRequestOperatorService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CrmRequestWorkspace extends Component
{
    public int $crmRequestId;

    public string $statusLocal = '';

    public string $priorityLocal = '';

    public string $internalSummary = '';

    public string $noteDraft = '';

    public bool $notePinImportant = false;

    public ?string $followUpLocal = null;

    /** Ненавязчивое подтверждение автосохранения: пусто | status | priority */
    public string $autosaveInlineHint = '';

    public function mount(int $crmRequestId): void
    {
        $this->crmRequestId = $crmRequestId;
        $this->bootstrapFirstView();
        $this->syncFormFromRecord();
    }

    private function bootstrapFirstView(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $crm = CrmRequest::query()->find($this->crmRequestId);
        if ($crm === null) {
            return;
        }

        Gate::authorize('view', $crm);
        app(CrmRequestOperatorService::class)->markFirstViewed($user, $crm);
    }

    /**
     * Минимальная загрузка заявки для синхронизации формы и вызовов операторского сервиса (без eager relations).
     */
    private function loadCrmBaseRecord(): CrmRequest
    {
        $crm = CrmRequest::query()->findOrFail($this->crmRequestId);
        Gate::authorize('view', $crm);

        return $crm;
    }

    /**
     * Полная загрузка для отрисовки workspace (заметки, активности, ответственный).
     */
    private function loadCrmWorkspaceRecord(): CrmRequest
    {
        $crm = CrmRequest::query()
            ->with([
                'tenant',
                'leads' => fn ($q) => $q->orderByDesc('id'),
                'leads.motorcycle.category',
                'notes' => fn ($q) => $q->orderByDesc('is_pinned')->orderBy('created_at'),
                'notes.user',
                'activities' => fn ($q) => $q->orderByDesc('created_at'),
                'activities.actor',
                'assignedUser',
            ])
            ->findOrFail($this->crmRequestId);

        Gate::authorize('view', $crm);

        return $crm;
    }

    private function syncFormFromRecord(): void
    {
        $crm = $this->loadCrmBaseRecord();

        $this->statusLocal = $crm->status;
        $this->priorityLocal = $crm->priority ?? CrmRequest::PRIORITY_NORMAL;
        $this->internalSummary = (string) ($crm->internal_summary ?? '');
        $this->followUpLocal = $crm->next_follow_up_at?->format('Y-m-d\TH:i');
    }

    private function mapOperatorValidationKey(string $key): string
    {
        return match ($key) {
            'status' => 'statusLocal',
            'priority' => 'priorityLocal',
            'body' => 'noteDraft',
            default => $key,
        };
    }

    private function applyOperatorValidationException(ValidationException $e): void
    {
        foreach ($e->errors() as $key => $messages) {
            $prop = $this->mapOperatorValidationKey($key);
            $first = $messages[0] ?? '';
            if (is_string($first) && $first !== '') {
                $this->addError($prop, $first);
            }
        }
    }

    /**
     * Короткая подсказка у поля вместо success-toast (без спама при быстрых переключениях).
     */
    private function flashQuietAutosaveConfirmation(string $hint): void
    {
        $this->autosaveInlineHint = $hint;
        $this->js('setTimeout(() => $wire.set("autosaveInlineHint", ""), 1400)');
    }

    public function saveSummary(CrmRequestOperatorService $svc): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $this->resetErrorBag('internalSummary');

        $text = trim($this->internalSummary);

        $svc->updateSummary($user, $this->loadCrmBaseRecord(), $text === '' ? null : $text);
        Notification::make()->title('Резюме сохранено')->success()->send();
        $this->syncFormFromRecord();
    }

    public function updatedStatusLocal(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $this->resetErrorBag('statusLocal');
        $this->autosaveInlineHint = '';

        $fresh = CrmRequest::query()->whereKey($this->crmRequestId)->value('status');
        if ($fresh === $this->statusLocal) {
            return;
        }

        $crm = $this->loadCrmBaseRecord();

        try {
            app(CrmRequestOperatorService::class)->changeStatus($user, $crm, $this->statusLocal);
            $this->flashQuietAutosaveConfirmation('status');
        } catch (ValidationException $e) {
            $this->applyOperatorValidationException($e);
            $this->syncFormFromRecord();

            return;
        } catch (\Throwable $e) {
            $this->syncFormFromRecord();
            throw $e;
        }
        $this->syncFormFromRecord();
    }

    public function updatedPriorityLocal(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $this->resetErrorBag('priorityLocal');
        $this->autosaveInlineHint = '';

        $fresh = CrmRequest::query()->whereKey($this->crmRequestId)->value('priority') ?? CrmRequest::PRIORITY_NORMAL;
        if ($fresh === $this->priorityLocal) {
            return;
        }

        $crm = $this->loadCrmBaseRecord();

        try {
            app(CrmRequestOperatorService::class)->updatePriority($user, $crm, $this->priorityLocal);
            $this->flashQuietAutosaveConfirmation('priority');
        } catch (ValidationException $e) {
            $this->applyOperatorValidationException($e);
            $this->syncFormFromRecord();

            return;
        } catch (\Throwable $e) {
            $this->syncFormFromRecord();
            throw $e;
        }
        $this->syncFormFromRecord();
    }

    public function saveFollowUp(CrmRequestOperatorService $svc): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $this->resetErrorBag('followUpLocal');

        $raw = trim((string) $this->followUpLocal);
        try {
            $at = $raw === '' ? null : Carbon::parse($raw);
        } catch (\Throwable) {
            $this->addError('followUpLocal', __('Некорректная дата и время.'));

            return;
        }

        $svc->updateFollowUp($user, $this->loadCrmBaseRecord(), $at);
        Notification::make()->title('Напоминание сохранено')->success()->send();
        $this->syncFormFromRecord();
    }

    public function clearFollowUp(CrmRequestOperatorService $svc): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $this->resetErrorBag('followUpLocal');

        $svc->updateFollowUp($user, $this->loadCrmBaseRecord(), null);
        $this->followUpLocal = null;
        Notification::make()->title('Напоминание сброшено')->success()->send();
        $this->syncFormFromRecord();
    }

    public function addNote(CrmRequestOperatorService $svc): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $this->resetErrorBag('noteDraft');

        try {
            $svc->addNote($user, $this->loadCrmBaseRecord(), $this->noteDraft, $this->notePinImportant);
        } catch (ValidationException $e) {
            $this->applyOperatorValidationException($e);

            return;
        }

        $this->noteDraft = '';
        $this->notePinImportant = false;
        Notification::make()->title('Комментарий добавлен')->success()->send();
    }

    public function render(): View
    {
        $crm = $this->loadCrmWorkspaceRecord();

        return view('livewire.crm.crm-request-workspace', [
            'crm' => $crm,
            'bookingWorkspace' => app(CrmRequestBookingWorkspaceAssembler::class)->assemble($crm),
        ]);
    }
}
