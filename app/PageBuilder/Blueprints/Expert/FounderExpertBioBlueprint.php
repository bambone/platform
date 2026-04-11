<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class FounderExpertBioBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'founder_expert_bio';
    }

    public function label(): string
    {
        return 'Expert: Био основателя';
    }

    public function description(): string
    {
        return 'Текст и слот фото (медиа через админку).';
    }

    public function icon(): string
    {
        return 'heroicon-o-user';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Content;
    }

    public function defaultData(): array
    {
        return [
            'heading' => '',
            'lead' => '',
            'paragraphs' => [],
            'photo_slot' => null,
            'portrait_image_url' => '',
            'portrait_image_alt' => '',
            'section_id' => '',
            'trust_points' => [],
            'cta_label' => '',
            'cta_anchor' => '',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            Textarea::make('data_json.lead')->label('Лид')->rows(2)->columnSpanFull(),
            Repeater::make('data_json.paragraphs')
                ->label('Абзацы')
                ->schema([
                    Textarea::make('text')->label('Текст')->rows(3)->required()->columnSpanFull(),
                ])
                ->columnSpanFull(),
            TextInput::make('data_json.portrait_image_url')
                ->label('URL портрета')
                ->maxLength(2048)
                ->columnSpanFull(),
            TextInput::make('data_json.portrait_image_alt')
                ->label('Alt портрета')
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make('data_json.section_id')
                ->label('HTML id секции (якорь)')
                ->maxLength(64)
                ->helperText('Например about — для ссылки /#about'),
            Repeater::make('data_json.trust_points')
                ->label('Маркеры доверия')
                ->schema([
                    TextInput::make('text')->label('Пункт')->maxLength(255)->required(),
                ])
                ->columnSpanFull(),
            TextInput::make('data_json.cta_label')->label('Текст CTA')->maxLength(120),
            TextInput::make('data_json.cta_anchor')->label('Якорь CTA (#id или URL)')->maxLength(255),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.founder_expert_bio';
    }

    public function previewSummary(array $data): string
    {
        return $this->stringPreview($data, 'heading', 50) ?: 'Био без заголовка';
    }
}
