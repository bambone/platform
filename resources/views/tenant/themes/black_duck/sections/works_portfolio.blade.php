{{--
  Black Duck /raboty: основная визуальная галерея. Lazy-load; без preload тяжёлых ассетов галереи.
--}}
@php
    use App\Tenant\BlackDuck\BlackDuckContentConstants;
    use App\Tenant\BlackDuck\BlackDuckProofDisplay;
    use App\Tenant\Expert\ExpertBrandMediaUrl;

    $d = is_array($data ?? null) ? $data : [];
    $rawItems = is_array($d['gallery_items'] ?? null) ? $d['gallery_items'] : [];
    $filters = is_array($d['filters'] ?? null) ? $d['filters'] : [];
    $heading = (string) ($d['heading'] ?? 'Портфолио');
    $intro = trim((string) ($d['intro'] ?? ''));
    $ctaLabel = trim((string) ($d['primary_cta_label'] ?? 'Заявка и расчёт'));
    $ctaHref = trim((string) ($d['primary_cta_href'] ?? BlackDuckContentConstants::PRIMARY_LEAD_URL));
    $showFilters = count($filters) > 1;
    $wrapId = 'bd-works-portfolio-wrap-'.(int) ($section->id ?? 0);
    $dialogId = 'bd-works-lightbox-'.(int) ($section->id ?? 0);

    $items = [];
    foreach ($rawItems as $it) {
        if (! is_array($it) || trim((string) ($it['image_url'] ?? '')) === '') {
            continue;
        }
        $items[] = $it;
    }
    $slides = [];
    foreach ($items as $it) {
        $path = trim((string) ($it['image_url'] ?? ''));
        $slides[] = [
            'src' => ExpertBrandMediaUrl::resolve($path),
            'alt' => BlackDuckProofDisplay::altForItem($it, null, tenant()?->id),
            'title' => trim((string) ($it['title'] ?? '')),
            'caption' => trim((string) ($it['summary'] ?? $it['task'] ?? $it['caption'] ?? '')),
        ];
    }
    $slidesJson = json_encode($slides, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp
@if ($items === [] || $slides === [])
@else
<section id="{{ e($wrapId) }}" class="bd-section" aria-labelledby="{{ e($wrapId) }}-h" data-bd-lightbox-root-id="{{ e($dialogId) }}">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 id="{{ e($wrapId) }}-h" class="text-2xl font-semibold text-[var(--ex-ink)]">{{ $heading }}</h2>
            @if ($intro !== '')
                <p class="mt-2 max-w-2xl text-sm text-zinc-400 sm:text-base">{{ $intro }}</p>
            @endif
        </div>
        @if ($ctaHref !== '' && $ctaLabel !== '')
            <a href="{{ e($ctaHref) }}" class="shrink-0 text-sm font-medium text-[#36C7FF] underline-offset-2 hover:underline">{{ e($ctaLabel) }}</a>
        @endif
    </div>

    @if ($showFilters)
        <div class="mt-6 flex flex-wrap gap-2" role="tablist" aria-label="Фильтр по направлению">
            @foreach ($filters as $f)
                @if (! is_array($f))
                    @continue
                @endif
                @php
                    $fv = (string) ($f['value'] ?? '');
                    $fl = (string) ($f['label'] ?? $fv);
                @endphp
                @if ($fv === '')
                    @continue
                @endif
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $fv === 'all' ? 'true' : 'false' }}"
                    data-bd-portfolio-filter="{{ e($fv) }}"
                    class="bd-portfolio-filter rounded-full border border-white/15 px-3 py-1.5 text-xs font-medium text-zinc-200 transition hover:bg-white/10 aria-selected:bg-[#36C7FF]/20 aria-selected:text-[#36C7FF]"
                >{{ e($fl) }}</button>
            @endforeach
        </div>
    @endif

    <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" role="list">
        @foreach ($items as $slideIdx => $it)
            @php
                $path = trim((string) ($it['image_url'] ?? ''));
                $fk = is_array($it['filter_keys'] ?? null) ? $it['filter_keys'] : [];
                $fkStr = e(implode(' ', $fk));
                $aspect = $it['aspect_ratio'] ?? null;
                $aspectCss = is_string($aspect) && $aspect !== '' ? $aspect : '4 / 3';
                $altItem = BlackDuckProofDisplay::altForItem($it, null, tenant()?->id);
                $srcset = trim((string) ($it['srcset'] ?? ''));
                $sizes = trim((string) ($it['sizes'] ?? ''));
                $title = trim((string) ($it['title'] ?? ''));
                $task = trim((string) ($it['task'] ?? ''));
                $badge = trim((string) ($it['badge'] ?? ''));
                $svcLabel = trim((string) ($it['service_label'] ?? ''));
            @endphp
            <li
                data-bd-portfolio-item
                data-bd-filters="{{ $fkStr }}"
                class="bd-portfolio-tile group flex flex-col overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04]"
            >
                <button
                    type="button"
                    class="text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#36C7FF]"
                    data-bd-lightbox-open="{{ (int) $slideIdx }}"
                    aria-haspopup="dialog"
                >
                    @include('tenant.themes.black_duck.components.proof_picture', [
                        'logicalPath' => $path,
                        'srcset' => $srcset,
                        'sizes' => $sizes,
                        'alt' => $altItem,
                        'aspectRatio' => $aspectCss,
                        'class' => 'transition duration-300 group-hover:scale-[1.02]',
                        'loading' => 'lazy',
                        'fetchpriority' => null,
                    ])
                </button>
                <div class="flex flex-1 flex-col gap-1 p-4">
                    @if ($badge !== '')
                        <span class="text-[0.65rem] font-semibold uppercase tracking-wide text-[#36C7FF]/90">{{ e($badge) }}</span>
                    @endif
                    @if ($svcLabel !== '')
                        <p class="text-xs text-zinc-500">{{ e($svcLabel) }}</p>
                    @endif
                    @if ($title !== '')
                        <p class="font-medium text-zinc-100">{{ e($title) }}</p>
                    @elseif ($task !== '')
                        <p class="font-medium text-zinc-100">{{ e($task) }}</p>
                    @endif
                    @if (! empty($it['summary']))
                        <p class="text-sm text-zinc-400">{{ e((string) $it['summary']) }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    <dialog id="{{ $dialogId }}" class="bd-works-lightbox fixed left-1/2 top-1/2 z-[200] w-[min(96vw,56rem)] max-h-[90vh] -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-white/15 bg-carbon p-0 text-zinc-100 shadow-2xl backdrop:bg-black/70">
        <div class="flex max-h-[90vh] flex-col">
            <div class="flex items-center justify-between gap-2 border-b border-white/10 px-4 py-3">
                <p class="min-w-0 truncate text-sm font-medium text-white" data-bd-lightbox-title></p>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm text-zinc-300 hover:bg-white/10" data-bd-lightbox-close autofocus>Закрыть</button>
            </div>
            <div class="relative flex flex-1 flex-col items-center justify-center gap-3 overflow-auto p-4">
                <img src="" alt="" class="max-h-[min(70vh,40rem)] w-auto max-w-full rounded-lg object-contain" data-bd-lightbox-img />
                <p class="max-w-prose text-center text-sm text-zinc-400" data-bd-lightbox-caption></p>
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg border border-white/15 px-3 py-2 text-sm hover:bg-white/5" data-bd-lightbox-prev aria-label="Предыдущее">←</button>
                    <button type="button" class="rounded-lg border border-white/15 px-3 py-2 text-sm hover:bg-white/5" data-bd-lightbox-next aria-label="Следующее">→</button>
                </div>
            </div>
        </div>
    </dialog>

    <script type="application/json" id="{{ $dialogId }}-slides">@php echo $slidesJson; @endphp</script>

    @push('tenant-scripts')
        <script>
            (function () {
                var root = document.getElementById('{{ e($wrapId) }}');
                if (!root) return;
                var dialogId = root.getAttribute('data-bd-lightbox-root-id') || '';
                if (!dialogId) return;
                var dialog = document.getElementById(dialogId);
                var slidesEl = document.getElementById(dialogId + '-slides');
                if (!dialog || !slidesEl) return;
                var slides;
                try { slides = JSON.parse(slidesEl.textContent || '[]'); } catch (e) { slides = []; }
                if (!slides.length) return;
                var idx = 0;
                var img = dialog.querySelector('[data-bd-lightbox-img]');
                var cap = dialog.querySelector('[data-bd-lightbox-caption]');
                var tit = dialog.querySelector('[data-bd-lightbox-title]');
                function render() {
                    var s = slides[idx];
                    if (!s || !img) return;
                    img.src = s.src;
                    img.alt = s.alt || '';
                    if (tit) tit.textContent = s.title || '';
                    if (cap) cap.textContent = s.caption || '';
                }
                function open(i) {
                    idx = Math.max(0, Math.min(slides.length - 1, i));
                    render();
                    dialog.showModal();
                }
                function close() { dialog.close(); }
                root.querySelectorAll('[data-bd-lightbox-open]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        open(parseInt(btn.getAttribute('data-bd-lightbox-open'), 10) || 0);
                    });
                });
                dialog.querySelector('[data-bd-lightbox-close]').addEventListener('click', close);
                dialog.querySelector('[data-bd-lightbox-prev]').addEventListener('click', function () {
                    idx = (idx - 1 + slides.length) % slides.length;
                    render();
                });
                dialog.querySelector('[data-bd-lightbox-next]').addEventListener('click', function () {
                    idx = (idx + 1) % slides.length;
                    render();
                });
                dialog.addEventListener('cancel', function (e) { e.preventDefault(); close(); });
                dialog.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') close();
                    if (e.key === 'ArrowLeft') { idx = (idx - 1 + slides.length) % slides.length; render(); }
                    if (e.key === 'ArrowRight') { idx = (idx + 1) % slides.length; render(); }
                });
                root.querySelectorAll('[data-bd-portfolio-filter]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var v = btn.getAttribute('data-bd-portfolio-filter') || 'all';
                        root.querySelectorAll('[data-bd-portfolio-filter]').forEach(function (b) {
                            b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
                        });
                        root.querySelectorAll('[data-bd-portfolio-item]').forEach(function (tile) {
                            var keys = (tile.getAttribute('data-bd-filters') || '').trim().split(/\s+/).filter(Boolean);
                            var show = v === 'all' || keys.indexOf(v) !== -1;
                            tile.classList.toggle('hidden', !show);
                        });
                    });
                });
            })();
        </script>
    @endpush
</section>
@endif
