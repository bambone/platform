<?php

namespace Database\Seeders;

use App\Models\DomainLocalizationPreset;
use App\Models\DomainLocalizationPresetTerm;
use App\Models\DomainTerm;
use App\Models\Tenant;
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
            [DomainTermKeys::LEAD, 'crm', 'Заявка', 'Входящие и операционные заявки клиентов.'],
            [DomainTermKeys::LEAD_PLURAL, 'crm', 'Заявки', null],
            [DomainTermKeys::BOOKING, 'booking_flow', 'Бронирование', 'Подтверждённая запись или бронь.'],
            [DomainTermKeys::BOOKING_PLURAL, 'booking_flow', 'Бронирования', null],
            [DomainTermKeys::APPOINTMENT, 'booking_flow', 'Запись', 'Слот или приём.'],
            [DomainTermKeys::APPOINTMENT_PLURAL, 'booking_flow', 'Записи', null],
            [DomainTermKeys::REQUEST, 'crm', 'Обращение', 'Входящее обращение / CRM.'],
            [DomainTermKeys::REQUEST_PLURAL, 'crm', 'Обращения', null],
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
                DomainTermKeys::REQUEST => 'CRM-заявка',
                DomainTermKeys::REQUEST_PLURAL => 'CRM-заявки',
            ]),
            'car_rental' => array_replace($g, [
                DomainTermKeys::RESOURCE => 'Автомобиль',
                DomainTermKeys::RESOURCE_PLURAL => 'Автомобили',
                DomainTermKeys::FLEET_UNIT => 'Автомобиль в парке',
                DomainTermKeys::FLEET_UNIT_PLURAL => 'Автомобили в парке',
                DomainTermKeys::REQUEST => 'CRM-заявка',
                DomainTermKeys::REQUEST_PLURAL => 'CRM-заявки',
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
                DomainTermKeys::REQUEST => 'CRM-обращение',
                DomainTermKeys::REQUEST_PLURAL => 'CRM-обращения',
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
        ];
    }

    /**
     * @return array<string, string>
     */
    private function genericLabels(): array
    {
        return [
            DomainTermKeys::LEAD => 'Заявка',
            DomainTermKeys::LEAD_PLURAL => 'Заявки',
            DomainTermKeys::BOOKING => 'Бронирование',
            DomainTermKeys::BOOKING_PLURAL => 'Бронирования',
            DomainTermKeys::APPOINTMENT => 'Запись',
            DomainTermKeys::APPOINTMENT_PLURAL => 'Записи',
            DomainTermKeys::REQUEST => 'Обращение',
            DomainTermKeys::REQUEST_PLURAL => 'Обращения',
            DomainTermKeys::CUSTOMER => 'Клиент',
            DomainTermKeys::CUSTOMER_PLURAL => 'Клиенты',
            DomainTermKeys::SERVICE => 'Услуга',
            DomainTermKeys::SERVICE_PLURAL => 'Услуги',
            DomainTermKeys::RESOURCE => 'Объект каталога',
            DomainTermKeys::RESOURCE_PLURAL => 'Объекты каталога',
            DomainTermKeys::STAFF_MEMBER => 'Сотрудник',
            DomainTermKeys::STAFF_MEMBER_PLURAL => 'Сотрудники',
            DomainTermKeys::LOCATION => 'Локация',
            DomainTermKeys::LOCATION_PLURAL => 'Локации',
            DomainTermKeys::CATEGORY => 'Категория',
            DomainTermKeys::CATEGORY_PLURAL => 'Категории',
            DomainTermKeys::FLEET_UNIT => 'Единица парка',
            DomainTermKeys::FLEET_UNIT_PLURAL => 'Единицы парка',
            DomainTermKeys::NAV_OPERATIONS => 'Операции',
            DomainTermKeys::NAV_CATALOG => 'Каталог',
            DomainTermKeys::NAV_CONTENT => 'Контент',
            DomainTermKeys::NAV_SETTINGS => 'Настройки',
        ];
    }
}
