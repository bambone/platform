@php
    $headline = trim((string) ($headline ?? ''));
    $variant = $variant ?? 'indigo';
    $urlLaunch = platform_marketing_contact_url($pm['intent']['launch'] ?? 'launch');
@endphp
@if($headline !== '')
    <section class="pm-cta-strip {{ $variant === 'slate' ? 'bg-slate-900' : 'border-b border-slate-200 bg-indigo-50/95' }}" aria-label="Призыв к действию">
        <div class="mx-auto flex max-w-6xl flex-col items-stretch gap-4 px-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6 md:px-6">
            <p class="max-w-2xl text-pretty text-base font-extrabold leading-snug {{ $variant === 'slate' ? 'text-white' : 'text-slate-900' }} sm:text-lg">
                {!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $headline) !!}
            </p>
            <a href="{{ $urlLaunch }}" class="inline-flex min-h-12 w-full shrink-0 items-center justify-center rounded-xl bg-pm-accent px-6 py-3.5 text-center text-base font-extrabold text-white shadow-md transition-transform hover:bg-pm-accent-hover active:scale-[0.98] sm:w-auto sm:min-w-[11rem] sm:px-8" data-pm-event="cta_click" data-pm-cta="primary" data-pm-location="strip">
                {{ $pm['cta']['primary'] ?? 'Запустить' }}
            </a>
        </div>
    </section>
@endif
