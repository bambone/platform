<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domain_terms')) {
            Schema::create('domain_terms', function (Blueprint $table) {
                $table->id();
                $table->string('term_key')->unique();
                $table->string('group', 64)->index();
                $table->string('default_label');
                $table->text('description')->nullable();
                $table->string('value_type', 32)->default('text');
                $table->boolean('is_required')->default(true);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_editable_by_tenant')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('domain_localization_presets')) {
            Schema::create('domain_localization_presets', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('domain_localization_preset_terms')) {
            Schema::create('domain_localization_preset_terms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('preset_id')->constrained('domain_localization_presets')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('domain_terms')->cascadeOnDelete();
                $table->string('label');
                $table->string('short_label')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['preset_id', 'term_id']);
            });
        }

        if (! Schema::hasTable('tenant_term_overrides')) {
            Schema::create('tenant_term_overrides', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('domain_terms')->cascadeOnDelete();
                $table->string('label');
                $table->string('short_label')->nullable();
                $table->string('source', 64)->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'term_id']);
            });
        }

        if (Schema::hasTable('tenants') && ! Schema::hasColumn('tenants', 'domain_localization_preset_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->foreignId('domain_localization_preset_id')
                    ->nullable()
                    ->after('plan_id')
                    ->constrained('domain_localization_presets')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('domain_localization_presets')) {
            return;
        }

        $now = now();
        $presets = [
            ['slug' => 'generic_services', 'name' => 'Универсальные услуги', 'description' => 'Базовая нейтральная терминология.', 'sort_order' => 10],
            ['slug' => 'moto_rental', 'name' => 'Прокат мотоциклов', 'description' => null, 'sort_order' => 20],
            ['slug' => 'car_rental', 'name' => 'Прокат автомобилей', 'description' => null, 'sort_order' => 30],
            ['slug' => 'beauty_salon', 'name' => 'Салон / бьюти', 'description' => null, 'sort_order' => 40],
            ['slug' => 'instructor_booking', 'name' => 'Запись к инструктору', 'description' => null, 'sort_order' => 50],
            ['slug' => 'tool_rental', 'name' => 'Аренда инструмента', 'description' => null, 'sort_order' => 60],
            ['slug' => 'other', 'name' => 'Другое (универсальная база)', 'description' => 'Та же база, что у generic; клиент может переименовать термины в кабинете.', 'sort_order' => 100],
        ];

        foreach ($presets as $row) {
            $exists = DB::table('domain_localization_presets')->where('slug', $row['slug'])->exists();
            $payload = [
                'name' => $row['name'],
                'description' => $row['description'],
                'is_active' => true,
                'sort_order' => $row['sort_order'],
                'updated_at' => $now,
            ];
            if ($exists) {
                DB::table('domain_localization_presets')->where('slug', $row['slug'])->update($payload);
            } else {
                DB::table('domain_localization_presets')->insert(array_merge($payload, [
                    'slug' => $row['slug'],
                    'created_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'domain_localization_preset_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropConstrainedForeignId('domain_localization_preset_id');
            });
        }

        Schema::dropIfExists('tenant_term_overrides');
        Schema::dropIfExists('domain_localization_preset_terms');
        Schema::dropIfExists('domain_localization_presets');
        Schema::dropIfExists('domain_terms');
    }
};
