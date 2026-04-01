<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Models\TenantSetting;
use App\Support\RussianPhone;

class MotorcycleController extends Controller
{
    public function show(string $slug)
    {
        $motorcycle = Motorcycle::where('slug', $slug)
            ->where('show_in_catalog', true)
            ->with(['category', 'media'])
            ->firstOrFail();

        $galleryUrls = [];
        if ($motorcycle->cover_url) {
            $galleryUrls[] = $motorcycle->cover_url;
        }
        foreach ($motorcycle->getMedia('gallery') as $media) {
            $galleryUrls[] = $media->getUrl();
        }
        $galleryUrls = array_values(array_unique($galleryUrls));

        $relatedQuery = Motorcycle::query()
            ->where('tenant_id', $motorcycle->tenant_id)
            ->where('show_in_catalog', true)
            ->where('status', 'available')
            ->where('id', '!=', $motorcycle->id)
            ->with(['category', 'media'])
            ->orderBy('sort_order');

        $related = (clone $relatedQuery)
            ->when($motorcycle->category_id, fn ($q) => $q->where('category_id', $motorcycle->category_id))
            ->limit(3)
            ->get();

        if ($related->count() < 3) {
            $more = $relatedQuery
                ->whereNotIn('id', $related->pluck('id'))
                ->limit(3 - $related->count())
                ->get();
            $related = $related->concat($more);
        }

        $tenantId = $motorcycle->tenant_id;
        $contactPhoneRaw = TenantSetting::getForTenant($tenantId, 'contacts.phone', '');
        $contactEmail = TenantSetting::getForTenant($tenantId, 'contacts.email', '');

        return tenant_view('pages.motorcycle', [
            'motorcycle' => $motorcycle,
            'seoMeta' => $motorcycle->seoMeta,
            'galleryUrls' => $galleryUrls,
            'relatedMotorcycles' => $related,
            'specGroups' => $motorcycle->specGroupsForPublic(),
            'detailContent' => $motorcycle->detailContentForView(),
            'contactTelHref' => RussianPhone::normalize($contactPhoneRaw),
            'contactEmail' => trim((string) $contactEmail),
        ]);
    }
}
