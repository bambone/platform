@props(['section' => null])
@php
    $heading = $section['heading'] ?? 'Забронируйте мотоцикл и отправляйтесь в поездку уже сегодня';
    $description = $section['description'] ?? 'Экипировка включена. Цена фиксирована. Ограниченное количество техники — не откладывайте.';
    $buttonText = $section['button_text'] ?? 'Забронировать';
@endphp
<section class="relative z-10 overflow-hidden border-t border-white/[0.02] pt-24 pb-20 sm:pt-28 sm:pb-24 md:pt-36 md:pb-28 lg:pt-40 lg:pb-32" aria-labelledby="final-cta-heading">
    <div class="absolute inset-0 z-0">
        <img src="{{ theme_platform_asset_url('marketing/experience-touring.png') }}"
             alt=""
             role="presentation"
             class="h-full w-full object-cover brightness-110 contrast-[1.05]"
             onerror="this.style.display='none'">
        <div class="absolute inset-0 bg-gradient-to-b from-black/55 via-black/45 to-black/70 md:from-black/50 md:via-black/40 md:to-black/65"></div>
    </div>

    <div class="pointer-events-none absolute inset-0 z-0 flex items-center justify-center">
        <div class="h-[280px] w-[280px] rounded-full bg-moto-amber/10 blur-[100px] sm:h-[420px] sm:w-[420px] sm:blur-[120px]"></div>
    </div>

    <div class="relative z-10 mx-auto max-w-4xl px-3 text-center sm:px-4 md:px-8">
        <img src="{{ theme_platform_asset_url('marketing/logo-round-dark.png') }}" alt="" class="mx-auto mb-6 h-12 w-12 object-contain opacity-95 sm:mb-8 sm:h-14 sm:w-14" width="56" height="56" decoding="async" />
        <h2 id="final-cta-heading" class="mx-auto mb-6 max-w-2xl text-balance text-3xl font-bold leading-tight tracking-tight text-white drop-shadow-[0_2px_24px_rgba(0,0,0,0.45)] md:text-4xl lg:text-5xl">
            {{ $heading }}
        </h2>
        <p class="mx-auto mb-8 max-w-xl text-base leading-relaxed text-zinc-200/95 drop-shadow-md md:text-lg">
            {{ $description }}
        </p>

        <div class="flex flex-col items-stretch justify-center gap-4 sm:flex-row sm:items-center sm:gap-5">
            <button type="button" @click="document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})" class="tenant-btn-primary min-h-12 w-full px-8 py-3.5 text-base shadow-lg transition-all duration-300 hover:-translate-y-0.5 sm:w-auto sm:min-h-12 sm:px-8 sm:py-3.5 sm:text-base">
                {{ $buttonText }}
            </button>

            <a href="https://wa.me/79130608689" target="_blank" rel="noopener noreferrer" class="tenant-btn-secondary min-h-12 w-full gap-3 px-8 py-3.5 text-base font-semibold shadow-md backdrop-blur-md transition-all duration-300 sm:w-auto sm:min-h-12 sm:px-8 sm:py-3.5 sm:text-base">
                <svg class="h-5 w-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.498 14.382c-.301-.15-1.767-.867-2.04-.966-.273-.101-.473-.15-.673.15-.197.295-.771.964-.944 1.162-.175.195-.349.21-.646.062-.301-.15-1.265-.464-2.406-1.485-.888-.795-1.484-1.77-1.66-2.07-.174-.3-.019-.465.13-.615.136-.135.301-.345.451-.523.146-.181.194-.301.297-.496.1-.21.049-.375-.025-.524-.075-.15-.672-1.62-.922-2.206-.24-.584-.487-.51-.672-.51-.172-.015-.371-.015-.571-.015-.2 0-.523.074-.797.359-.273.3-1.045 1.02-1.045 2.475s1.07 2.865 1.219 3.075c.149.21 2.095 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                Связаться
            </a>
        </div>
    </div>
</section>
