<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Contracts\View\View;

class TenantPublicFaqController extends Controller
{
    public function __invoke(): View
    {
        abort_if(tenant() === null, 404);

        $faqs = Faq::query()
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return tenant_view('pages.faq', [
            'faqs' => $faqs,
        ]);
    }
}
