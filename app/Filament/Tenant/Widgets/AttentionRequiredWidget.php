<?php

namespace App\Filament\Tenant\Widgets;

use App\Filament\Tenant\Resources\LeadResource;
use App\Models\Lead;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AttentionRequiredWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Lead::query()
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
            )
            ->heading('Требует внимания')
            ->description(function (): string {
                $tenant = currentTenant();
                $n = Lead::where('status', 'new')->count();
                if ($tenant === null) {
                    return $n > 0
                        ? 'У вас '.$n.' новых заявок, ожидающих ответа.'
                        : 'Новых заявок пока нет. Отличная работа!';
                }
                $leadPlural = mb_strtolower(app(TenantTerminologyService::class)->label($tenant, DomainTermKeys::LEAD_PLURAL));

                return $n > 0
                    ? 'У вас '.$n.' новых '.$leadPlural.', ожидающих ответа.'
                    : 'Новых '.$leadPlural.' пока нет. Отличная работа!';
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Время')
                    ->dateTime('H:i, d.m')
                    ->description(fn (Lead $record) => $record->created_at->diffForHumans()),
                TextColumn::make('name')
                    ->label('Клиент')
                    ->description(fn (Lead $record) => $record->phone),
                TextColumn::make('status')
                    ->label('Проблема')
                    ->badge()
                    ->color(fn (Lead $record) => $record->created_at->diffInHours(now()) > 24 ? 'danger' : 'warning')
                    ->formatStateUsing(fn (string $state, Lead $record) => match (true) {
                        $record->created_at->diffInHours(now()) > 24 => 'Просрочено (>24ч)',
                        default => 'Ждет ответа',
                    }),
                TextColumn::make('next_action')
                    ->label('Next Best Action')
                    ->default(fn (Lead $record) => 'Позвонить клиенту')
                    ->color('primary')
                    ->weight('bold'),
            ])
            ->actions([
                Action::make('call')
                    ->icon('heroicon-o-phone')
                    ->color('success')
                    ->label('Call')
                    ->url(fn (Lead $record) => 'tel:'.preg_replace('/[^0-9]/', '', $record->phone)),
                Action::make('open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Lead $record) => LeadResource::getUrl('index').'?tableFilters[id][value]='.$record->id),
            ])
            ->paginated(false);
    }
}
