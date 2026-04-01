<?php

namespace App\Product\CRM\BookingWorkspace;

enum BookingWorkspaceAvailabilityState: string
{
    case Unknown = 'unknown';
    case NoItem = 'no_item';
    case NoDates = 'no_dates';
    case NoData = 'no_data';
    case Available = 'available';
    case Conflict = 'conflict';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Недостаточно данных',
            self::NoItem => 'Объект не выбран',
            self::NoDates => 'Даты не указаны',
            self::NoData => 'Нет данных для расчёта',
            self::Available => 'Свободно на запрошенные даты',
            self::Conflict => 'Пересечение с бронированием',
            self::Blocked => 'Недоступно (блокировка / календарь)',
        };
    }

    public function summaryText(): string
    {
        return match ($this) {
            self::Unknown => 'Не удалось однозначно оценить доступность по текущим данным.',
            self::NoItem => 'Без привязки к объекту календарь недоступен. Даты заявки ниже — если указаны.',
            self::NoDates => 'Без диапазона дат нельзя проверить занятость.',
            self::NoData => 'Нет данных для построения доступности.',
            self::Available => 'По правилам бронирования объект свободен на выбранный период.',
            self::Conflict => 'На эти даты есть действующее бронирование (см. полосу и список ниже).',
            self::Blocked => 'Период перекрывается с блокировкой календаря или все активные юниты заняты.',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Conflict => 'danger',
            self::Blocked => 'warning',
            self::NoItem, self::NoDates, self::NoData => 'neutral',
            self::Unknown => 'muted',
        };
    }
}
