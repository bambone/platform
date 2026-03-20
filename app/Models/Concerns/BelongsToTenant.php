<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenant = \currentTenant();
            if ($tenant) {
                $table = (new static)->getTable();
                $builder->where($table.'.tenant_id', $tenant->id);
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && $tenant = \currentTenant()) {
                $model->tenant_id = $tenant->id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function withoutTenantScope(): Builder
    {
        return $this->withoutGlobalScope('tenant');
    }
}
