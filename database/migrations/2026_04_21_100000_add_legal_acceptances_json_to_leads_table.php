<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table): void {
            if (! Schema::hasColumn('leads', 'legal_acceptances_json')) {
                $table->json('legal_acceptances_json')->nullable()->after('visitor_contact_channels_json');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table): void {
            if (Schema::hasColumn('leads', 'legal_acceptances_json')) {
                $table->dropColumn('legal_acceptances_json');
            }
        });
    }
};
