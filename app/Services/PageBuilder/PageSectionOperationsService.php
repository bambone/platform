<?php

namespace App\Services\PageBuilder;

use App\Models\Page;
use App\Models\PageSection;
use App\PageBuilder\Contacts\ContactsInfoDataService;
use App\PageBuilder\LegacySectionTypeResolver;
use App\PageBuilder\PageSectionKeyGenerator;
use App\PageBuilder\PageSectionTypeRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PageSectionOperationsService
{
    public function __construct(
        private readonly PageSectionTypeRegistry $registry,
        private readonly PageSectionKeyGenerator $keyGenerator,
        private readonly LegacySectionTypeResolver $legacyResolver,
    ) {}

    public function createTypedSection(Page $page, string $typeId, array $payload, ?int $tenantId, ?int $insertAfterSectionId = null): PageSection
    {
        $this->assertPageTenant($page, $tenantId);
        $themeKey = $this->themeKey();
        if (! $this->registry->get($typeId)->supportsTheme($themeKey)) {
            throw new RuntimeException('Section type is not supported for the current theme.');
        }
        if (! $this->registry->typeAllowedOnPage($typeId, $page, $themeKey)) {
            throw new RuntimeException('Section type is not allowed for this page.');
        }

        $blueprint = $this->registry->get($typeId);
        $key = $this->keyGenerator->next($page, $typeId);
        $dataJson = $this->normalizeDataJson($blueprint->defaultData(), $payload['data_json'] ?? []);
        if (in_array($typeId, ['contacts_info', 'contacts'], true)) {
            $dataJson = app(ContactsInfoDataService::class)->finalizeForPersistence($dataJson);
        }

        $sortOrder = $this->nextSortOrder($page);
        if ($insertAfterSectionId !== null) {
            $after = PageSection::query()
                ->where('page_id', $page->id)
                ->whereKey($insertAfterSectionId)
                ->where('section_key', '!=', 'main')
                ->first();
            if ($after === null) {
                throw new RuntimeException('Invalid insert position for section.');
            }
            $sortOrder = $this->sortOrderAfter($after);
        }

        return PageSection::query()->create([
            'tenant_id' => $page->tenant_id,
            'page_id' => $page->id,
            'section_key' => $key,
            'section_type' => $typeId,
            'title' => $payload['title'] ?? $blueprint->label(),
            'data_json' => $dataJson,
            'sort_order' => $sortOrder,
            'status' => $payload['status'] ?? 'published',
            'is_visible' => (bool) ($payload['is_visible'] ?? true),
        ]);
    }

    /**
     * @param  list<int|string>  $orderedSectionIds
     */
    public function reorderSections(Page $page, array $orderedSectionIds, ?int $tenantId): void
    {
        $this->assertPageTenant($page, $tenantId);

        $normalized = [];
        foreach ($orderedSectionIds as $id) {
            $normalized[] = (int) $id;
        }

        $existingIds = $page->sections()
            ->where('section_key', '!=', 'main')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $a = $existingIds;
        $b = $normalized;
        sort($a);
        sort($b);

        if ($a !== $b || count($normalized) !== count($existingIds)) {
            throw new RuntimeException('Invalid section order: IDs do not match page sections.');
        }

        DB::transaction(function () use ($page, $normalized): void {
            $order = 0;
            foreach ($normalized as $id) {
                $order += 10;
                PageSection::query()
                    ->where('page_id', $page->id)
                    ->whereKey($id)
                    ->where('section_key', '!=', 'main')
                    ->update(['sort_order' => $order]);
            }
        });
    }

    /**
     * @param  array{title?: string, status?: string, is_visible?: bool, block_title?: string}  $payload
     */
    public function patchSectionMeta(PageSection $section, array $payload, ?int $tenantId): void
    {
        $this->assertSectionTenant($section, $tenantId);
        $this->assertNotMain($section);

        $updates = [];
        if (array_key_exists('title', $payload)) {
            $updates['title'] = (string) $payload['title'];
        }
        if (array_key_exists('status', $payload)) {
            $status = (string) $payload['status'];
            if (! array_key_exists($status, PageSection::statuses())) {
                throw new RuntimeException('Invalid section status.');
            }
            $updates['status'] = $status;
        }
        if (array_key_exists('is_visible', $payload)) {
            $updates['is_visible'] = (bool) $payload['is_visible'];
        }
        if (array_key_exists('block_title', $payload)) {
            $typeId = $section->section_type;
            if (! is_string($typeId) || $typeId === '' || ! $this->registry->has($typeId)) {
                $typeId = $this->legacyResolver->effectiveTypeId($section);
            }
            $dataKey = match ($typeId) {
                'structured_text', 'text_section', 'contacts_info', 'content_faq' => 'title',
                'rich_text', 'gallery', 'hero' => 'heading',
                default => null,
            };
            if ($dataKey !== null && $this->registry->has($typeId)) {
                $data = is_array($section->data_json) ? $section->data_json : [];
                $data = ContactsInfoDataService::mergeDataJsonPreservingChannelList(
                    $this->registry->get($typeId)->defaultData(),
                    $data,
                );
                $raw = trim((string) $payload['block_title']);
                $data[$dataKey] = $raw === '' ? null : mb_substr($raw, 0, 255);
                $updates['data_json'] = $data;
            }
        }

        if ($updates === []) {
            return;
        }

        $section->update($updates);
    }

    public function updateTypedSection(PageSection $section, array $payload, ?int $tenantId): void
    {
        $this->assertSectionTenant($section, $tenantId);
        $this->assertNotMain($section);

        $typeId = $section->section_type;
        if (! is_string($typeId) || $typeId === '' || ! $this->registry->has($typeId)) {
            $typeId = $this->legacyResolver->effectiveTypeId($section);
        }
        $blueprint = $this->registry->get($typeId);
        $existing = is_array($section->data_json) ? $section->data_json : [];
        $base = ContactsInfoDataService::mergeDataJsonPreservingChannelList($blueprint->defaultData(), $existing);
        $dataJson = $this->normalizeDataJson($base, $payload['data_json'] ?? []);
        if (in_array($typeId, ['contacts_info', 'contacts'], true)) {
            $dataJson = app(ContactsInfoDataService::class)->finalizeForPersistence($dataJson);
        }

        $section->update([
            'title' => $payload['title'] ?? $section->title,
            'data_json' => $dataJson,
            'status' => $payload['status'] ?? $section->status,
            'is_visible' => array_key_exists('is_visible', $payload) ? (bool) $payload['is_visible'] : $section->is_visible,
            'section_type' => $typeId,
        ]);
    }

    public function duplicateSection(PageSection $section, ?int $tenantId): PageSection
    {
        $this->assertSectionTenant($section, $tenantId);
        $this->assertNotMain($section);

        $section->loadMissing('page');
        $page = $section->page;
        if ($page === null) {
            throw new RuntimeException('Page not found for section.');
        }

        $typeId = $section->section_type;
        if (! is_string($typeId) || $typeId === '' || ! $this->registry->has($typeId)) {
            $typeId = $this->legacyResolver->effectiveTypeId($section);
        }
        $key = $this->keyGenerator->next($page, $typeId);

        return PageSection::query()->create([
            'tenant_id' => $section->tenant_id,
            'page_id' => $section->page_id,
            'section_key' => $key,
            'section_type' => $typeId,
            'title' => ($section->title ?? '').' (копия)',
            'data_json' => is_array($section->data_json) ? $section->data_json : [],
            'sort_order' => $this->sortOrderAfter($section),
            'status' => $section->status,
            'is_visible' => $section->is_visible,
        ]);
    }

    public function deleteSection(PageSection $section, ?int $tenantId): void
    {
        $this->assertSectionTenant($section, $tenantId);
        $this->assertNotMain($section);
        $section->delete();
    }

    public function moveSectionUp(PageSection $section, ?int $tenantId): void
    {
        $this->assertSectionTenant($section, $tenantId);
        $this->assertNotMain($section);
        $this->swapWithAdjacent($section, -1);
    }

    public function moveSectionDown(PageSection $section, ?int $tenantId): void
    {
        $this->assertSectionTenant($section, $tenantId);
        $this->assertNotMain($section);
        $this->swapWithAdjacent($section, 1);
    }

    public function toggleVisibility(PageSection $section, ?int $tenantId): void
    {
        $this->assertSectionTenant($section, $tenantId);
        $this->assertNotMain($section);
        $section->update(['is_visible' => ! $section->is_visible]);
    }

    /**
     * Builder-visible sections for a page (excludes virtual primary `main`).
     *
     * @return Collection<int, PageSection>
     */
    public function listBuilderSections(Page $page): Collection
    {
        return $page->sections()
            ->where('section_key', '!=', 'main')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function themeKey(): string
    {
        $t = currentTenant();

        return $t?->themeKey() ?? 'default';
    }

    private function assertPageTenant(Page $page, ?int $tenantId): void
    {
        if ($tenantId === null || (int) $page->tenant_id !== $tenantId) {
            throw new AuthorizationException('Invalid tenant context for page.');
        }
    }

    private function assertSectionTenant(PageSection $section, ?int $tenantId): void
    {
        if ($tenantId === null || (int) $section->tenant_id !== $tenantId) {
            throw new AuthorizationException('Invalid tenant context for section.');
        }
    }

    private function assertNotMain(PageSection $section): void
    {
        if ($section->section_key === 'main') {
            throw new RuntimeException('The primary content section cannot be modified from the builder.');
        }
    }

    private function nextSortOrder(Page $page): int
    {
        $max = (int) $page->sections()->max('sort_order');

        return $max + 10;
    }

    private function sortOrderAfter(PageSection $section): int
    {
        $next = PageSection::query()
            ->where('page_id', $section->page_id)
            ->where('sort_order', '>', $section->sort_order)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($next === null) {
            return $section->sort_order + 10;
        }

        return (int) (($section->sort_order + $next->sort_order) / 2);
    }

    private function swapWithAdjacent(PageSection $section, int $direction): void
    {
        $query = PageSection::query()
            ->where('page_id', $section->page_id)
            ->where('section_key', '!=', 'main')
            ->orderBy('sort_order')
            ->orderBy('id');

        $rows = $query->get();
        $idx = $rows->search(fn (PageSection $s): bool => $s->is($section));
        if ($idx === false) {
            return;
        }
        $otherIdx = $idx + $direction;
        if ($otherIdx < 0 || $otherIdx >= $rows->count()) {
            return;
        }
        $other = $rows[$otherIdx];
        DB::transaction(function () use ($section, $other): void {
            $a = $section->sort_order;
            $b = $other->sort_order;
            $section->update(['sort_order' => $b]);
            $other->update(['sort_order' => $a]);
        });
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function normalizeDataJson(array $base, array $incoming): array
    {
        if ($incoming === []) {
            return $base;
        }

        return array_replace_recursive($base, $incoming);
    }
}
