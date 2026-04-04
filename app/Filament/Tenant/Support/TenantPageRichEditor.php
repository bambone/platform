<?php

namespace App\Filament\Tenant\Support;

use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageArea;
use App\Support\Storage\TenantStorageDisks;
use Filament\Forms\Components\RichEditor;

/**
 * Единые настройки RichEditor для контента страниц тенанта: TipTap toolbar, таблицы, вложения.
 *
 * Вложения пишутся на диск {@see TenantStorageDisks::publicDiskName()} (S3/R2 — через TENANT_STORAGE_PUBLIC_DISK),
 * ключи вида {@code tenants/{id}/public/site/page-content/…} — см. {@see TenantStorage::publicPathInArea()}.
 */
final class TenantPageRichEditor
{
    /**
     * Группы кнопок панели (вставка таблицы, изображения, отмена — как в дефолте Filament).
     *
     * @return list<list<string>>
     */
    public static function toolbarButtonGroups(): array
    {
        return [
            ['bold', 'italic', 'underline', 'strike', 'link'],
            ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
            ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
            ['table', 'attachFiles', 'horizontalRule'],
            ['undo', 'redo'],
        ];
    }

    public static function enhance(RichEditor $editor, bool $withAttachmentHelp = true): RichEditor
    {
        $editor = $editor
            ->toolbarButtons(self::toolbarButtonGroups())
            ->resizableImages()
            ->fileAttachmentsDisk(fn (): string => TenantStorageDisks::publicDiskName())
            ->fileAttachmentsDirectory(function (): string {
                $tenant = currentTenant();
                if ($tenant === null) {
                    return TenantStorage::forTrusted(0)->publicPathInArea(TenantStorageArea::PublicSite, 'page-content');
                }

                return TenantStorage::forTrusted((int) $tenant->id)
                    ->publicPathInArea(TenantStorageArea::PublicSite, 'page-content');
            })
            ->fileAttachmentsVisibility('public');

        if ($withAttachmentHelp) {
            $editor = $editor->helperText(
                'Изображения: публичный диск тенанта (TENANT_STORAGE_PUBLIC_DISK, обычно S3/R2), путь tenants/{id}/public/site/page-content/. '.
                'Выделите картинку → «Прикрепить файл» — alt и замена файла; угол рамки — размер (если включено в теме админки).'
            );
        }

        return $editor;
    }
}
