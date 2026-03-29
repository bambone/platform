<section id="uroven-produkta" class="pm-section-anchor border-b border-slate-200 bg-slate-50 py-12 sm:py-16 md:py-20" aria-labelledby="uroven-heading">
    <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <h2 id="uroven-heading" class="text-balance text-xl font-bold leading-tight text-slate-900 sm:text-2xl md:text-3xl">Продукт для бизнеса, а не для экспериментов</h2>
        <p class="mt-4 max-w-3xl text-sm leading-relaxed text-slate-600 sm:text-base">RentBase — для компаний, которым нужны заявки и бронирования в бою, а не «потестить бесплатно». Мы держим планку: реальные проекты, без мусора в продукте, стабильная инфраструктура и развитие вместе с платящими клиентами.</p>
        <ul class="mt-8 grid gap-3 sm:gap-4 md:grid-cols-2">
            @foreach([
                'На платформе — реальные проекты с онлайн-записью и операциями каждый день.',
                'В продукте — только то, что нужно сервисному бизнесу; без лишнего шума и «фич ради галочки».',
                'Серверы, бэкапы и доступность — инфраструктура под рабочую нагрузку, а не демо.',
                'Поддержка и сопровождение на старте — чтобы вы быстро вышли на приём заявок.',
            ] as $line)
                <li class="rounded-xl border border-slate-200 bg-white p-4 text-sm leading-relaxed text-slate-700 sm:p-5">{{ $line }}</li>
            @endforeach
        </ul>
    </div>
</section>
