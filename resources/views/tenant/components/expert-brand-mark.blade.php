@php
    $compactMark = (bool) ($compact ?? false);
@endphp
{{-- Заглушка личного бренда (пока нет загруженного лого): монограмма + руль --}}
<span class="expert-brand-mark inline-flex shrink-0 items-center justify-center border border-white/15 bg-gradient-to-br from-white/[0.12] to-white/[0.03] shadow-[0_12px_40px_-8px_rgba(0,0,0,0.55)] {{ $compactMark ? 'h-10 w-10 rounded-xl sm:h-11 sm:w-11' : 'h-12 w-12 rounded-2xl sm:h-14 sm:w-14' }}" aria-hidden="true">
    <svg viewBox="0 0 48 48" class="text-[var(--ex-accent,#c9a87c)] {{ $compactMark ? 'h-7 w-7 sm:h-8 sm:w-8' : 'h-8 w-8 sm:h-9 sm:w-9' }}" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="1.25" opacity="0.35"/>
        <path d="M24 10v6M24 32v6M10 24h6M32 24h6" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" opacity="0.5"/>
        <circle cx="24" cy="24" r="5.5" stroke="currentColor" stroke-width="1.5"/>
        <path d="M17 30c2.2-2.8 5.1-4.2 7-4.2s4.8 1.4 7 4.2" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" opacity="0.85"/>
    </svg>
</span>
