<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Services\Tenancy\TenantViewResolver;

class MotorcycleController extends Controller
{
    public function __construct(
        private readonly TenantViewResolver $tenantViews,
    ) {}

    public function show(string $slug)
    {
        $motorcycle = Motorcycle::where('slug', $slug)
            ->where('show_in_catalog', true)
            ->firstOrFail();

        return view($this->tenantViews->resolve('pages.motorcycle'), [
            'motorcycle' => $motorcycle,
            'seoMeta' => $motorcycle->seoMeta,
        ]);
    }
}
