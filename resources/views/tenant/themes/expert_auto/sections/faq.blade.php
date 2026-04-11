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
@endphp
@if(count($items) > 0)
<section class="expert-faq-mega mb-16 sm:mb-24">
    @if(filled($h))
        <h2 class="mb-8 text-balance text-[clamp(1.45rem,3.2vw,2.1rem)] font-bold tracking-tight text-white sm:mb-10">{{ $h }}</h2>
    @endif
    <dl class="space-y-2.5 sm:space-y-3.5">
        @foreach($items as $i => $item)
            @php $fid = 'expert-faq-'.$i; @endphp
            <div class="expert-faq-item rounded-2xl border border-white/[0.06] bg-white/[0.02] transition-colors hover:border-white/12">
                <dt>
                    <button type="button" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left sm:px-6 sm:py-[1.125rem]" aria-expanded="false" aria-controls="{{ $fid }}" data-expert-faq-toggle>
                        <span class="text-base font-semibold text-white sm:text-lg">{{ $item['question'] ?? '' }}</span>
                        <span class="expert-faq-chevron inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/10 text-moto-amber" aria-hidden="true">+</span>
                    </button>
                </dt>
                <dd id="{{ $fid }}" class="expert-faq-panel hidden border-t border-white/[0.05] px-5 pb-5 pt-0 sm:px-6 sm:pb-6">
                    <div class="pt-3 text-sm leading-relaxed text-silver/95 sm:pt-4 sm:text-base">{!! nl2br(e($item['answer'] ?? '')) !!}</div>
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
                var dd = btn.getAttribute('aria-controls');
                if (!dd) return;
                var panel = document.getElementById(dd);
                if (!panel) return;
                var row = btn.closest('.expert-faq-item');
                var chev = row ? row.querySelector('.expert-faq-chevron') : null;
                var open = !panel.classList.contains('hidden');
                document.querySelectorAll('.expert-faq-panel').forEach(function (p) {
                    if (p === panel) return;
                    p.classList.add('hidden');
                    var b = document.querySelector('[aria-controls="' + p.id + '"]');
                    if (b) b.setAttribute('aria-expanded', 'false');
                    var r = b ? b.closest('.expert-faq-item') : null;
                    var c = r ? r.querySelector('.expert-faq-chevron') : null;
                    if (c) c.textContent = '+';
                });
                if (open) {
                    panel.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                    if (chev) chev.textContent = '+';
                } else {
                    panel.classList.remove('hidden');
                    btn.setAttribute('aria-expanded', 'true');
                    if (chev) chev.textContent = '−';
                }
            });
        })();
    </script>
@endonce
@endif
