<?php

use App\MediaPresentation\LegacyCoverObjectPositionParser;
use App\MediaPresentation\PresentationData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_service_programs')) {
            return;
        }

        Schema::table('tenant_service_programs', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_service_programs', 'cover_presentation_json')) {
                $table->json('cover_presentation_json')->nullable();
            }
        });

        if (! Schema::hasColumn('tenant_service_programs', 'cover_presentation_json')) {
            return;
        }

        $rows = DB::table('tenant_service_programs')
            ->select('id', 'cover_object_position', 'cover_presentation_json')
            ->whereNull('cover_presentation_json')
            ->get();

        foreach ($rows as $row) {
            $parsed = LegacyCoverObjectPositionParser::parse($row->cover_object_position ?? null);
            if ($parsed === null) {
                continue;
            }
            $map = [
                'default' => $parsed->toArray(),
                'mobile' => $parsed->toArray(),
                'desktop' => $parsed->toArray(),
            ];
            $payload = new PresentationData(PresentationData::CURRENT_VERSION, $map);
            DB::table('tenant_service_programs')->where('id', $row->id)->update([
                'cover_presentation_json' => json_encode($payload->toArray()),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_service_programs')) {
            return;
        }

        Schema::table('tenant_service_programs', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_service_programs', 'cover_presentation_json')) {
                $table->dropColumn('cover_presentation_json');
            }
        });
    }
};
