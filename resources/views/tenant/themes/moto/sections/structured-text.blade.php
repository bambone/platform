@php
    $title = isset($data['title']) ? trim((string) $data['title']) : '';
    $content = $data['content'] ?? '';
    $maxWidth = $data['max_width'] ?? 'prose';
    $widthClass = match ($maxWidth) {
        'wide' => 'max-w-3xl',
        'full' => 'max-w-none',
        default => 'max-w-none',
    };
    $displayTitle = $title !== '' ? $title : trim((string) ($section->title ?? ''));
    if ($displayTitle === '') {
        $displayTitle = 'Раздел';
    }
    $iconSvg = '<svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
    $calloutIcon = '<svg class="h-6 w-6 shrink-0 text-moto-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
    $isContactsCallout = isset($page) && $page->slug === 'contacts';
@endphp
@if(! filled($content))
@else
@if($isContactsCallout)
    <aside id="section-{{ $section->id }}" class="scroll-mt-28 md:scroll-mt-32 mt-14 w-full min-w-0 rounded-2xl border border-white/10 border-l-[3px] border-l-moto-amber bg-gradient-to-br from-obsidian/90 to-carbon/60 p-6 shadow-lg shadow-black/30 ring-1 ring-inset ring-white/5 sm:mt-16 sm:p-8 lg:mt-20">
        <div class="flex items-start gap-4">
            <span class="mt-1 shrink-0" aria-hidden="true">{!! $calloutIcon !!}</span>
            <div class="min-w-0 flex-1">
                @if($displayTitle !== '' && $displayTitle !== 'Раздел')
                    <h2 class="mb-3 text-lg font-semibold leading-tight tracking-tight text-white sm:text-xl">{{ $displayTitle }}</h2>
                @endif
                <x-tenant.rich-prose variant="callout" :content="$content" />
            </div>
        </div>
    </aside>
@else
<div class="{{ $widthClass }} mx-auto w-full min-w-0">
    <x-custom-pages.terms.policy-section-card
        id="section-{{ $section->id }}"
        :title="$displayTitle"
        :icon="$iconSvg"
        :content="$content"
    />
</div>
@endif
@endif
