<?php

namespace Tests\Unit\PageBuilder;

use App\PageBuilder\Blueprints\Expert\EditorialGalleryBlueprint;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EditorialGalleryBlueprintPreviewSummaryTest extends TestCase
{
    #[Test]
    public function preview_summary_uses_neutral_material_word_and_breakdown(): void
    {
        $bp = new EditorialGalleryBlueprint;
        $summary = $bp->previewSummary([
            'items' => [
                ['media_kind' => 'image'],
                ['media_kind' => 'video'],
                ['media_kind' => 'video_embed'],
            ],
        ]);
        $this->assertSame('3 материала: 1 фото, 1 видео, 1 встроенное видео', $summary);
    }

    #[Test]
    public function preview_summary_singular_material(): void
    {
        $bp = new EditorialGalleryBlueprint;
        $this->assertSame('1 материал: 1 фото', $bp->previewSummary([
            'items' => [['media_kind' => 'image']],
        ]));
    }

    #[Test]
    public function preview_summary_empty(): void
    {
        $bp = new EditorialGalleryBlueprint;
        $this->assertSame('Нет материалов', $bp->previewSummary(['items' => []]));
    }

    #[Test]
    public function preview_summary_plural_embedded_videos(): void
    {
        $bp = new EditorialGalleryBlueprint;
        $summary = $bp->previewSummary([
            'items' => [
                ['media_kind' => 'video_embed'],
                ['media_kind' => 'video_embed'],
            ],
        ]);
        $this->assertSame('2 материала: 2 встроенных видео', $summary);
    }

    #[Test]
    public function preview_summary_counts_external_article(): void
    {
        $bp = new EditorialGalleryBlueprint;
        $summary = $bp->previewSummary([
            'items' => [
                ['media_kind' => 'image'],
                ['media_kind' => 'external_article'],
            ],
        ]);
        $this->assertSame('2 материала: 1 фото, 1 внешний материал', $summary);
    }
}
