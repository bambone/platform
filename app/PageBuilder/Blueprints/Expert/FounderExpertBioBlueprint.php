<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\Filament\Forms\Components\TenantPublicImagePicker;
use App\Filament\Tenant\PageBuilder\TeleportedEditorRepeater;
use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

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
            'cta_goal_prefill' => '',
            'cta_repeat_after_trust' => true,
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            Textarea::make('data_json.lead')->label('Лид')->rows(2)->columnSpanFull(),
            TeleportedEditorRepeater::make('data_json.paragraphs')
                ->label('Абзацы')
                ->addActionLabel('Добавить абзац')
                ->schema([
                    Textarea::make('text')->label('Текст')->rows(3)->required()->columnSpanFull(),
                ])
                ->columnSpanFull(),
            TenantPublicImagePicker::make('data_json.portrait_image_url')
                ->label('Портрет')
                ->uploadPublicSiteSubdirectory('site/page-builder/founder-bio')
                ->columnSpanFull(),
            TextInput::make('data_json.portrait_image_alt')
                ->label('Alt портрета')
                ->maxLength(255)
                ->columnSpanFull(),
            static::makeSectionHtmlIdTextInput(),
            TeleportedEditorRepeater::make('data_json.trust_points')
                ->label('Маркеры доверия')
                ->addActionLabel('Добавить маркер')
                ->schema([
                    TextInput::make('text')->label('Пункт')->maxLength(255)->required(),
                ])
                ->columnSpanFull(),
            TextInput::make('data_json.cta_label')->label('Текст CTA')->maxLength(120),
            TextInput::make('data_json.cta_anchor')->label('Якорь при режиме «прокрутка» (#expert-inquiry)')->maxLength(255),
            Textarea::make('data_json.cta_goal_prefill')
                ->label('Текст цели в модалке / после якоря')
                ->rows(2)
                ->maxLength(500)
                ->helperText('Короткая формулировка для поля «цель обращения» при записи с этой страницы.')
                ->columnSpanFull(),
            Toggle::make('data_json.cta_repeat_after_trust')
                ->label('Повторить CTA после блока доверия')
                ->default(true),
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
