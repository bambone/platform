<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\Filament\Forms\Components\TenantPublicImagePicker;
use App\Filament\Forms\Components\TenantPublicMediaPicker;
use App\Filament\Tenant\PageBuilder\FramingCoverFocalEditor;
use App\Filament\Tenant\PageBuilder\TeleportedEditorRepeater;
use App\MediaPresentation\FramingPresentationSummaryResolver;
use App\MediaPresentation\MediaPresentationRegistry;
use App\MediaPresentation\PresentationData;
use App\MediaPresentation\Profiles\PageHeroCoverPresentationProfile;
use App\MediaPresentation\ViewportFraming;
use App\MediaPresentation\ViewportKey;
use App\PageBuilder\BlueprintFramingSlotDescriptor;
use App\PageBuilder\PageSectionCategory;
use App\PageBuilder\PageSectionFormWirePath;
use App\Support\Storage\TenantPublicAssetResolver;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

final class ExpertHeroBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'expert_hero';
    }

    public function label(): string
    {
        return 'Expert: Hero';
    }

    public function description(): string
    {
        return 'Главный блок эксперта: заголовки, CTA, слот медиа.';
    }

    public function icon(): string
    {
        return 'heroicon-o-sparkles';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Basic;
    }

    /**
     * Metadata: image framing slots (profile id is not stored in section JSON — only framing state).
     *
     * @return list<BlueprintFramingSlotDescriptor>
     */
    public function framingSlotDescriptors(): array
    {
        return [
            new BlueprintFramingSlotDescriptor(
                slotKey: 'hero_background',
                imageField: 'hero_image_url',
                presentationField: 'hero_background_presentation',
                profileSlotId: PageHeroCoverPresentationProfile::SLOT_ID,
                editorMode: 'inline',
            ),
        ];
    }

    public function defaultData(): array
    {
        return [
            'heading' => '',
            'subheading' => '',
            'description' => '',
            'primary_cta_label' => '',
            'primary_cta_anchor' => '',
            'secondary_cta_label' => '',
            'secondary_cta_anchor' => '',
            'trust_badges' => [],
            'hero_eyebrow' => '',
            'hero_image_slot' => null,
            'hero_image_url' => '',
            'hero_image_alt' => '',
            'overlay_dark' => true,
            'hero_video_url' => '',
            'hero_video_poster_url' => '',
            'video_trigger_label' => '',
            'hero_focal_sync_all_viewports' => false,
            'hero_background_presentation' => PresentationData::empty()->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $dataJson  merged blueprint defaults + section data (editor state)
     * @param  array<string, mixed>|null  $storageSnapshot  raw {@code data_json} from DB (before merge), for legacy {@code hero_focal_sync_mobile_desktop}
     * @return array<string, mixed>
     */
    public static function normalizeHeroPresentationForEditor(array $dataJson, ?array $storageSnapshot = null): array
    {
        $defaults = (new self)->defaultData();
        $merged = array_replace_recursive($defaults, $dataJson);
        $hp = is_array($merged['hero_background_presentation'] ?? null) ? $merged['hero_background_presentation'] : [];
        $rawMap = is_array($hp['viewport_focal_map'] ?? null) ? $hp['viewport_focal_map'] : [];
        $hadTablet = array_key_exists(ViewportKey::Tablet->value, $rawMap);
        $hadMobile = array_key_exists(ViewportKey::Mobile->value, $rawMap);
        $map = $rawMap;
        $sMin = PageHeroCoverPresentationProfile::FRAMING_SCALE_MIN;
        $sMax = PageHeroCoverPresentationProfile::FRAMING_SCALE_MAX;
        $sStep = PageHeroCoverPresentationProfile::FRAMING_SCALE_STEP;
        foreach ([ViewportKey::Mobile->value, ViewportKey::Tablet->value, ViewportKey::Desktop->value] as $vk) {
            $row = $map[$vk] ?? null;
            $vf = ViewportFraming::fromArray(is_array($row) ? $row : null, $sMin, $sMax, $sStep);
            if ($vf === null) {
                $def = PageHeroCoverPresentationProfile::defaultFocalForViewport(ViewportKey::from($vk));
                $vf = ViewportFraming::normalized(
                    $def->x,
                    $def->y,
                    PageHeroCoverPresentationProfile::FRAMING_SCALE_DEFAULT,
                    null,
                    $sMin,
                    $sMax,
                    $sStep,
                );
            }
            $map[$vk] = $vf->toArray();
        }
        // Legacy hero: только mobile+desktop → tablet исторически шёл по mobile; не подставляем tablet default 50/50.
        if (! $hadTablet && $hadMobile) {
            $map[ViewportKey::Tablet->value] = $map[ViewportKey::Mobile->value];
        }
        $hp['viewport_focal_map'] = $map;
        $merged['hero_background_presentation'] = PresentationData::fromArray($hp, $sMin, $sMax, $sStep)->toArray();

        if (is_array($storageSnapshot)) {
            if (array_key_exists('hero_focal_sync_all_viewports', $storageSnapshot)) {
                $merged['hero_focal_sync_all_viewports'] = (bool) ($merged['hero_focal_sync_all_viewports'] ?? false);
            } else {
                $merged['hero_focal_sync_all_viewports'] = (bool) ($storageSnapshot['hero_focal_sync_mobile_desktop'] ?? false);
            }
        } elseif (array_key_exists('hero_focal_sync_all_viewports', $dataJson)) {
            $merged['hero_focal_sync_all_viewports'] = (bool) ($merged['hero_focal_sync_all_viewports'] ?? false);
        } else {
            $merged['hero_focal_sync_all_viewports'] = (bool) ($merged['hero_focal_sync_mobile_desktop'] ?? false);
        }
        unset($merged['hero_focal_sync_mobile_desktop']);

        return $merged;
    }

    public function formComponents(): array
    {
        $profile = MediaPresentationRegistry::profile(PageHeroCoverPresentationProfile::SLOT_ID);
        $summaryResolver = app(FramingPresentationSummaryResolver::class);
        $wirePrefix = PageSectionFormWirePath::presentationWirePathPrefix('hero_background_presentation');

        return [
            TextInput::make('data_json.heading')->label('Заголовок')->maxLength(500)->columnSpanFull(),
            Textarea::make('data_json.subheading')->label('Подзаголовок')->rows(2)->columnSpanFull(),
            TextInput::make('data_json.hero_eyebrow')
                ->label('Строка над заголовком (eyebrow)')
                ->maxLength(120)
                ->helperText('Например: «Адвокат • Челябинск». Пусто — подставится дефолт по теме.')
                ->columnSpanFull(),
            Textarea::make('data_json.description')->label('Описание')->rows(3)->columnSpanFull(),
            TextInput::make('data_json.primary_cta_label')->label('Текст основной CTA')->maxLength(120),
            TextInput::make('data_json.primary_cta_anchor')->label('Якорь основной CTA (#id)')->maxLength(120),
            TextInput::make('data_json.secondary_cta_label')->label('Текст второй CTA')->maxLength(120),
            TextInput::make('data_json.secondary_cta_anchor')->label('Якорь второй CTA (#id)')->maxLength(120),
            TeleportedEditorRepeater::make('data_json.trust_badges')
                ->label('Бейджи доверия')
                ->addActionLabel('Добавить бейдж')
                ->schema([
                    TextInput::make('text')->label('Текст')->maxLength(255)->required(),
                ])
                ->columnSpanFull(),
            Toggle::make('data_json.overlay_dark')->label('Тёмный оверлей на фоне'),
            TenantPublicImagePicker::make('data_json.hero_image_url')
                ->label('Фото hero')
                ->uploadPublicSiteSubdirectory('site/page-builder/hero')
                ->helperText('Выберите файл из медиатеки или загрузите новый. Один источник для всех ширин; разные кадры для mobile, tablet и desktop задаются ниже (кадрирование и «Синхронизировать все размеры»).')
                ->live()
                ->columnSpanFull(),
            Toggle::make('data_json.hero_focal_sync_all_viewports')
                ->label('Синхронизировать все размеры')
                ->helperText('Включите, чтобы mobile, tablet и desktop менялись вместе. Выключите — настроить каждый размер отдельно.')
                ->default(false)
                ->live()
                ->columnSpanFull(),
            Placeholder::make('hero_framing_summary')
                ->hiddenLabel()
                ->content(function (Get $get) use ($summaryResolver, $profile): HtmlString {
                    $row = $get('data_json.hero_background_presentation');
                    $summ = $summaryResolver->summarize(is_array($row) ? $row : null, $profile);

                    return new HtmlString(
                        '<div class="space-y-1">'
                        .'<p class="text-sm font-medium text-gray-700 dark:text-gray-300">'.e('Кадрирование фона').'</p>'
                        .'<p class="text-sm text-gray-600 dark:text-gray-400">'.e($summ['label']).'</p>'
                        .'</div>'
                    );
                })
                ->dehydrated(false)
                ->columnSpanFull(),
            Hidden::make('data_json.hero_background_presentation.version')
                ->default(PresentationData::CURRENT_VERSION)
                ->dehydrated(),
            FramingCoverFocalEditor::make(
                'hero_background_framing_preview',
                'data_json.hero_background_presentation',
                $profile,
                $wirePrefix,
                resolveDesktopImageUrl: function (Get $get): ?string {
                    $t = currentTenant();
                    if ($t === null) {
                        return null;
                    }
                    $raw = trim((string) ($get('data_json.hero_image_url') ?? ''));

                    return TenantPublicAssetResolver::resolveForTenantModel($raw !== '' ? $raw : null, $t);
                },
                resolveMobileImageUrl: function (Get $get): ?string {
                    $t = currentTenant();
                    if ($t === null) {
                        return null;
                    }
                    $raw = trim((string) ($get('data_json.hero_image_url') ?? ''));

                    return TenantPublicAssetResolver::resolveForTenantModel($raw !== '' ? $raw : null, $t);
                },
                resolveSyncDefault: fn (Get $get): bool => (bool) ($get('data_json.hero_focal_sync_all_viewports') ?? $get('data_json.hero_focal_sync_mobile_desktop') ?? false),
            ),
            Grid::make(['default' => 1, 'lg' => 3])
                ->extraAttributes([
                    'data-svc-focal-numeric-extras' => 'true',
                    'class' => 'svc-hero-focal-numeric-grid items-stretch gap-4 [&_.fi-fieldset]:flex [&_.fi-fieldset]:min-h-0 [&_.fi-fieldset]:h-full [&_.fi-fieldset]:flex-col',
                ])
                ->schema([
                    Fieldset::make('Mobile · до 767 px')
                        ->schema([
                            TextInput::make('data_json.hero_background_presentation.viewport_focal_map.mobile.x')
                                ->label('X %')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.1)
                                ->required()
                                ->live(debounce: 400),
                            TextInput::make('data_json.hero_background_presentation.viewport_focal_map.mobile.y')
                                ->label('Y %')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.1)
                                ->required()
                                ->live(debounce: 400),
                            TextInput::make('data_json.hero_background_presentation.viewport_focal_map.mobile.scale')
                                ->label('Zoom (множитель)')
                                ->numeric()
                                ->minValue(PageHeroCoverPresentationProfile::FRAMING_SCALE_MIN)
                                ->maxValue(PageHeroCoverPresentationProfile::FRAMING_SCALE_MAX)
                                ->step(PageHeroCoverPresentationProfile::FRAMING_SCALE_STEP)
                                ->required()
                                ->live(debounce: 400),
                        ])
                        ->columns(3)
                        ->compact(),
                    Fieldset::make('Tablet · 768–1023 px')
                        ->schema([
                            TextInput::make('data_json.hero_background_presentation.viewport_focal_map.tablet.x')
                                ->label('X %')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.1)
                                ->required()
                                ->live(debounce: 400),
                            TextInput::make('data_json.hero_background_presentation.viewport_focal_map.tablet.y')
                                ->label('Y %')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.1)
                                ->required()
                                ->live(debounce: 400),
                            TextInput::make('data_json.hero_background_presentation.viewport_focal_map.tablet.scale')
                                ->label('Zoom (множитель)')
                                ->numeric()
                                ->minValue(PageHeroCoverPresentationProfile::FRAMING_SCALE_MIN)
                                ->maxValue(PageHeroCoverPresentationProfile::FRAMING_SCALE_MAX)
                                ->step(PageHeroCoverPresentationProfile::FRAMING_SCALE_STEP)
                                ->required()
                                ->live(debounce: 400),
                        ])
                        ->columns(3)
                        ->compact(),
                    Fieldset::make('Desktop · от 1024 px')
                        ->schema([
                            TextInput::make('data_json.hero_background_presentation.viewport_focal_map.desktop.x')
                                ->label('X %')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.1)
                                ->required()
                                ->live(debounce: 400),
                            TextInput::make('data_json.hero_background_presentation.viewport_focal_map.desktop.y')
                                ->label('Y %')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.1)
                                ->required()
                                ->live(debounce: 400),
                            TextInput::make('data_json.hero_background_presentation.viewport_focal_map.desktop.scale')
                                ->label('Zoom (множитель)')
                                ->numeric()
                                ->minValue(PageHeroCoverPresentationProfile::FRAMING_SCALE_MIN)
                                ->maxValue(PageHeroCoverPresentationProfile::FRAMING_SCALE_MAX)
                                ->step(PageHeroCoverPresentationProfile::FRAMING_SCALE_STEP)
                                ->required()
                                ->live(debounce: 400),
                        ])
                        ->columns(3)
                        ->compact(),
                ])
                ->columnSpanFull(),
            TextInput::make('data_json.hero_image_alt')
                ->label('Alt-текст фото')
                ->maxLength(255)
                ->columnSpanFull(),
            TenantPublicMediaPicker::make('data_json.hero_video_url')
                ->label('Видео для модалки (MP4/WebM)')
                ->mediaType(TenantPublicMediaPicker::MEDIA_VIDEO)
                ->maxLength(2048)
                ->uploadPublicSiteSubdirectory('site/page-builder/hero')
                ->helperText('Выберите файл из медиатеки, загрузите ролик или укажите внешний https-URL. Когда задано — в hero появится кнопка, откроется плеер.')
                ->columnSpanFull(),
            TenantPublicImagePicker::make('data_json.hero_video_poster_url')
                ->label('Постер для видео (превью)')
                ->uploadPublicSiteSubdirectory('site/page-builder/hero')
                ->helperText('Обложка в модалке; по желанию.')
                ->columnSpanFull(),
            TextInput::make('data_json.video_trigger_label')
                ->label('Текст кнопки видео')
                ->maxLength(120)
                ->helperText('Показывается, если задан URL видео. Пусто — на сайте подставится нейтральное «Смотреть видео». Для обучения можно ввести свой вариант (например «Смотреть, как проходят занятия»).')
                ->placeholder('Смотреть видео'),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.expert_hero';
    }

    public function previewSummary(array $data): string
    {
        $h = $this->stringPreview($data, 'heading', 60);

        return $h !== '' ? $h : 'Пустой hero';
    }
}
