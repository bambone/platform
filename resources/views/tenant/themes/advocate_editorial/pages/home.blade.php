@php
    $sections = $sections ?? [];
    $homeLayoutSections = $homeLayoutSections ?? collect();
@endphp
@extends('tenant.layouts.app')

@section('content')
    <div class="expert-home w-full min-w-0 pb-24 lg:pb-8">
        {{-- Отступ под фиксированную шапку (h 4.5rem / 5rem / 5.5rem) + небольшой зазор --}}
        <div
            class="expert-home-main mx-auto max-w-[min(88rem,calc(100vw-1.5rem))] px-3 pt-[calc(3.75rem+0.5rem)] sm:px-4 md:px-8 md:pt-[calc(5rem+0.75rem)] lg:px-12 lg:pt-[calc(5.5rem+1rem)]"
            data-expert-home-lazy-root="1"
        >
            @forelse ($homeLayoutSections as $section)
                @php
                    $sk = (string) ($section->section_key ?? '');
                    $skClass = $sk !== '' ? 'expert-home-section--'.preg_replace('/[^a-z0-9_-]+/i', '-', $sk) : 'expert-home-section--unknown';
                    $slotVars = [
                        'section' => $section,
                        'bikes' => $bikes ?? collect(),
                        'badges' => $badges ?? [],
                        'faqs' => $faqs ?? collect(),
                        'reviews' => $reviews ?? collect(),
                    ];
                @endphp
                @if ($loop->first)
                    <div class="expert-home-section expert-home-section--eager {{ $skClass }}" data-section-key="{{ e($sk) }}">
                        @include('tenant.pages.partials.home-section-slot', $slotVars)
                    </div>
                @else
                    <template id="expert-home-section-tpl-{{ (int) $section->id }}">
                        <div class="expert-home-section expert-home-section--lazy-mounted {{ $skClass }}" data-section-key="{{ e($sk) }}">
                            @include('tenant.pages.partials.home-section-slot', $slotVars)
                        </div>
                    </template>
                    <div
                        class="expert-home-section__lazy-host {{ $skClass }}"
                        id="expert-home-section-host-{{ (int) $section->id }}"
                        data-expert-lazy-template="expert-home-section-tpl-{{ (int) $section->id }}"
                        data-expert-lazy-order="{{ (int) $loop->index }}"
                        aria-busy="true"
                    >
                        <div class="expert-home-section__skeleton" aria-hidden="true">
                            <span class="expert-home-section__skeleton-shimmer"></span>
                        </div>
                    </div>
                @endif
            @empty
                <p class="text-center text-silver">Главная страница ещё не настроена.</p>
            @endforelse
        </div>
    </div>
@endsection

@push('tenant-scripts')
    <script>
        (function () {
            var root = document.querySelector('[data-expert-home-lazy-root="1"]');
            if (!root) {
                return;
            }
            var hosts = Array.prototype.slice.call(root.querySelectorAll('[data-expert-lazy-template]'));
            if (hosts.length === 0) {
                return;
            }
            hosts.sort(function (a, b) {
                return Number(a.getAttribute('data-expert-lazy-order') || 0) - Number(b.getAttribute('data-expert-lazy-order') || 0);
            });

            function mountFromTemplate(host) {
                var tid = host.getAttribute('data-expert-lazy-template');
                if (!tid) {
                    return false;
                }
                var tpl = document.getElementById(tid);
                if (!tpl || !tpl.content) {
                    return false;
                }
                var inner = tpl.content.firstElementChild;
                if (!inner) {
                    return false;
                }
                host.replaceWith(inner);
                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                    try {
                        window.Alpine.initTree(inner);
                    } catch (e) {}
                }
                inner.removeAttribute('aria-busy');
                document.dispatchEvent(new CustomEvent('rentbase:tenant-dom-mounted', { detail: { root: inner } }));
                return true;
            }

            var next = 0;
            function observeNext() {
                if (next >= hosts.length) {
                    return;
                }
                var host = hosts[next];
                if (!host.isConnected) {
                    next++;
                    observeNext();
                    return;
                }
                var io = new IntersectionObserver(
                    function (entries, obs) {
                        if (!entries[0] || !entries[0].isIntersecting) {
                            return;
                        }
                        obs.disconnect();
                        mountFromTemplate(host);
                        next++;
                        observeNext();
                    },
                    { root: null, rootMargin: '220px 0px 320px 0px', threshold: 0.01 }
                );
                io.observe(host);
            }
            observeNext();
        })();
    </script>
@endpush
