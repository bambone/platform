<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_storage_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('base_quota_bytes');
            $table->unsignedBigInteger('extra_quota_bytes')->default(0);
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->unsignedBigInteger('reserved_bytes')->default(0);
            $table->string('status', 32)->default('ok')->index();
            $table->unsignedTinyInteger('warning_threshold_percent')->default(20);
            $table->unsignedTinyInteger('critical_threshold_percent')->default(10);
            $table->boolean('hard_stop_enabled')->default(true);
            $table->timestamp('last_recalculated_at')->nullable();
            $table->timestamp('last_synced_from_storage_at')->nullable();
            $table->json('last_scan_summary_json')->nullable();
            $table->timestamp('last_sync_error_at')->nullable();
            $table->text('last_sync_error_message')->nullable();
            $table->text('notes')->nullable();
            $table->string('storage_package_label', 255)->nullable();
            $table->timestamps();
        });

        $defaultBase = (int) config('tenant_storage_quotas.default_base_quota_bytes', 100 * 1024 * 1024);
        $warn = (int) config('tenant_storage_quotas.default_warning_threshold_percent', 20);
        $crit = (int) config('tenant_storage_quotas.default_critical_threshold_percent', 10);
        $hard = (bool) config('tenant_storage_quotas.default_hard_stop_enabled', true);
        $now = now();

        DB::table('tenants')->orderBy('id')->chunkById(100, function ($tenants) use ($defaultBase, $warn, $crit, $hard, $now): void {
            foreach ($tenants as $tenant) {
                $exists = DB::table('tenant_storage_quotas')->where('tenant_id', $tenant->id)->exists();
                if ($exists) {
                    continue;
                }
                DB::table('tenant_storage_quotas')->insert([
                    'tenant_id' => $tenant->id,
                    'base_quota_bytes' => $defaultBase,
                    'extra_quota_bytes' => 0,
                    'used_bytes' => 0,
                    'reserved_bytes' => 0,
                    'status' => 'ok',
                    'warning_threshold_percent' => $warn,
                    'critical_threshold_percent' => $crit,
                    'hard_stop_enabled' => $hard,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_storage_quotas');
    }
};
