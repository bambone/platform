<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Lead;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class AttentionRequiredWidget extends Widget
{
    protected string $view = 'filament.tenant.widgets.attention-required-cards';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    /**
     * @return array{leads: Collection<int, Lead>, queueTotal: int}
     */
    protected function getViewData(): array
    {
        $leads = $this->getLeads();

        return [
            'leads' => $leads,
            'queueTotal' => $this->getAttentionQueueCount(),
        ];
    }

    /**
     * Та же выборка, что и у списка карточек (без лимита) — для заголовка и empty state.
     */
    protected function getAttentionQueueCount(): int
    {
        return Lead::query()
            ->whereIn('status', ['new', 'in_progress'])
            ->count();
    }

    /**
     * @return Collection<int, Lead>
     */
    protected function getLeads(): Collection
    {
        return Lead::query()
            ->with(['motorcycle.media'])
            ->whereIn('status', ['new', 'in_progress'])
            ->orderByRaw("
                CASE 
                    WHEN status = 'new' AND created_at < NOW() - INTERVAL 24 HOUR THEN 1
                    WHEN status = 'new' THEN 2
                    WHEN status = 'in_progress' AND created_at < NOW() - INTERVAL 48 HOUR THEN 3
                    ELSE 4
                END ASC
            ")
            ->orderBy('created_at', 'desc')
            ->limit(7)
            ->get();
    }
}
