@php
    $h = $data['section_heading'] ?? ($data['heading'] ?? '');
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    if ($items === [] && tenant() && ($data['source'] ?? null) === 'faqs_table') {
        $items = \App\Models\Faq::query()
            ->where('show_on_home', true)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($faq) => ['question' => $faq->question, 'answer' => $faq->answer])
            ->all();
    }
    if ($items === [] && tenant() && ($data['source'] ?? null) === 'faqs_table_service') {
        $cat = trim((string) ($data['faq_category'] ?? ''));
        if ($cat !== '') {
            $items = \App\Models\Faq::query()
                ->where('category', $cat)
                ->where('status', 'published')
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($faq) => ['question' => $faq->question, 'answer' => $faq->answer])
                ->all();
        }
    }
    $faqIdPrefix = (isset($section) && $section instanceof \App\Models\PageSection && (int) $section->id > 0)
        ? 'expert-faq-ps-'.(int) $section->id
        : 'expert-faq-'.substr(hash('sha256', json_encode($items, JSON_UNESCAPED_UNICODE)."\n".($h ?? '')), 0, 12);
@endphp
@if(count($items) > 0)
<section class="expert-faq-mega relative mb-14 min-w-0 sm:mb-20 lg:mb-28" data-expert-faq-scope>
    @if(filled($h))
        <h2 class="expert-section-title mb-8 max-w-4xl text-balance text-[clamp(1.65rem,4vw,3rem)] font-bold leading-[1.12] tracking-tight text-white/95 sm:mb-10 sm:leading-[1.1] lg:mb-12 lg:w-2/3">{{ $h }}</h2>
    @endif
    <dl class="expert-faq-list mx-auto min-w-0 max-w-5xl space-y-2 sm:space-y-4 lg:space-y-5 xl:max-w-6xl">
        @foreach($items as $i => $item)
            @php $fid = $faqIdPrefix.'-'.$i; @endphp
            <div class="expert-faq-item overflow-hidden rounded-[1.15rem] border border-white/[0.05] bg-white/[0.015] backdrop-blur-sm transition-all duration-300 hover:border-white/[0.1] hover:bg-white/[0.03] sm:rounded-[1.5rem]">
                <dt>
                    <button type="button" class="group flex w-full min-w-0 min-h-[3.25rem] items-center justify-between gap-3 px-4 py-3.5 text-left sm:min-h-0 sm:gap-5 sm:px-8 sm:py-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber" aria-expanded="false" aria-controls="{{ $fid }}" data-expert-faq-toggle>
                        <span class="min-w-0 flex-1 text-[1rem] font-bold leading-snug tracking-wide text-white/95 sm:text-[1.05rem] md:text-[1.15rem]">{{ $item['question'] ?? '' }}</span>
                        <span class="expert-faq-chevron relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-moto-amber/10 text-moto-amber ring-1 ring-inset ring-moto-amber/30 transition-transform duration-300 group-hover:bg-moto-amber/20 sm:h-10 sm:w-10" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-6-6h12" class="expert-faq-icon-line transition-transform duration-300"/></svg>
                        </span>
                    </button>
                </dt>
                <dd id="{{ $fid }}" class="expert-faq-panel hidden px-4 pb-5 pt-0 sm:px-8 sm:pb-8">
                    <div class="border-t border-white/[0.06] pt-4 sm:pt-6">
                        <div class="max-w-4xl text-[15px] font-medium leading-[1.75] text-silver/85 text-pretty sm:text-[16px]">{!! nl2br(e($item['answer'] ?? '')) !!}</div>
                    </div>
                </dd>
            </div>
        @endforeach
    </dl>
</section>

@once('expert-faq-accordion')
    <script>
        (function () {
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-expert-faq-toggle]');
                if (!btn) return;
                var scope = btn.closest('[data-expert-faq-scope]');
                if (!scope) return;
                var dd = btn.getAttribute('aria-controls');
                if (!dd) return;
                var panel = document.getElementById(dd);
                if (!panel || !scope.contains(panel)) return;
                var row = btn.closest('.expert-faq-item');
                var chev = row ? row.querySelector('.expert-faq-chevron') : null;
                var open = !panel.classList.contains('hidden');
                scope.querySelectorAll('.expert-faq-panel').forEach(function (p) {
                    if (p === panel) return;
                    p.classList.add('hidden');
                    var b = scope.querySelector('[aria-controls="' + p.id + '"]');
                    if (b) b.setAttribute('aria-expanded', 'false');
                    var r = b ? b.closest('.expert-faq-item') : null;
                    var prevChev = r ? r.querySelector('.expert-faq-chevron') : null;
                    var prevPath = prevChev ? prevChev.querySelector('svg path') : null;
                    if (prevPath) prevPath.setAttribute('d', 'M12 6v12m-6-6h12');
                });
                if (open) {
                    panel.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                    var svg = chev ? chev.querySelector('svg path') : null;
                    if (svg) svg.setAttribute('d', 'M12 6v12m-6-6h12');
                } else {
                    panel.classList.remove('hidden');
                    btn.setAttribute('aria-expanded', 'true');
                    var svg = chev ? chev.querySelector('svg path') : null;
                    if (svg) svg.setAttribute('d', 'M18 12H6');
                }
            });
        })();
    </script>
@endonce
@endif
