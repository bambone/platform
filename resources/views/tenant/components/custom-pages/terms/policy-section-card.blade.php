@props([
    'id' => '',
    'title' => '',
    'icon' => null,
    'content' => null,
])

@php
    $body = $content;
    if ($body === null || $body === '') {
        $body = isset($slot) && $slot->isNotEmpty() ? $slot : null;
    }
@endphp

<div id="{{ $id }}" class="scroll-mt-28 md:scroll-mt-32 group mb-8 transition-shadow last:mb-0 target:rounded-2xl target:ring-2 target:ring-moto-amber/40 target:ring-offset-2 target:ring-offset-carbon md:mb-12">
    <div class="mb-5 flex items-start gap-3 sm:mb-6 sm:gap-4">
        @if($icon)
            <div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-moto-amber/10 text-moto-amber ring-1 ring-moto-amber/20 transition-colors duration-300 group-hover:bg-moto-amber/20 sm:h-12 sm:w-12">
                {!! $icon !!}
            </div>
        @endif
        <h2 class="min-w-0 flex-1 text-2xl font-bold leading-tight text-white md:text-3xl">{{ $title }}</h2>
    </div>

    <div class="rounded-2xl border border-white/10 bg-obsidian/60 p-6 shadow-xl shadow-black/25 transition-colors hover:bg-obsidian/70 sm:p-8 md:p-10">
        <x-tenant.rich-prose variant="policy" :content="$body" />
    </div>
</div>
