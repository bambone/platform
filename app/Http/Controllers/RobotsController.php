<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $custom = Setting::get('seo.robots_txt', '');

        if ($custom !== '') {
            $content = trim($custom);
        } else {
            $content = implode("\n", [
                'User-agent: *',
                'Allow: /',
                'Disallow: /admin',
                'Disallow: /api',
                '',
                'Sitemap: '.url('/sitemap.xml'),
            ]);
        }

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
