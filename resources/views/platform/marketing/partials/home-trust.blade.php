<section id="doverie" class="pm-section-anchor border-b border-slate-200 bg-white" aria-label="Показатели доверия">
    <div class="mx-auto max-w-6xl px-3 py-5 sm:px-4 sm:py-6 md:px-6">
        <div class="flex flex-col gap-6 text-center text-sm text-slate-600 sm:flex-row sm:flex-wrap sm:items-center sm:justify-center sm:gap-8 md:justify-start md:text-left">
            <div class="max-w-xs">
                <span class="block text-2xl font-bold text-slate-900">{{ $pm['trust']['businesses'] }}</span>
                <span class="font-medium text-slate-800">бизнесов на платформе</span>
                <span class="mt-1 block text-slate-600">→ реальные проекты, не тестовые площадки.</span>
            </div>
            <div class="hidden h-8 w-px shrink-0 bg-slate-200 sm:block" aria-hidden="true"></div>
            <div class="max-w-xs">
                <span class="block text-2xl font-bold text-slate-900">{{ $pm['trust']['applications'] }}</span>
                <span class="font-medium text-slate-800">заявок обработано</span>
                <span class="mt-1 block text-slate-600">→ система уже работает и приносит клиентов.</span>
            </div>
        </div>
    </div>
</section>
