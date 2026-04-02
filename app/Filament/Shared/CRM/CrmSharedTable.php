<?php

namespace App\Filament\Shared\CRM;

use App\Models\CrmRequest;
use App\Models\Motorcycle;
use App\Models\User;
use App\Product\CRM\CrmRequestOperatorService;
use App\Support\FilamentMotorcycleThumbnail;
use Closure;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class CrmSharedTable
{
    /**
     * @return array<int, ImageColumn|SelectColumn|TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            ImageColumn::make('product_thumb')
                ->label('')
                ->getStateUsing(fn (CrmRequest $record): string => FilamentMotorcycleThumbnail::coverUrlOrPlaceholder(self::inferredMotorcycle($record)))
                ->defaultImageUrl(FilamentMotorcycleThumbnail::placeholderDataUrl())
                ->checkFileExistence(false)
                ->imageSize(48)
                ->square()
                ->extraImgAttributes([
                    'class' => 'rounded-lg object-cover',
                    'loading' => 'lazy',
                    'decoding' => 'async',
                ])
                ->extraCellAttributes(['class' => 'w-px pe-0'])
                ->toggleable(),
            TextColumn::make('created_at')
                ->label('Создана')
                ->dateTime('d.m.Y H:i')
                ->sortable(),
            TextColumn::make('motorcycle_context')
                ->label('Техника')
                ->getStateUsing(fn (CrmRequest $record): string => self::inferredMotorcycle($record)?->name ?? '—')
                ->description(fn (CrmRequest $record): string => $record->name)
                ->wrap()
                ->toggleable(),
            TextColumn::make('name')
                ->label('Имя')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('contacts')
                ->label('Контакты')
                ->getStateUsing(function (CrmRequest $record): string {
                    $parts = array_filter([
                        $record->email,
                        $record->phone,
                    ]);

                    return $parts === [] ? '—' : implode(' · ', $parts);
                })
                ->searchable(true, function ($query, string $search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                }),
            TextColumn::make('request_type')
                ->label('Тип')
                ->badge()
                ->toggleable(),
            TextColumn::make('source')
                ->label('Источник')
                ->placeholder('—')
                ->toggleable(),
            SelectColumn::make('status')
                ->label('Статус CRM')
                ->options(CrmRequest::statusLabels())
                ->native(true)
                ->selectablePlaceholder(false)
                ->disabled(fn (CrmRequest $record): bool => ! Gate::check('update', $record))
                ->updateStateUsing(function (mixed $state, Model $record): string {
                    $state = is_string($state) ? $state : (string) $state;
                    $user = Auth::user();
                    if (! $user instanceof User || ! $record instanceof CrmRequest) {
                        return $record instanceof CrmRequest ? $record->status : $state;
                    }
                    app(CrmRequestOperatorService::class)->changeStatus($user, $record, $state);
                    Notification::make()->title('Статус обновлён')->success()->send();

                    return $state;
                }),
            TextColumn::make('priority')
                ->label('Приоритет')
                ->badge()
                ->formatStateUsing(fn (?string $state): string => $state
                    ? (CrmRequest::priorityLabels()[$state] ?? $state)
                    : CrmRequest::priorityLabels()[CrmRequest::PRIORITY_NORMAL])
                ->color(fn (?string $state): string => CrmRequest::priorityColor($state ?? CrmRequest::PRIORITY_NORMAL))
                ->sortable(),
            TextColumn::make('last_activity_at')
                ->label('Активность')
                ->dateTime('d.m.Y H:i')
                ->sortable()
                ->placeholder('—'),
            TextColumn::make('next_follow_up_at')
                ->label('Follow-up')
                ->dateTime('d.m.Y H:i')
                ->sortable()
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('notes_count')
                ->label('Заметки')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('assignedUser.name')
                ->label('Ответственный')
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return Closure(CrmRequest): string
     */
    public static function recordClasses(): Closure
    {
        return function (CrmRequest $record): string {
            $classes = [];

            if ($record->status === CrmRequest::STATUS_NEW) {
                $classes[] = 'border-s-2 border-s-sky-500/80';
            }

            if ($record->isFollowUpOverdue()) {
                $classes[] = 'bg-amber-500/5';
            }

            if ($record->last_activity_at !== null
                && ! $record->isTerminalStatus()
                && $record->last_activity_at->lt(now()->subHours(24))) {
                $classes[] = 'opacity-90';
            }

            if (in_array($record->priority, [CrmRequest::PRIORITY_HIGH, CrmRequest::PRIORITY_URGENT], true)) {
                $classes[] = 'ring-1 ring-amber-500/25 inset-shadow-sm';
            }

            return implode(' ', array_filter($classes));
        };
    }

    private static function inferredMotorcycle(CrmRequest $record): ?Motorcycle
    {
        $payload = $record->payload_json ?? [];
        if (isset($payload['motorcycle_id']) && is_numeric($payload['motorcycle_id'])) {
            $id = (int) $payload['motorcycle_id'];
            if ($id > 0) {
                $q = Motorcycle::query()->with('media')->whereKey($id);
                if ($record->tenant_id !== null) {
                    $q->where('tenant_id', $record->tenant_id);
                }

                $found = $q->first();
                if ($found instanceof Motorcycle) {
                    return $found;
                }
            }
        }

        return $record->leads->first()?->motorcycle;
    }
}
