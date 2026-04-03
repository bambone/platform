<?php

namespace Tests\Feature\Motorcycle;

use App\Models\Category;
use App\Models\Motorcycle;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class MotorcycleMediaDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenant(): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'M',
            'slug' => 'm',
            'status' => 'active',
        ]);
        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'm.apex.test',
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
            'verified_at' => now(),
            'activated_at' => now(),
        ]);
        Cache::flush();

        return $tenant->fresh();
    }

    public function test_soft_delete_keeps_media_row(): void
    {
        $tenant = $this->seedTenant();
        $cat = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'C',
            'slug' => 'c',
        ]);
        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $cat->id,
            'name' => 'Bike',
            'slug' => 'bike',
            'status' => 'available',
            'show_in_catalog' => true,
        ]);
        $m->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('cover');

        $media = $m->getFirstMedia('cover');
        $this->assertNotNull($media);
        $disk = $media->disk;
        $relative = $media->getPathRelativeToRoot();
        $this->assertTrue(Storage::disk($disk)->exists($relative), 'cover file should exist before soft delete');

        $m->delete();

        $this->assertSame(1, Media::query()->count());
        $this->assertTrue(Storage::disk($disk)->exists($relative), 'soft-deleted motorcycle should keep media file on disk (Spatie)');
    }

    public function test_force_delete_removes_media_rows(): void
    {
        $tenant = $this->seedTenant();
        $cat = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'C2',
            'slug' => 'c2',
        ]);
        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $cat->id,
            'name' => 'Bike2',
            'slug' => 'bike2',
            'status' => 'available',
            'show_in_catalog' => true,
        ]);
        $m->addMedia(UploadedFile::fake()->image('cover2.jpg'))->toMediaCollection('cover');

        $media = $m->getFirstMedia('cover');
        $this->assertNotNull($media);
        $disk = $media->disk;
        $relative = $media->getPathRelativeToRoot();
        $this->assertTrue(Storage::disk($disk)->exists($relative));

        $m->forceDelete();

        $this->assertSame(0, Media::query()->count());
        $this->assertFalse(Storage::disk($disk)->exists($relative), 'force delete should remove media file from storage');
    }

    public function test_force_delete_one_motorcycle_does_not_remove_other_bike_media_file(): void
    {
        $tenant = $this->seedTenant();
        $cat = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'C3',
            'slug' => 'c3',
        ]);
        $a = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $cat->id,
            'name' => 'A',
            'slug' => 'a',
            'status' => 'available',
            'show_in_catalog' => true,
        ]);
        $b = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $cat->id,
            'name' => 'B',
            'slug' => 'b',
            'status' => 'available',
            'show_in_catalog' => true,
        ]);
        $a->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('cover');
        $b->addMedia(UploadedFile::fake()->image('b.jpg'))->toMediaCollection('cover');

        $mediaA = $a->getFirstMedia('cover');
        $mediaB = $b->getFirstMedia('cover');
        $this->assertNotSame($mediaA->getPathRelativeToRoot(), $mediaB->getPathRelativeToRoot());

        $diskA = $mediaA->disk;
        $relA = $mediaA->getPathRelativeToRoot();
        $relB = $mediaB->getPathRelativeToRoot();

        $a->forceDelete();

        $this->assertFalse(Storage::disk($diskA)->exists($relA));
        $this->assertTrue(Storage::disk($mediaB->disk)->exists($relB));
        $this->assertSame(1, Media::query()->count());
    }
}
