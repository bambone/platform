<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\Filament\Forms\Components\TenantPublicEditorialGalleryPoster;
use App\Filament\Forms\Components\TenantPublicMediaPicker;
use App\Filament\Tenant\PageBuilder\TeleportedEditorRepeater;
use App\PageBuilder\PageSectionCategory;
use App\Rules\EditorialGalleryAssetUrlRule;
use App\Rules\EditorialGalleryCaptionRule;
use App\Rules\EditorialGalleryMaterialSourceUrlRule;
use App\Tenant\Expert\VideoEmbedUrlNormalizer;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

final class EditorialGalleryBlueprint extends ExpertSectionBlueprint
{
    /** Подкаталог под {@code site/} для загрузок из редактора галереи (раздельно по типу медиа). */
    private const UPLOAD_SUBDIR_IMAGES = 'page-builder/editorial-gallery/images';

    private const UPLOAD_SUBDIR_VIDEOS = 'page-builder/editorial-gallery/videos';

    private const UPLOAD_SUBDIR_POSTERS = 'page-builder/editorial-gallery/posters';

    /** Сообщение валидации {@code embed_share_url} при невозможности разобрать ссылку (зафиксировано для тестов и UI). */
    public static function embedShareUrlFailureMessage(?string $embedProvider): string
    {
        return match ($embedProvider) {
            'youtube' => 'Для YouTube вставьте обычную ссылку на ролик (страница с видео в браузере).',
            'vk' => 'Для ВКонтакте вставьте ссылку на ролик (vk.com/video…), video_ext.php или код iframe из «Поделиться».',
            default => 'Не удалось разобрать ссылку для выбранного провайдера.',
        };
    }

    /** Сообщение при VK: короткая ссылка на страницу ролика или {@code video_ext} без {@code hash}. */
    public static function embedShareUrlVkMissingHashMessage(): string
    {
        return 'Для ВКонтакте вставьте URL из «Кода для вставки»: video_ext.php с параметром hash (или целиком iframe). Одной короткой ссылки на страницу ролика недостаточно.';
    }

    public function id(): string
    {
        return 'editorial_gallery';
    }

    public function label(): string
    {
        return 'Expert: Галерея';
    }

    public function description(): string
    {
        return 'Редакторская подборка кадров и видео. Превью и постеры предпочтительно загружать в хранилище сайта, а не hotlink. Внешние статьи — поле «Ссылка на материал».';
    }

    public function icon(): string
    {
        return 'heroicon-o-photo';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Content;
    }

