<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operator CRM fields + canonical statuses.
 *
 * Status remap (legacy → new):
 * - contacted → contact_attempted
 * - lost, spam → rejected
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_requests', function (Blueprint $table) {
            $table->string('priority', 32)->default('normal');
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('last_commented_at')->nullable();
            $table->text('internal_summary')->nullable();
        });

        // Remap legacy status values before code expects new constants.
        DB::table('crm_requests')->where('status', 'contacted')->update(['status' => 'contact_attempted']);
        DB::table('crm_requests')->whereIn('status', ['lost', 'spam'])->update(['status' => 'rejected']);

        Schema::table('crm_request_notes', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false);
        });

        Schema::table('crm_requests', function (Blueprint $table) {
            $table->index('status');
            $table->index('priority');
            $table->index('next_follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::table('crm_requests', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['next_follow_up_at']);
        });

        Schema::table('crm_request_notes', function (Blueprint $table) {
            $table->dropColumn('is_pinned');
        });

        DB::table('crm_requests')->where('status', 'contact_attempted')->update(['status' => 'contacted']);
        DB::table('crm_requests')->where('status', 'rejected')->update(['status' => 'lost']);
        DB::table('crm_requests')->where('status', 'awaiting_reply')->update(['status' => 'in_review']);

        Schema::table('crm_requests', function (Blueprint $table) {
            $table->dropColumn([
                'priority',
                'next_follow_up_at',
                'first_viewed_at',
                'processed_at',
                'last_commented_at',
                'internal_summary',
            ]);
        });
    }
};
