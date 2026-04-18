<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_subscriptions', function (Blueprint $table) {
            $table->unique(
                ['calendar_connection_id', 'external_calendar_id'],
                'calendar_subscriptions_connection_external_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('calendar_subscriptions', function (Blueprint $table) {
            $table->dropUnique('calendar_subscriptions_connection_external_unique');
        });
    }
};
