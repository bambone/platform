@props([
    'mapEmbedCode' => null,
    'mapUrl' => null,
])

<div class="relative aspect-[4/3] overflow-hidden rounded-2xl border border-white/10 bg-obsidian shadow-2xl shadow-black/50 ring-1 ring-inset ring-white/5 sm:aspect-[21/9] sm:min-h-[280px]">
    @if($mapEmbedCode || $mapUrl)
        @if($mapEmbedCode)
            <div class="w-full h-full [&>iframe]:w-full [&>iframe]:h-full [&>iframe]:border-0 filter invert-[90%] hue-rotate-180 opacity-90 transition-opacity hover:opacity-100">
                {!! $mapEmbedCode !!}
            </div>
        @elseif($mapUrl)
            <iframe 
                src="{{ $mapUrl }}" 
                width="100%" 
                height="100%" 
                frameborder="0" 
                class="w-full h-full absolute inset-0 border-0 filter invert-[90%] hue-rotate-180 opacity-90 transition-opacity hover:opacity-100"
                loading="lazy">
            </iframe>
        @endif
        
        <!-- Overlay gradient to blend map with dark theme beautifully -->
        <div class="absolute inset-0 pointer-events-none ring-1 ring-inset ring-white/10 rounded-2xl shadow-[inset_0_0_100px_rgba(0,0,0,0.6)]"></div>
    @else
        <!-- Neutral Fallback if no map is provided -->
        <div class="w-full h-full flex flex-col items-center justify-center p-6 text-center bg-carbon/50">
            <svg class="w-12 h-12 text-white/10 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
            <p class="text-silver text-sm">Карта не добавлена</p>
        </div>
    @endif
</div>
