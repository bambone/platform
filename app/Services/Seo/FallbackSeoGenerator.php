<?php

namespace App\Services\Seo;

use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\TenantSetting;

final class FallbackSeoGenerator
{
    public function siteName(Tenant $tenant): string
    {
        $name = trim((string) TenantSetting::getForTenant($tenant->id, 'general.site_name', ''));

        if ($name !== '') {
            return $name;
        }

        return $tenant->defaultPublicSiteName();
    }

    /**
     * @return array{title: string, description: string, h1: string}
     */
    public function forRouteOnly(Tenant $tenant, string $routeName): array
    {
        $site = $this->siteName($tenant);

        return [
            'title' => $site,
            'description' => '',
            'h1' => $site,
        ];
    }

    /**
     * @return array{title: string, description: string, h1: string}
     */
    public function forPage(Tenant $tenant, Page $page): array
    {
        $site = $this->siteName($tenant);
        $pageName = trim((string) $page->name) ?: $page->slug;
        $title = $site === '' ? $pageName : $site.' — '.$pageName;
        $plain = $this->mainSectionPlainText($page);
        $description = $this->excerptFromPlain($plain, 160);
        if ($description === '') {
            $description = $this->excerptFromPlain($pageName, 160);
        }

        return [
            'title' => $title,
            'description' => $description,
            'h1' => $pageName,
        ];
    }

    /**
     * @return array{title: string, description: string, h1: string}
     */
    public function forMotorcycle(Tenant $tenant, Motorcycle $moto): array
    {
        $site = $this->siteName($tenant);
        $name = trim((string) $moto->name) ?: (string) $moto->slug;
        $title = $site === '' ? $name : $name.' — '.$site;

        $plain = TenantSeoMerge::isFilled($moto->short_description)
            ? strip_tags((string) $moto->short_description)
            : (TenantSeoMerge::isFilled($moto->full_description)
                ? strip_tags((string) $moto->full_description)
                : '');
        if ($plain === '') {
            $card = $moto->catalogCardForView();
            $bits = array_filter([
                trim((string) ($card['positioning'] ?? '')),
                trim((string) ($card['scenario'] ?? '')),
            ]);
            $plain = implode(' ', $bits);
        }
        $description = $this->excerptFromPlain($plain, 160);
        if ($description === '') {
            $description = $this->excerptFromPlain('Аренда '.$name.' у '.$site.'.', 160);
        }

        return [
            'title' => $title,
            'description' => $description,
            'h1' => $name,
        ];
    }

    private function mainSectionPlainText(Page $page): string
    {
        $section = PageSection::query()
            ->where('page_id', $page->id)
            ->where('tenant_id', $page->tenant_id)
            ->where('section_key', 'main')
            ->where('status', 'published')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($section === null || ! is_array($section->data_json)) {
            return '';
        }

        $content = $section->data_json['content'] ?? null;
        if (! is_string($content) || $content === '') {
            return '';
        }

        return strip_tags($content);
    }

    private function excerptFromPlain(string $plain, int $maxLen): string
    {
        $plain = preg_replace('/\s+/u', ' ', trim($plain)) ?? '';
        if ($plain === '') {
            return '';
        }
        if (mb_strlen($plain) <= $maxLen) {
            return $plain;
        }

        return rtrim(mb_substr($plain, 0, $maxLen - 1)).'…';
    }
}
