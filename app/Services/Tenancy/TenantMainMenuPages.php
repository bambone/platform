<?php

namespace App\Services\Tenancy;

use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Пункты верхнего меню публичного сайта тенанта (только CMS-страницы из БД).
 */
final class TenantMainMenuPages
{
    /**
     * @return Collection<int, array{label: string, url: string}>
     */
    public function menuItems(Tenant $tenant): Collection
    {
        $items = Page::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->where('show_in_main_menu', true)
            ->where('slug', '!=', 'home')
            ->orderBy('main_menu_sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Page $page): array {
                $slug = (string) $page->slug;
                $url = $slug === ''
                    ? url('/')
                    : url('/'.ltrim(str_replace('\\', '/', $slug), '/'));

                return [
                    'label' => $page->name,
                    'url' => $url,
                ];
            });

        if ($tenant->themeKey() === 'expert_pr') {
            $hasPublishedFaq = DB::table('faqs')
                ->where('tenant_id', $tenant->id)
                ->where('status', 'published')
                ->exists();
            if ($hasPublishedFaq) {
                $faqUrl = url('/faq');
                $hasFaq = $items->contains(static function (array $item) use ($faqUrl): bool {
                    $u = rtrim((string) ($item['url'] ?? ''), '/');
                    $f = rtrim($faqUrl, '/');

                    return $u === $f;
                });
                if (! $hasFaq) {
                    $faqItem = ['label' => 'FAQ', 'url' => $faqUrl];
                    $contactsIdx = $items->search(static function (array $item): bool {
                        return str_contains((string) ($item['url'] ?? ''), '/contacts');
                    });
                    if ($contactsIdx !== false) {
                        $items->splice((int) $contactsIdx, 0, [$faqItem]);
                    } else {
                        $items->push($faqItem);
                    }
                }
            }
        }

        return $items->values();
    }
}
