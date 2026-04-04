<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class TenantPublicPageController extends Controller
{
    /** @var list<string> */
    private const ALLOWED_LOGICAL = [
        'pages.about',
    ];

    public function show(string $logical): View
    {
        if (! in_array($logical, self::ALLOWED_LOGICAL, true)) {
            abort(404);
        }

        return tenant_view($logical);
    }
}
