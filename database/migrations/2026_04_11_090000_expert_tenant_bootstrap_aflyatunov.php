<?php

use Database\Seeders\Tenant\AflyatunovExpertBootstrap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expert tenant aflyatunov: DDL (tenant_service_programs, reviews columns) + idempotent seed.
 * Test host: aflyatunov.local (avoid localhost — used by motolevins in other migrations).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createProgramsTable();
        $this->extendReviewsTable();
        $this->seedAflyatunovTenant();
    }

    public function down(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', 'aflyatunov')->value('id');
        if ($tenantId > 0) {
            DB::table('tenant_service_programs')->where('tenant_id', $tenantId)->delete();
            DB::table('page_sections')->where('tenant_id', $tenantId)->delete();
            DB::table('pages')->where('tenant_id', $tenantId)->delete();
            DB::table('form_configs')->where('tenant_id', $tenantId)->delete();
            DB::table('faqs')->where('tenant_id', $tenantId)->delete();
            DB::table('reviews')->where('tenant_id', $tenantId)->delete();
            DB::table('seo_meta')->where('tenant_id', $tenantId)->delete();
            DB::table('tenant_domains')->where('tenant_id', $tenantId)->delete();
            DB::table('tenants')->where('id', $tenantId)->delete();
        }

        if (Schema::hasTable('tenant_service_programs')) {
            Schema::dropIfExists('tenant_service_programs');
        }

        if (Schema::hasTable('reviews')) {
            try {
                Schema::table('reviews', function (Blueprint $table): void {
                    $table->dropIndex('reviews_tenant_category_idx');
                });
            } catch (Throwable) {
                // index may be absent in partial states
            }
        }

        foreach ([
            'category_key', 'headline', 'text_short', 'text_long',
            'media_type', 'video_url', 'meta_json',
        ] as $col) {
            if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', $col)) {
                Schema::table('reviews', function (Blueprint $table) use ($col): void {
                    $table->dropColumn($col);
                });
            }
        }
    }

    private function createProgramsTable(): void
    {
        if (Schema::hasTable('tenant_service_programs')) {
            return;
        }

        Schema::create('tenant_service_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 128);
            $table->string('title');
            $table->text('teaser')->nullable();
            $table->text('description')->nullable();
            $table->json('audience_json')->nullable();
            $table->json('outcomes_json')->nullable();
            $table->string('duration_label', 255)->nullable();
            $table->unsignedBigInteger('price_amount')->nullable();
            $table->string('price_prefix', 32)->nullable();
            $table->string('format_label', 255)->nullable();
            $table->string('program_type', 32);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_visible', 'sort_order']);
        });
    }

    private function extendReviewsTable(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('reviews', 'category_key')) {
                $table->string('category_key', 64)->nullable();
            }
            if (! Schema::hasColumn('reviews', 'headline')) {
                $table->string('headline', 255)->nullable();
            }
            if (! Schema::hasColumn('reviews', 'text_short')) {
                $table->text('text_short')->nullable();
            }
            if (! Schema::hasColumn('reviews', 'text_long')) {
                $table->text('text_long')->nullable();
            }
            if (! Schema::hasColumn('reviews', 'media_type')) {
                $table->string('media_type', 16)->default('text');
            }
            if (! Schema::hasColumn('reviews', 'video_url')) {
                $table->string('video_url', 2048)->nullable();
            }
            if (! Schema::hasColumn('reviews', 'meta_json')) {
                $table->json('meta_json')->nullable();
            }
        });

        if (Schema::hasColumn('reviews', 'category_key')) {
            Schema::table('reviews', function (Blueprint $table): void {
                $table->index(['tenant_id', 'category_key'], 'reviews_tenant_category_idx');
            });
        }
    }

    private function seedAflyatunovTenant(): void
    {
        AflyatunovExpertBootstrap::run();
    }
};
