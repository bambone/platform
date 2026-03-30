<?php

namespace App\Filament\Shared\CRM;

use App\Models\CrmRequest;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

final class CrmSharedFilters
{
    /**
     * Distinct request_type values constrained by the same scope as the resource list (tenant / platform).
     *
     * @return array<string, string>
     */
    public static function requestTypeOptionsForScopedQuery(Builder $scopedCrmQuery): array
    {
        return (clone $scopedCrmQuery)
            ->whereNotNull('request_type')
            ->distinct()
            ->orderBy('request_type')
            ->pluck('request_type', 'request_type')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function sourceOptionsForScopedQuery(Builder $scopedCrmQuery): array
    {
        return (clone $scopedCrmQuery)
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->distinct()
            ->orderBy('source')
            ->pluck('source', 'source')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public static function assignedUserOptionsForScopedQuery(Builder $scopedCrmQuery): array
    {
        $ids = (clone $scopedCrmQuery)
            ->whereNotNull('assigned_user_id')
            ->distinct()
            ->pluck('assigned_user_id')
            ->filter()
            ->all();

        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, Filter|SelectFilter>
     */
    public static function tableFilters(Builder $scopedCrmQuery): array
    {
        return [
            SelectFilter::make('status')
                ->label('Статус CRM')
                ->options(CrmRequest::statusLabels()),
            SelectFilter::make('request_type')
                ->label('Тип заявки')
                ->options(fn (): array => self::requestTypeOptionsForScopedQuery($scopedCrmQuery)),
            SelectFilter::make('source')
                ->label('Источник')
                ->options(fn (): array => self::sourceOptionsForScopedQuery($scopedCrmQuery)),
            SelectFilter::make('priority')
                ->label('Приоритет')
                ->options(CrmRequest::priorityLabels()),
            SelectFilter::make('assigned_user_id')
                ->label('Ответственный')
                ->options(fn (): array => self::assignedUserOptionsForScopedQuery($scopedCrmQuery)),
            Filter::make('notes_presence')
                ->label('Комментарии')
                ->schema([
                    Select::make('value')
                        ->label('Комментарии')
                        ->options([
                            '' => 'Все',
                            'has' => 'С комментариями',
                            'none' => 'Без комментариев',
                        ])
                        ->default(''),
                ])
                ->query(function (Builder $query, array $data): void {
                    $v = (string) ($data['value'] ?? '');
                    if ($v === 'has') {
                        $query->whereHas('notes');
                    } elseif ($v === 'none') {
                        $query->whereDoesntHave('notes');
                    }
                }),
            Filter::make('needs_follow_up')
                ->label('Просроченный follow-up')
                ->query(function (Builder $query): void {
                    $query->needsFollowUp();
                }),
            Filter::make('created_range')
                ->label('Дата создания')
                ->schema([
                    DatePicker::make('from')->label('С'),
                    DatePicker::make('until')->label('По'),
                ])
                ->query(function (Builder $query, array $data): void {
                    if (! empty($data['from'])) {
                        $query->whereDate('created_at', '>=', $data['from']);
                    }
                    if (! empty($data['until'])) {
                        $query->whereDate('created_at', '<=', $data['until']);
                    }
                }),
        ];
    }
}
