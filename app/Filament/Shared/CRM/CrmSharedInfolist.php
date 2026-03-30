<?php

namespace App\Filament\Shared\CRM;

use App\Models\CrmRequest;
use App\Models\CrmRequestActivity;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CrmSharedInfolist
{
    public static function schema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Контакт и канал')
                ->schema([
                    TextEntry::make('id')->label('CRM ID'),
                    TextEntry::make('created_at')->label('Создана')->dateTime(),
                    TextEntry::make('name')->label('Имя'),
                    TextEntry::make('phone')->label('Телефон')->placeholder('—'),
                    TextEntry::make('email')->label('Email')->placeholder('—'),
                    TextEntry::make('message')->label('Сообщение клиента')->columnSpanFull()->placeholder('—'),
                ])
                ->columns(2),
            Section::make('Атрибуция')
                ->schema([
                    TextEntry::make('request_type')->label('Тип заявки'),
                    TextEntry::make('source')->label('Источник')->placeholder('—'),
                    TextEntry::make('channel')->label('Канал'),
                    TextEntry::make('pipeline')->label('Воронка'),
                    TextEntry::make('status')
                        ->label('Статус')
                        ->formatStateUsing(fn (?string $state): string => $state ? (CrmRequest::statusLabels()[$state] ?? $state) : '—'),
                    TextEntry::make('utm_source')->label('utm_source')->placeholder('—'),
                    TextEntry::make('utm_medium')->label('utm_medium')->placeholder('—'),
                    TextEntry::make('utm_campaign')->label('utm_campaign')->placeholder('—'),
                    TextEntry::make('landing_page')->label('Страница')->columnSpanFull()->placeholder('—'),
                    TextEntry::make('referrer')->label('Referrer')->columnSpanFull()->placeholder('—'),
                    TextEntry::make('ip')->label('IP')->placeholder('—'),
                ])
                ->columns(2),
            Section::make('Технические данные')
                ->schema([
                    TextEntry::make('payload_json')
                        ->label('JSON')
                        ->columnSpanFull()
                        ->formatStateUsing(function ($state): string {
                            if ($state === null || $state === []) {
                                return '—';
                            }

                            return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        }),
                ])
                ->collapsed(),
            Section::make('История работы')
                ->schema([
                    RepeatableEntry::make('activities')
                        ->label('')
                        ->schema([
                            TextEntry::make('created_at')->label('Когда')->dateTime(),
                            TextEntry::make('type')
                                ->label('Тип')
                                ->formatStateUsing(fn (?string $state): string => $state ? CrmRequestActivity::typeLabel($state) : '—'),
                            TextEntry::make('meta')
                                ->label('Мета')
                                ->columnSpanFull()
                                ->formatStateUsing(function ($state): string {
                                    if ($state === null || $state === []) {
                                        return '—';
                                    }

                                    return json_encode($state, JSON_UNESCAPED_UNICODE);
                                }),
                        ])
                        ->columns(3),
                ])
                ->collapsed(false),
        ]);
    }
}
