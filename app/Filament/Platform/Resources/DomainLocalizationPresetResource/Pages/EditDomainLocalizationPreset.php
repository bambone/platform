<?php

namespace App\Filament\Platform\Resources\DomainLocalizationPresetResource\Pages;

use App\Filament\Platform\Resources\DomainLocalizationPresetResource;
use App\Models\DomainLocalizationPreset;
use App\Models\DomainLocalizationPresetTerm;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDomainLocalizationPreset extends EditRecord
{
    protected static string $resource = DomainLocalizationPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Action::make('clonePreset')
                ->label('Клонировать пресет')
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->form([
                    TextInput::make('slug')
                        ->label('Slug нового пресета')
                        ->required()
                        ->maxLength(255)
                        ->rule('regex:/^[a-z0-9_]+$/')
                        ->helperText('Только строчные латинские буквы, цифры и подчёркивание.'),
                    TextInput::make('name')
                        ->label('Название')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    /** @var DomainLocalizationPreset $source */
                    $source = $this->getRecord();
                    $new = DomainLocalizationPreset::query()->create([
                        'slug' => $data['slug'],
                        'name' => $data['name'],
                        'description' => $source->description,
                        'is_active' => true,
                        'sort_order' => (int) $source->sort_order + 1,
                    ]);

                    foreach ($source->presetTerms as $row) {
                        DomainLocalizationPresetTerm::query()->create([
                            'preset_id' => $new->id,
                            'term_id' => $row->term_id,
                            'label' => $row->label,
                            'short_label' => $row->short_label,
                            'notes' => $row->notes,
                        ]);
                    }

                    $this->redirect(DomainLocalizationPresetResource::getUrl('edit', ['record' => $new]));
                })
                ->modalSubmitActionLabel('Создать копию'),
        ];
    }
}
