<?php

use App\Models\Motorcycle;
use App\Support\MotorcycleLegacyCoverImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('motorcycles', 'cover_image')) {
            return;
        }

        Motorcycle::query()->withoutGlobalScopes()->orderBy('id')->chunkById(50, function ($motorcycles): void {
            foreach ($motorcycles as $motorcycle) {
                /** @var Motorcycle $motorcycle */
                try {
                    MotorcycleLegacyCoverImporter::importToCoverCollectionIfMissing(
                        $motorcycle,
                        $motorcycle->getAttributes()['cover_image'] ?? null
                    );
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });

        Schema::table('motorcycles', function (Blueprint $table): void {
            $table->dropColumn('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('motorcycles', function (Blueprint $table): void {
            $table->string('cover_image')->nullable();
        });
    }
};
