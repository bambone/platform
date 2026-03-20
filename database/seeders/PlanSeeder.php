<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        if (Plan::exists()) {
            return;
        }

        Plan::create([
            'name' => 'Lite',
            'slug' => 'lite',
            'limits_json' => ['max_models' => 10, 'max_leads_per_month' => 100],
            'features_json' => ['cms', 'catalog', 'leads', 'seo'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'limits_json' => ['max_models' => 50, 'max_leads_per_month' => 500],
            'features_json' => ['cms', 'catalog', 'leads', 'seo', 'booking_engine', 'custom_domain'],
            'sort_order' => 2,
            'is_active' => true,
        ]);
    }
}
