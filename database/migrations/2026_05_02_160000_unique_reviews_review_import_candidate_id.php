<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Одна связь Review ↔ кандидат импорта; блокирует двойное создание при гонках.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews') || ! Schema::hasColumn('reviews', 'review_import_candidate_id')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table): void {
            $table->unique('review_import_candidate_id', 'reviews_review_import_candidate_id_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique('reviews_review_import_candidate_id_unique');
        });
    }
};
