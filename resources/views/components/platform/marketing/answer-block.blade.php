@props([
    'question' => '',
])
<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <h3 class="text-sm font-semibold leading-snug text-slate-900 sm:text-base">{{ $question }}</h3>
    <div class="mt-2 text-sm leading-relaxed text-slate-600">
        {{ $slot }}
    </div>
</div>
