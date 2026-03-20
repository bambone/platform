<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\TemplatePreset;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TemplateCloningService
{
    public function cloneToTenant(Tenant $tenant, TemplatePreset $preset): void
    {
        DB::transaction(function () use ($tenant, $preset) {
            $config = $preset->config_json ?? [];
            $defaultPages = $config['default_pages'] ?? $this->getDefaultPagesConfig();
            $defaultSections = $config['default_sections'] ?? [];

            foreach ($defaultPages as $pageSlug => $pageData) {
                $page = Page::withoutGlobalScope('tenant')->create([
                    'tenant_id' => $tenant->id,
                    'name' => $pageData['name'] ?? ucfirst($pageSlug),
                    'slug' => $pageSlug,
                    'template' => $pageData['template'] ?? 'default',
                    'status' => $pageData['status'] ?? 'published',
                ]);

                $sections = $defaultSections[$pageSlug] ?? $this->getDefaultSectionsForPage($pageSlug);
                foreach ($sections as $index => $sectionData) {
                    PageSection::withoutGlobalScope('tenant')->create([
                        'tenant_id' => $tenant->id,
                        'page_id' => $page->id,
                        'section_key' => $sectionData['section_key'],
                        'section_type' => $sectionData['section_type'] ?? null,
                        'title' => $sectionData['title'] ?? null,
                        'data_json' => $sectionData['data_json'] ?? [],
                        'sort_order' => $sectionData['sort_order'] ?? $index * 10,
                        'is_visible' => $sectionData['is_visible'] ?? true,
                        'status' => $sectionData['status'] ?? 'published',
                    ]);
                }
            }
        });
    }

    protected function getDefaultPagesConfig(): array
    {
        return [
            'home' => ['name' => 'Главная', 'template' => 'default', 'status' => 'published'],
            'contacts' => ['name' => 'Контакты', 'template' => 'default', 'status' => 'published'],
        ];
    }

    protected function getDefaultSectionsForPage(string $pageSlug): array
    {
        if ($pageSlug === 'home') {
            return [
                ['section_key' => 'hero', 'title' => 'Hero', 'data_json' => [
                    'heading' => 'Аренда мотоциклов',
                    'subheading' => 'от 4 000 ₽/сутки',
                    'description' => 'Без скрытых платежей, экипировка и страховка включены',
                ], 'sort_order' => 0],
                ['section_key' => 'fleet_block', 'title' => 'Автопарк', 'data_json' => [
                    'heading' => 'Наш автопарк',
                    'subheading' => 'Выберите технику',
                ], 'sort_order' => 20],
                ['section_key' => 'why_us', 'title' => 'Почему мы', 'data_json' => ['items' => []], 'sort_order' => 30],
                ['section_key' => 'how_it_works', 'title' => 'Как это работает', 'data_json' => ['items' => []], 'sort_order' => 40],
                ['section_key' => 'final_cta', 'title' => 'CTA', 'data_json' => [], 'sort_order' => 100],
            ];
        }

        return [];
    }
}
