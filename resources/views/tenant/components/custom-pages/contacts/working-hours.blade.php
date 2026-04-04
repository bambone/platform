@props([
    'workingHours' => null,
])

@if($workingHours)
<div class="rounded-xl border border-white/5 bg-obsidian p-6 sm:p-8 relative overflow-hidden">
    <!-- Decorative background glow -->
    <div class="absolute -top-10 -right-10 w-40 h-40 bg-moto-amber/5 rounded-full blur-3xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs font-medium text-silver mb-4">
                <span class="w-1.5 h-1.5 rounded-full bg-moto-amber animate-pulse"></span>
                Режим работы
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Ждем вас каждый день</h3>
            <div class="text-silver/90 whitespace-pre-wrap leading-relaxed">{{ $workingHours }}</div>
        </div>
        
        <div class="shrink-0 hidden md:flex items-center justify-center w-16 h-16 rounded-full bg-carbon border border-white/5 text-moto-amber/80">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
    </div>
</div>
@endif
