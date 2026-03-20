<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;

class MotorcycleController extends Controller
{
    public function show(string $slug)
    {
        $motorcycle = Motorcycle::where('slug', $slug)
            ->where('show_in_catalog', true)
            ->firstOrFail();

        return view('pages.motorcycle', [
            'motorcycle' => $motorcycle,
            'seoMeta' => $motorcycle->seoMeta,
        ]);
    }
}
