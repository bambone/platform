@php
    $compactMark = (bool) ($compact ?? false);
@endphp
<span class="expert-brand-mark relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#11161d] shadow-sm {{ $compactMark ? 'h-9 w-9 sm:h-10 sm:w-10' : 'h-11 w-11 sm:h-12 sm:w-12' }}" aria-hidden="true">
    <svg viewBox="0 0 48 48" class="text-moto-amber {{ $compactMark ? 'h-[1.6rem] w-[1.6rem] sm:h-[1.75rem] sm:w-[1.75rem]' : 'h-[2rem] w-[2rem]' }}" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="1.5" opacity="0.3"/>
        <path d="M24 10v6M24 32v6M10 24h6M32 24h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.5"/>
        <circle cx="24" cy="24" r="5" stroke="currentColor" stroke-width="1.5" opacity="0.9"/>
        <path d="M17 30c2.2-2.8 5.1-4.2 7-4.2s4.8 1.4 7 4.2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
    </svg>
</span>
