<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Plan::query()->where('slug', 'pro')->each(function (Plan $plan): void {
            $features = $plan->features_json ?? [];
            if (! in_array('web_push_onesignal', $features, true)) {
                $features[] = 'web_push_onesignal';
                $plan->update(['features_json' => $features]);
            }
        });
    }

    public function down(): void
    {
        Plan::query()->where('slug', 'pro')->each(function (Plan $plan): void {
            $features = array_values(array_filter(
                $plan->features_json ?? [],
                fn (string $f): bool => $f !== 'web_push_onesignal'
            ));
            $plan->update(['features_json' => $features]);
        });
    }
};
