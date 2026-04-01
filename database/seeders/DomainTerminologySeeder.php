<?php

namespace Database\Seeders;

use App\Models\DomainLocalizationPreset;
use App\Models\DomainLocalizationPresetTerm;
use App\Models\DomainTerm;
use App\Models\Tenant;
use App\Terminology\DomainTermEmergencyLabels;
use App\Terminology\DomainTermKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DomainTerminologySeeder extends Seeder
{
    public function run(): void
    {
        Model::withoutEvents(function (): void {
            $this->syncTerms();

            $byKey = DomainTerm::query()->pluck('id', 'term_key')->all();

            foreach ($this->presetLabelMaps() as $slug => $labels) {
                $preset = DomainLocalizationPreset::query()->where('slug', $slug)->first();
                if ($preset === null) {
                    continue;
                }
                foreach ($labels as $termKey => $labelData) {
                    $termId = $byKey[$termKey] ?? null;
                    if ($termId === null) {
                        continue;
                    }
                    $label = is_array($labelData) ? $labelData['label'] : $labelData;
                    $short = is_array($labelData) ? ($labelData['short'] ?? null) : null;
                    DomainLocalizationPresetTerm::query()->updateOrCreate(
                        [
                            'preset_id' => $preset->id,
                            'term_id' => $termId,
                        ],
                        [
                            'label' => $label,
                            'short_label' => $short,
                        ]
                    );
                }
            }
        });

        $genericId = DomainLocalizationPreset::query()->where('slug', 'generic_services')->value('id');
        if ($genericId !== null) {
            Tenant::withoutEvents(function () use ($genericId): void {
                Tenant::query()->whereNull('domain_localization_preset_id')->update([
                    'domain_localization_preset_id' => $genericId,
                ]);
            });
        }
    }

    private function syncTerms(): void
    {
        $rows = [
            [DomainTermKeys::LEAD, 'crm', 'Обращение', 'Входящее обращение клиента (лид).'],
            [DomainTermKeys::LEAD_PLURAL, 'crm', 'Обращения', null],
            [DomainTermKeys::BOOKING, 'booking_flow', 'Бронирование', 'Подтверждённая запись или бронь.'],
            [DomainTermKeys::BOOKING_PLURAL, 'booking_flow', 'Бронирования', null],
            [DomainTermKeys::APPOINTMENT, 'booking_flow', 'Запись', 'Слот или приём.'],
            [DomainTermKeys::APPOINTMENT_PLURAL, 'booking_flow', 'Записи', null],
            [DomainTermKeys::REQUEST, 'crm', 'Заявка', 'Заявка в операционном контуре CRM.'],
            [DomainTermKeys::REQUEST_PLURAL, 'crm', 'Заявки', null],
            [DomainTermKeys::CUSTOMER, 'customer', 'Клиент', null],
            [DomainTermKeys::CUSTOMER_PLURAL, 'customer', 'Клиенты', null],
            [DomainTermKeys::SERVICE, 'catalog', 'Услуга', null],
            [DomainTermKeys::SERVICE_PLURAL, 'catalog', 'Услуги', null],
            [DomainTermKeys::RESOURCE, 'catalog', 'Объект каталога', 'Товар, техника или позиция каталога.'],
            [DomainTermKeys::RESOURCE_PLURAL, 'catalog', 'Объекты каталога', null],
            [DomainTermKeys::STAFF_MEMBER, 'staff', 'Сотрудник', null],
            [DomainTermKeys::STAFF_MEMBER_PLURAL, 'staff', 'Сотрудники', null],
            [DomainTermKeys::LOCATION, 'common', 'Локация', null],
            [DomainTermKeys::LOCATION_PLURAL, 'common', 'Локации', null],
            [DomainTermKeys::CATEGORY, 'catalog', 'Категория', null],
            [DomainTermKeys::CATEGORY_PLURAL, 'catalog', 'Категории', null],
            [DomainTermKeys::FLEET_UNIT, 'catalog', 'Единица парка', 'Единица парка / флота.'],
            [DomainTermKeys::FLEET_UNIT_PLURAL, 'catalog', 'Единицы парка', null],
            [DomainTermKeys::NAV_OPERATIONS, 'navigation', 'Операции', null],
            [DomainTermKeys::NAV_CATALOG, 'navigation', 'Каталог', null],
            [DomainTermKeys::NAV_CONTENT, 'navigation', 'Контент', null],
            [DomainTermKeys::NAV_MARKETING, 'navigation', 'Маркетинг', null],
            [DomainTermKeys::NAV_INFRASTRUCTURE, 'navigation', 'Инфраструктура', null],
            [DomainTermKeys::NAV_SETTINGS, 'navigation', 'Настройки', null],
        ];

        foreach ($rows as [$key, $group, $defaultLabel, $description]) {
            DomainTerm::query()->updateOrCreate(
                ['term_key' => $key],
                [
                    'group' => $group,
                    'default_label' => $defaultLabel,
                    'description' => $description,
                    'value_type' => 'text',
                    'is_required' => true,
                    'is_active' => true,
                    'is_editable_by_tenant' => true,
                ]
            );
        }
    }

    /**
     * @return array<string, array<string, string|array{label: string, short?: string|null}>>
     */
    private function presetLabelMaps(): array
    {
        $g = $this->genericLabels();

        return [
            'generic_services' => $g,
            'other' => $g,
            'moto_rental' => array_replace($g, [
                DomainTermKeys::RESOURCE => 'Мотоцикл',
                DomainTermKeys::RESOURCE_PLURAL => 'Мотоциклы',
                DomainTermKeys::BOOKING => 'Бронирование',
                DomainTermKeys::BOOKING_PLURAL => 'Бронирования',
                DomainTermKeys::FLEET_UNIT => 'Мотоцикл в парке',
                DomainTermKeys::FLEET_UNIT_PLURAL => 'Мотоциклы в парке',
                DomainTermKeys::REQUEST => 'Заявка',
                DomainTermKeys::REQUEST_PLURAL => 'Заявки',
            ]),
            'car_rental' => array_replace($g, [
                DomainTermKeys::RESOURCE => 'Автомобиль',
                DomainTermKeys::RESOURCE_PLURAL => 'Автомобили',
                DomainTermKeys::FLEET_UNIT => 'Автомобиль в парке',
                DomainTermKeys::FLEET_UNIT_PLURAL => 'Автомобили в парке',
                DomainTermKeys::REQUEST => 'Заявка',
                DomainTermKeys::REQUEST_PLURAL => 'Заявки',
            ]),
            'beauty_salon' => array_replace($g, [
                DomainTermKeys::BOOKING => 'Запись',
                DomainTermKeys::BOOKING_PLURAL => 'Записи',
                DomainTermKeys::APPOINTMENT => 'Приём',
                DomainTermKeys::APPOINTMENT_PLURAL => 'Приёмы',
                DomainTermKeys::RESOURCE => 'Услуга',
                DomainTermKeys::RESOURCE_PLURAL => 'Услуги',
                DomainTermKeys::STAFF_MEMBER => 'Мастер',
                DomainTermKeys::STAFF_MEMBER_PLURAL => 'Мастера',
                DomainTermKeys::LEAD => 'Обращение',
                DomainTermKeys::LEAD_PLURAL => 'Обращения',
                DomainTermKeys::REQUEST => 'Заявка',
                DomainTermKeys::REQUEST_PLURAL => 'Заявки',
            ]),
            'instructor_booking' => array_replace($g, [
                DomainTermKeys::BOOKING => 'Запись',
                DomainTermKeys::BOOKING_PLURAL => 'Записи',
                DomainTermKeys::RESOURCE => 'Занятие',
                DomainTermKeys::RESOURCE_PLURAL => 'Занятия',
                DomainTermKeys::STAFF_MEMBER => 'Инструктор',
                DomainTermKeys::STAFF_MEMBER_PLURAL => 'Инструкторы',
                DomainTermKeys::APPOINTMENT => 'Слот',
                DomainTermKeys::APPOINTMENT_PLURAL => 'Слоты',
            ]),
            'tool_rental' => array_replace($g, [
                DomainTermKeys::BOOKING => 'Заказ',
                DomainTermKeys::BOOKING_PLURAL => 'Заказы',
                DomainTermKeys::RESOURCE => 'Инструмент',
                DomainTermKeys::RESOURCE_PLURAL => 'Инструменты',
                DomainTermKeys::FLEET_UNIT => 'Единица инструмента',
                DomainTermKeys::FLEET_UNIT_PLURAL => 'Единицы инструмента',
            ]),
            'advanced_driving_pk' => array_replace($g, [
                DomainTermKeys::BOOKING => 'Запись',
                DomainTermKeys::BOOKING_PLURAL => 'Записи',
                DomainTermKeys::APPOINTMENT => 'Занятие',
                DomainTermKeys::APPOINTMENT_PLURAL => 'Занятия',
                DomainTermKeys::RESOURCE => 'Курс',
                DomainTermKeys::RESOURCE_PLURAL => 'Курсы',
                DomainTermKeys::STAFF_MEMBER => 'Инструктор',
                DomainTermKeys::STAFF_MEMBER_PLURAL => 'Инструкторы',
                DomainTermKeys::FLEET_UNIT => 'Учебный автомобиль',
                DomainTermKeys::FLEET_UNIT_PLURAL => 'Учебные автомобили',
                DomainTermKeys::LEAD => 'Заявка на обучение',
                DomainTermKeys::LEAD_PLURAL => 'Заявки на обучение',
                DomainTermKeys::REQUEST => 'Заявка',
                DomainTermKeys::REQUEST_PLURAL => 'Заявки',
            ]),
            'nail_service_booking' => array_replace($g, [
                DomainTermKeys::BOOKING => 'Запись',
                DomainTermKeys::BOOKING_PLURAL => 'Записи',
                DomainTermKeys::APPOINTMENT => 'Приём',
                DomainTermKeys::APPOINTMENT_PLURAL => 'Приёмы',
                DomainTermKeys::RESOURCE => 'Услуга',
                DomainTermKeys::RESOURCE_PLURAL => 'Услуги',
                DomainTermKeys::STAFF_MEMBER => 'Мастер',
                DomainTermKeys::STAFF_MEMBER_PLURAL => 'Мастера',
                DomainTermKeys::LEAD => 'Обращение',
                DomainTermKeys::LEAD_PLURAL => 'Обращения',
                DomainTermKeys::REQUEST => 'Заявка',
                DomainTermKeys::REQUEST_PLURAL => 'Заявки',
                DomainTermKeys::FLEET_UNIT => 'Рабочее место',
                DomainTermKeys::FLEET_UNIT_PLURAL => 'Рабочие места',
            ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function genericLabels(): array
    {
        return DomainTermEmergencyLabels::ruMap();
    }
}
