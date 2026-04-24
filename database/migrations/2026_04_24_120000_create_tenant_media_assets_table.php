<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_media_assets', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('catalog_key', 80);

            $table->string('role', 64)->index();
            $table->string('logical_path', 512);
            $table->string('poster_logical_path', 512)->nullable();

            $table->string('service_slug', 128)->nullable()->index();
            $table->string('page_slug', 128)->nullable()->index();
            $table->string('before_after_group', 128)->nullable()->index();
            $table->string('works_group', 128)->nullable()->index();

            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_featured')->default(false)->index();

            $table->string('title', 255)->nullable();
            $table->string('caption', 255)->nullable();
            $table->string('summary', 255)->nullable();
            $table->string('alt', 255)->nullable();
            $table->string('service_label', 255)->nullable();

            $table->json('tags_json')->nullable();
            $table->string('aspect_hint', 64)->nullable();
            $table->string('display_variant', 64)->nullable();
            $table->string('badge', 64)->nullable();
            $table->string('cta_label', 128)->nullable();
            $table->string('kind', 64)->nullable();
            $table->string('source_ref', 255)->nullable();

            $table->boolean('show_on_home')->nullable()->index();
            $table->boolean('show_on_works')->nullable()->index();
            $table->boolean('show_on_service')->nullable()->index();

            $table->json('derivatives_json')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'catalog_key'], 'tenant_media_assets_tenant_catalog_key_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_media_assets');
    }
};

