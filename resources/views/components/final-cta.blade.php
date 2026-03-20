@props(['section' => null])
@php
    $heading = $section['heading'] ?? 'Забронируйте мотоцикл и отправляйтесь в поездку уже сегодня';
    $description = $section['description'] ?? 'Экипировка включена. Цена фиксирована. Ограниченное количество техники — не откладывайте.';
    $buttonText = $section['button_text'] ?? 'Забронировать';
@endphp
<section class="py-32 lg:py-40 relative z-10 border-t border-white/[0.02] overflow-hidden">
    <!-- Atmospheric Background Image with heavy darkening -->
    <div class="absolute inset-0 z-0">
        <img src="{{ asset('images/experience-touring.png') }}" alt="Road" class="w-full h-full object-cover opacity-30" onerror="this.style.display='none'">
        <div class="absolute inset-0 bg-gradient-to-b from-obsidian via-obsidian/90 to-[#08080a]"></div>
    </div>
    
    <!-- Central Glow Effect -->
    <div class="absolute inset-0 z-0 pointer-events-none flex items-center justify-center">
        <div class="w-[300px] h-[300px] sm:w-[500px] sm:h-[500px] bg-moto-amber/15 rounded-full blur-[120px]"></div>
    </div>

    <div class="max-w-4xl mx-auto px-4 md:px-8 text-center relative z-10">
        <img src="{{ asset('images/logo-round-dark.png') }}" alt="Moto Levins" class="w-16 h-16 md:w-20 md:h-20 mx-auto mb-6 opacity-90 object-contain" />
        <h2 class="text-4xl md:text-6xl lg:text-7xl font-black text-white mb-6 tracking-tight drop-shadow-2xl">
            {{ $heading }}
        </h2>
        <p class="text-silver/90 text-lg md:text-2xl mb-12 max-w-2xl mx-auto leading-relaxed drop-shadow-lg">
            {{ $description }}
        </p>
        
        <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
            <!-- Primary Final CTA -->
            <button @click="document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})" class="w-full sm:w-auto px-10 py-5 bg-moto-amber hover:bg-orange-600 text-white font-bold rounded-xl transition-all duration-300 shadow-2xl shadow-moto-amber/30 hover:shadow-moto-amber/50 hover:-translate-y-1 text-xl">
                {{ $buttonText }}
            </button>
            
            <!-- Secondary Contact Link -->
            <a href="https://wa.me/79130608689" target="_blank" class="w-full sm:w-auto px-10 py-5 bg-white/5 hover:bg-white/10 text-white font-bold rounded-xl transition-all duration-300 border border-white/10 hover:border-white/20 shadow-xl backdrop-blur-md text-xl flex items-center justify-center gap-3">
                <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M17.498 14.382c-.301-.15-1.767-.867-2.04-.966-.273-.101-.473-.15-.673.15-.197.295-.771.964-.944 1.162-.175.195-.349.21-.646.062-.301-.15-1.265-.464-2.406-1.485-.888-.795-1.484-1.77-1.66-2.07-.174-.3-.019-.465.13-.615.136-.135.301-.345.451-.523.146-.181.194-.301.297-.496.1-.21.049-.375-.025-.524-.075-.15-.672-1.62-.922-2.206-.24-.584-.487-.51-.672-.51-.172-.015-.371-.015-.571-.015-.2 0-.523.074-.797.359-.273.3-1.045 1.02-1.045 2.475s1.07 2.865 1.219 3.075c.149.21 2.095 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                Связаться
            </a>
        </div>
    </div>
</section>
