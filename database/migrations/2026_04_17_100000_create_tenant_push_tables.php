<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_push_settings')) {
            Schema::create('tenant_push_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('push_override', 32)->default('inherit_plan');
                $table->boolean('self_serve_allowed')->default(true);
                $table->boolean('commercial_service_active')->default(false);
                $table->string('setup_status', 32)->default('not_started');
                $table->string('provider_status', 32)->default('not_configured');
                $table->string('canonical_origin', 512)->nullable();
                $table->string('canonical_host', 255)->nullable();
                $table->string('onesignal_app_id', 64)->nullable();
                $table->text('onesignal_app_api_key_encrypted')->nullable();
                $table->string('onesignal_site_name', 255)->nullable();
                $table->timestamp('onesignal_config_verified_at')->nullable();
                $table->text('onesignal_last_verification_error')->nullable();
                $table->boolean('onesignal_key_pending_verification')->default(false);
                $table->timestamp('test_push_last_sent_at')->nullable();
                $table->string('test_push_last_result_status', 32)->nullable();
                $table->text('test_push_last_result_message')->nullable();
                $table->boolean('is_push_enabled')->default(false);
                $table->boolean('is_pwa_enabled')->default(false);
                $table->string('pwa_manifest_status', 32)->nullable();
                $table->string('pwa_worker_status', 32)->nullable();
                $table->string('pwa_name', 255)->nullable();
                $table->string('pwa_short_name', 64)->nullable();
                $table->string('pwa_theme_color', 16)->nullable();
                $table->string('pwa_background_color', 16)->nullable();
                $table->string('pwa_display', 32)->default('standalone');
                $table->string('pwa_start_url', 512)->nullable();
                $table->json('pwa_icons_json')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id']);
            });
        }

        if (! Schema::hasTable('tenant_push_event_preferences')) {
            Schema::create('tenant_push_event_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('event_key', 128);
                $table->boolean('is_enabled')->default(false);
                $table->string('delivery_mode', 32)->default('immediate');
                $table->string('recipient_scope', 32)->default('owner_only');
                $table->json('selected_user_ids_json')->nullable();
                $table->json('quiet_hours_json')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'event_key']);
            });
        }

        if (! Schema::hasTable('tenant_push_diagnostics')) {
            Schema::create('tenant_push_diagnostics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('check_type', 64);
                $table->string('status', 32);
                $table->string('code', 64)->nullable();
                $table->text('message')->nullable();
                $table->json('details_json')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'check_type'], 'tpush_diag_tenant_check');
            });
        }

        if (! Schema::hasTable('tenant_onesignal_push_identities')) {
            Schema::create('tenant_onesignal_push_identities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('external_user_id', 128);
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id'], 'tospi_tenant_user_uq');
                $table->index(['tenant_id', 'external_user_id'], 'tospi_tenant_extuid');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_onesignal_push_identities');
        Schema::dropIfExists('tenant_push_diagnostics');
        Schema::dropIfExists('tenant_push_event_preferences');
        Schema::dropIfExists('tenant_push_settings');
    }
};