    public function defaultData(): array
    {
        return [
            'section_heading' => '',
            'section_lead' => '',
            'items' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.section_heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            Textarea::make('data_json.section_lead')->label('Лид под заголовком')->rows(2)->columnSpanFull(),
            TeleportedEditorRepeater::make('data_json.items')
                ->label('Кадры и видео')
                ->defaultItems(0)
                ->addActionLabel('Добавить материал')
                ->addAction(function (Action $action): Action {
                    return TeleportedEditorRepeater::withFullLivewireRenderAfter(
                        $action->action(function (Repeater $component): void {
                            $newUuid = $component->generateUuid();
                            $items = $component->getRawState();
                            $seed = [
                                'media_kind' => 'image',
                                'source_new_tab' => true,
                            ];
                            if ($newUuid) {
                                $items[$newUuid] = $seed;
                            } else {
                                $items[] = $seed;
                            }
                            $component->rawState($items);
                            $component->getChildSchema($newUuid ?? array_key_last($items))->fill();
                            $component->collapsed(false, shouldMakeComponentCollapsible: false);
                            $component->callAfterStateUpdated();
                        })
                    );
                })
                ->schema([
                    Select::make('media_kind')
                        ->label('Тип')
                        ->options([
                            'image' => 'Фото',
                            'video' => 'Видео (файл MP4/WebM)',
                            'video_embed' => 'Видео (встраивание VK/YouTube)',
                        ])
                        ->default('image')
                        ->live(),
                    TenantPublicMediaPicker::make('image_url')
                        ->label('Изображение')
                        ->mediaType(TenantPublicMediaPicker::MEDIA_IMAGE)
                        ->maxLength(2048)
                        ->allowEmpty(fn ($get): bool => ($get('media_kind') ?? '') !== 'image')
                        ->uploadPublicSiteSubdirectory(self::UPLOAD_SUBDIR_IMAGES)
                        ->visible(fn ($get): bool => ($get('media_kind') ?? '') === 'image')
                        ->helperText('Выберите файл из хранилища сайта или загрузите новый. Ручной путь или URL — только в редких случаях (кнопка «Указать вручную»).')
                        ->rules([
                            fn ($get): array => ($get('media_kind') ?? '') === 'image'
                                ? [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_IMAGE)]
                                : [],
                        ]),
                    TenantPublicMediaPicker::make('video_url')
                        ->label('Видеофайл')
                        ->mediaType(TenantPublicMediaPicker::MEDIA_VIDEO)
                        ->maxLength(2048)
                        ->allowEmpty(fn ($get): bool => ($get('media_kind') ?? '') !== 'video')
                        ->uploadPublicSiteSubdirectory(self::UPLOAD_SUBDIR_VIDEOS)
                        ->visible(fn ($get): bool => ($get('media_kind') ?? '') === 'video')
                        ->helperText('Выберите видеофайл MP4 или WebM из хранилища или загрузите новый. Ссылка на страницу VK/YouTube здесь не работает — выберите тип «Видео (встраивание)».')
                        ->rules([
                            fn ($get): array => ($get('media_kind') ?? '') === 'video'
                                ? [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_VIDEO_FILE)]
                                : [],
                        ]),
                    Select::make('embed_provider')
                        ->label('Где размещено видео')
                        ->options([
                            'youtube' => 'YouTube',
                            'vk' => 'ВКонтакте',
                        ])
                        ->visible(fn ($get): bool => ($get('media_kind') ?? '') === 'video_embed')
                        ->required(fn ($get): bool => ($get('media_kind') ?? '') === 'video_embed')
                        ->live()
                        ->rules([
                            fn ($get): array => ($get('media_kind') ?? '') === 'video_embed'
                                ? ['required', 'in:youtube,vk']
                                : [],
                        ]),
                    TextInput::make('embed_share_url')
                        ->label('Ссылка на видео')
                        ->maxLength(2048)
                        ->live(onBlur: true)
                        ->placeholder(function (Get $get): ?string {
                            return match ($get('embed_provider')) {
                                'vk' => 'Лучше: URL из src в «Коде для вставки» (vkvideo.ru/video_ext.php?…&hash=…) или целиком iframe',
                                default => null,
                            };
                        })
                        ->visible(fn ($get): bool => ($get('media_kind') ?? '') === 'video_embed')
                        ->required(fn ($get): bool => ($get('media_kind') ?? '') === 'video_embed')
                        ->hintIcon('heroicon-o-information-circle')
                        ->hintIconTooltip(function (Get $get): string {
                            return match ($get('embed_provider')) {
                                'vk' => 'Ссылка вида vk.com/video-… или vkvideo.ru/video-… открывается в браузере, но на стороннем сайте плееру VK обычно нужен адрес video_ext.php с параметром hash (его даёт только «Код для вставки»). Можно вставить целиком iframe — мы возьмём src.',
                                'youtube' => 'Обычная ссылка на ролик или фрагмент iframe — из него будет взят адрес из src.',
                                default => 'Сначала выберите площадку — текст подсказки обновится.',
                            };
                        })
                        ->helperText(function (Get $get): string {
                            $vkBase = 'Для стабильного встраивания используйте «Поделиться» → «Код для вставки»: вставьте весь iframe или скопируйте только значение src=… (домен vk.com или vkvideo.ru — не меняйте вручную).';
                            $vkWarn = ' Сейчас у вас короткая ссылка на страницу ролика без hash — в модалке сайта часто будет «Видеофайл не найден». Замените на ссылку из src=… с hash=… или на iframe.';
                            return match ($get('embed_provider')) {
                                'vk' => $vkBase.(VideoEmbedUrlNormalizer::vkEmbedProbablyMissingHash((string) ($get('embed_share_url') ?? '')) ? $vkWarn : ''),
                                'youtube' => 'Вставьте обычную ссылку на видео.',
                                default => 'Сначала выберите площадку (YouTube или ВКонтакте) — подсказка под полем обновится.',
                            };
                        })
                        ->dehydrateStateUsing(function (?string $state): ?string {
                            if ($state === null) {
                                return null;
                            }

                            return VideoEmbedUrlNormalizer::normalizeVkShareUrlForStorage($state);
                        })
                        ->rules([
                            fn ($get): array => ($get('media_kind') ?? '') === 'video_embed'
                                ? [
                                    function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                        $v = VideoEmbedUrlNormalizer::extractShareUrlFromPaste(trim((string) $value));
                                        if ($v === '') {
                                            return;
                                        }
                                        $p = (string) ($get('embed_provider') ?? '');
                                        if ($p === 'vk' && VideoEmbedUrlNormalizer::vkEmbedProbablyMissingHash($v)) {
                                            $fail(self::embedShareUrlVkMissingHashMessage());

                                            return;
                                        }
                                        if (VideoEmbedUrlNormalizer::toIframeSrc($p, $v) === null) {
                                            $fail(self::embedShareUrlFailureMessage($p !== '' ? $p : null));
                                        }
                                    },
                                ]
                                : [],
                        ]),
                    TenantPublicEditorialGalleryPoster::make('poster_url')
                        ->label('Обложка видео')
                        ->maxLength(2048)
                        ->uploadPublicSiteSubdirectory(self::UPLOAD_SUBDIR_POSTERS)
                        ->visible(fn ($get): bool => in_array($get('media_kind'), ['video', 'video_embed'], true))
                        ->helperText(fn ($get): ?string => ($get('media_kind') ?? '') === 'video'
                            ? 'По желанию. Делает превью в сетке ровнее; только изображение, без HTML и iframe.'
                            : null)
                        ->rules([
                            fn ($get): array => in_array($get('media_kind'), ['video', 'video_embed'], true)
                                ? [new EditorialGalleryAssetUrlRule(EditorialGalleryAssetUrlRule::KIND_POSTER)]
                                : [],
                        ]),
                    TextInput::make('caption')
                        ->label('Подпись')
                        ->maxLength(255)
                        ->helperText('Обычный текст, без HTML и без копипаста из кода страницы.')
                        ->rules([new EditorialGalleryCaptionRule]),
                    TextInput::make('source_url')
                        ->label('Ссылка на материал (источник)')
                        ->maxLength(2048)
                        ->live(onBlur: true)
                        ->helperText('Только полный URL с https:// или http://. Относительные пути, «протокол-relative» (//…), якоря (#…), mailto и tel не используются.')
                        ->rules([new EditorialGalleryMaterialSourceUrlRule]),
                    TextInput::make('source_label')
                        ->label('Текст ссылки на источник')
                        ->maxLength(120)
                        ->placeholder('Читать материал')
                        ->visible(fn ($get): bool => trim((string) ($get('source_url') ?? '')) !== '')
                        ->helperText('Например: «Открыть источник», «Читать на сайте ФПА». Пусто — подпись по умолчанию.'),
                    Toggle::make('source_new_tab')
                        ->label('Открывать источник в новой вкладке')
                        ->default(true)
                        ->visible(fn ($get): bool => trim((string) ($get('source_url') ?? '')) !== '')
                        ->inline(false),
                ])
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.editorial_gallery';
    }

    public function previewSummary(array $data): string
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $nImage = 0;
        $nVideo = 0;
        $nEmbed = 0;
        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }
            $k = trim((string) ($row['media_kind'] ?? ''));
            if ($k === '') {
                $k = trim((string) ($row['video_url'] ?? '')) !== '' ? 'video' : 'image';
            }
            match ($k) {
                'video_embed' => $nEmbed++,
                'video' => $nVideo++,
                default => $nImage++,
            };
        }
        $n = $nImage + $nVideo + $nEmbed;
        if ($n === 0) {
            return 'Нет материалов';
        }
        $word = match (true) {
            $n % 10 === 1 && $n % 100 !== 11 => 'материал',
            in_array($n % 10, [2, 3, 4], true) && ! in_array($n % 100, [12, 13, 14], true) => 'материала',
            default => 'материалов',
        };
        $parts = [];
        if ($nImage > 0) {
            $parts[] = $nImage.' фото';
        }
        if ($nVideo > 0) {
            $parts[] = $nVideo.' видео';
        }
        if ($nEmbed > 0) {
            $parts[] = self::embeddedVideosLabel($nEmbed);
        }

        return $n.' '.$word.': '.implode(', ', $parts);
    }

    private static function embeddedVideosLabel(int $n): string
    {
        $phrase = ($n % 10 === 1 && $n % 100 !== 11)
            ? 'встроенное видео'
            : 'встроенных видео';

        return $n.' '.$phrase;
    }
}
