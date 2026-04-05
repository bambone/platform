<?php

namespace App\Services\Seo;

use App\Models\Motorcycle;
use App\Models\Tenant;
use App\Services\Seo\Data\TenantSeoBootstrapData;

/**
 * Deterministic llms intro + entries (paths relative to tenant host).
 */
final class TenantLlmsTxtGenerator
{
    /**
     * @return array{intro: string, entries: list<array{path: string, summary: string}>}
     */
    public function generate(Tenant $tenant, TenantSeoBootstrapData $data): array
    {
        $max = (int) config('seo_autopilot.llms_max_entries', 10);
        if ($max < 1) {
            $max = 10;
        }

        $intro = $this->buildIntro($data);
        $entries = [];
        $seen = [];

        $candidates = $this->candidatePaths($tenant, $data);
        foreach ($candidates as $item) {
            if (count($entries) >= $max) {
                break;
            }
            $path = $item['path'];
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $summary = trim($item['summary']);
            if ($summary === '') {
                continue;
            }
            $entries[] = ['path' => $path, 'summary' => $summary];
        }

        return ['intro' => $intro, 'entries' => $entries];
    }

    private function buildIntro(TenantSeoBootstrapData $data): string
    {
        $name = $data->siteName !== '' ? $data->siteName : 'Site';
        $en = str_starts_with(strtolower($data->locale), 'en');

        $lines = [];
        if ($en) {
            $lines[] = $name.' — public website of the company.';
        } else {
            $lines[] = $name.' — публичный сайт компании.';
        }

        if ($data->catalogItemsCount > 0) {
            $lines[] = $en
                ? 'A public catalog is available on the site.'
                : 'На сайте доступен публичный каталог.';
        } elseif ($en) {
            $lines[] = 'Use the links below to browse key pages.';
        } else {
            $lines[] = 'Ниже — ссылки на основные разделы сайта.';
        }

        if ($data->faqPublishedCount > 0) {
            $lines[] = $en
                ? 'Published FAQ answers common questions.'
                : 'В разделе вопросов и ответов опубликованы материалы для клиентов.';
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<array{path: string, summary: string}>
     */
    private function candidatePaths(Tenant $tenant, TenantSeoBootstrapData $data): array
    {
        $en = str_starts_with(strtolower($data->locale), 'en');

        $rows = [
            [
                'path' => '/',
                'summary' => $en ? 'Home page.' : 'Главная страница сайта.',
            ],
        ];

        if ($data->catalogItemsCount > 0) {
            $this->pushRouteRow($rows, 'motorcycles.index', $en ? 'Vehicle catalog.' : 'Каталог техники.');
        }

        $this->pushRouteRow($rows, 'booking.index', $en ? 'Online booking flow.' : 'Онлайн-бронирование.');
        $this->pushRouteRow($rows, 'contacts', $en ? 'Contact options.' : 'Контакты и способы связи.');
        $this->pushRouteRow($rows, 'terms', $en ? 'Terms and policies.' : 'Правила и условия сервиса.');

        if ($data->faqPublishedCount > 0) {
            $this->pushRouteRow($rows, 'faq', $en ? 'Frequently asked questions.' : 'Частые вопросы и ответы.');
        }

        $this->pushRouteRow($rows, 'about', $en ? 'About the company.' : 'О компании.');

        foreach ($this->topMotorcyclePaths($tenant, 3) as $path) {
            $rows[] = [
                'path' => $path,
                'summary' => $en ? 'Vehicle detail page.' : 'Карточка техники.',
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{path: string, summary: string}>  $rows
     */
    private function pushRouteRow(array &$rows, string $routeName, string $summary): void
    {
        $path = TenantSeoSafeRoutePath::relativeOrNull($routeName);
        if ($path === null) {
            return;
        }
        $rows[] = ['path' => $path, 'summary' => $summary];
    }

    /**
     * @return list<string>
     */
    private function topMotorcyclePaths(Tenant $tenant, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $bikes = Motorcycle::query()
            ->where('tenant_id', $tenant->id)
            ->where('show_in_catalog', true)
            ->where('status', 'available')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get(['slug']);

        $out = [];
        foreach ($bikes as $m) {
            $slug = trim((string) $m->slug);
            if ($slug === '') {
                continue;
            }
            $out[] = '/moto/'.rawurlencode($slug);
        }

        return $out;
    }
}
