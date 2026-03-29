@props(['section' => null])
@php
    $heading = $section['heading'] ?? 'Забронируйте мотоцикл и отправляйтесь в поездку уже сегодня';
    $description = $section['description'] ?? 'Экипировка включена. Цена фиксирована. Ограниченное количество техники — не откладывайте.';
    $buttonText = $section['button_text'] ?? 'Забронировать';
@endphp
<section class="relative z-10 overflow-hidden border-t border-white/[0.02] py-20 sm:py-28 lg:py-36 xl:py-40">
    <!-- Atmospheric Background Image with heavy darkening -->
    <div class="absolute inset-0 z-0">
        <img src="{{ asset(config('tenant_landing.motolevins_public_prefix').'/marketing/experience-touring.png') }}" alt="Road" class="w-full h-full object-cover opacity-30" onerror="this.style.display='none'">
        <div class="absolute inset-0 bg-gradient-to-b from-obsidian via-obsidian/90 to-[#08080a]"></div>
    </div>
    
    <!-- Central Glow Effect -->
    <div class="absolute inset-0 z-0 pointer-events-none flex items-center justify-center">
        <div class="w-[300px] h-[300px] sm:w-[500px] sm:h-[500px] bg-moto-amber/15 rounded-full blur-[120px]"></div>
    </div>

    <div class="relative z-10 mx-auto max-w-4xl px-3 text-center sm:px-4 md:px-8">
        <img src="{{ asset(config('tenant_landing.motolevins_public_prefix').'/marketing/logo-round-dark.png') }}" alt="Moto Levins" class="mx-auto mb-5 h-14 w-14 object-contain opacity-90 sm:mb-6 sm:h-16 sm:w-16 md:h-20 md:w-20" />
        <h2 class="mb-5 text-balance text-[clamp(1.75rem,5vw+0.75rem,3.25rem)] font-black leading-tight tracking-tight text-white drop-shadow-2xl sm:mb-6 md:text-5xl lg:text-6xl xl:text-7xl">
            {{ $heading }}
        </h2>
        <p class="mx-auto mb-10 max-w-2xl text-base leading-relaxed text-silver/90 drop-shadow-lg sm:mb-12 sm:text-lg md:text-xl lg:text-2xl">
            {{ $description }}
        </p>
        
        <div class="flex flex-col items-stretch justify-center gap-4 sm:flex-row sm:items-center sm:gap-5">
            <!-- Primary Final CTA -->
            <button type="button" @click="document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})" class="min-h-12 w-full rounded-xl bg-moto-amber px-8 py-3.5 text-base font-bold text-white shadow-2xl shadow-moto-amber/30 transition-all duration-300 hover:-translate-y-0.5 hover:bg-orange-600 hover:shadow-moto-amber/50 sm:w-auto sm:min-h-14 sm:px-10 sm:py-4 sm:text-lg md:text-xl">
                {{ $buttonText }}
            </button>
            
            <!-- Secondary Contact Link -->
            <a href="https://wa.me/79130608689" target="_blank" rel="noopener noreferrer" class="flex min-h-12 w-full items-center justify-center gap-3 rounded-xl border border-white/10 bg-white/5 px-8 py-3.5 text-base font-bold text-white shadow-xl backdrop-blur-md transition-all duration-300 hover:border-white/20 hover:bg-white/10 sm:w-auto sm:min-h-14 sm:px-10 sm:py-4 sm:text-lg md:text-xl">
                <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M17.498 14.382c-.301-.15-1.767-.867-2.04-.966-.273-.101-.473-.15-.673.15-.197.295-.771.964-.944 1.162-.175.195-.349.21-.646.062-.301-.15-1.265-.464-2.406-1.485-.888-.795-1.484-1.77-1.66-2.07-.174-.3-.019-.465.13-.615.136-.135.301-.345.451-.523.146-.181.194-.301.297-.496.1-.21.049-.375-.025-.524-.075-.15-.672-1.62-.922-2.206-.24-.584-.487-.51-.672-.51-.172-.015-.371-.015-.571-.015-.2 0-.523.074-.797.359-.273.3-1.045 1.02-1.045 2.475s1.07 2.865 1.219 3.075c.149.21 2.095 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                Связаться
            </a>
        </div>
    </div>
</section>
