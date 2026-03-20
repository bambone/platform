@props(['bike', 'badge' => null])
@php
    $imageUrl = $bike->cover_url ?? null;
    if (!$imageUrl && ($img = $bike->cover_image ?? $bike->image ?? null)) {
        $imageUrl = str_starts_with($img, 'motorcycles/') ? asset('storage/' . $img) : asset('images/' . $img);
    }
    $type = $bike->model ?? $bike->type ?? '';
    $engine = $bike->engine_cc ?? $bike->engine ?? 0;
    $description = $bike->short_description ?? $bike->description ?? 'Идеален для города и путешествий.';
@endphp
<div class="bg-carbon rounded-2xl overflow-hidden flex flex-col h-full group relative transition-all duration-400 hover:scale-[1.02] hover:-translate-y-1.5 border border-white/5 hover:border-white/10 shadow-xl shadow-black/40 hover:shadow-2xl hover:shadow-moto-amber/5 cursor-pointer"
     @click="$dispatch('open-booking-modal', { id: {{ $bike->id }}, name: {!! json_encode($bike->name) !!}, price: {{ $bike->price_per_day }}, start: filters.start_date, end: filters.end_date })">
    
    <!-- Restrained Background Amber Glow -->
    <div class="absolute inset-0 bg-moto-amber/10 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none -z-10 rounded-2xl"></div>

    <!-- Image Zone (Fixed h-64 target 60%) -->
    <div class="relative h-64 bg-[#0a0a0c] overflow-hidden shrink-0 border-b border-white/[0.03]">
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $bike->name }}" class="block w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-105" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
        @endif
        <div class="absolute inset-0 flex items-center justify-center text-silver text-sm img-fallback {{ $imageUrl ? 'hidden' : '' }}">
            <svg class="w-12 h-12 text-white/5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        
        <!-- UI Treatment Overlays to unify media presentation -->
        <!-- Subtle dark wash to tone down blown-out photos -->
        <div class="absolute inset-0 bg-black/10 group-hover:bg-transparent transition-colors duration-500"></div>
        <!-- Bottom integration gradient tying image to carbon background -->
        <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-carbon to-transparent"></div>

        <!-- Badges -->
        <div class="absolute top-4 left-4 right-4 flex flex-wrap gap-2 z-10">
            @if($badge)
                <span class="bg-moto-amber/90 text-white px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider shadow-lg">{{ $badge }}</span>
            @endif
            @if($type)
            <span class="bg-black/60 backdrop-blur-md px-3 py-1.5 rounded-full border border-white/10 text-[10px] font-bold text-moto-amber uppercase tracking-widest">{{ $type }}</span>
            @endif
        </div>
    </div>

    <!-- Content Zone (Internal layering depth) -->
    <div class="px-6 pb-6 pt-2 flex flex-col flex-1 relative z-10 bg-carbon">
        <h3 class="text-[22px] font-bold text-white mt-1 mb-1.5 leading-tight group-hover:text-moto-amber transition-colors line-clamp-1 drop-shadow-sm" title="{{ $bike->name }}">{{ $bike->name }}</h3>
        
        <!-- Advantage -->
        <p class="text-sm text-silver/90 leading-relaxed mb-5 h-10 line-clamp-2" title="{{ $description }}">
            {{ $description }}
        </p>

        <!-- Specs Row (Soft depth separator) -->
        <div class="flex items-center gap-4 text-[13px] text-silver font-medium mt-auto mb-5 py-3 border-y border-white/[0.03] bg-white/[0.01] -mx-6 px-6">
            <div class="flex items-center gap-2 flex-shrink-0">
                <svg class="w-4 h-4 text-silver/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                {{ $engine }} cc
            </div>
            @if($bike->license_category)
            <div class="flex items-center gap-2 flex-shrink-0">
                <svg class="w-4 h-4 text-silver/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                Кат. {{ $bike->license_category }}
            </div>
            @endif
        </div>

        <!-- Price Area & Secondary CTA Row -->
        <div class="flex items-center justify-between mt-auto">
            <!-- Price Display -->
            <div x-show="!filters.start_date || !filters.end_date" class="flex-1">
                <span class="text-[11px] text-silver/80 uppercase tracking-widest font-semibold block mb-0.5">от</span>
                <span class="text-2xl font-extrabold text-white tracking-tight">{{ number_format($bike->price_per_day, 0, ',', ' ') }} ₽</span>
                <span class="text-[11px] text-silver/60 uppercase tracking-wider">/ сутки</span>
            </div>
            <div class="flex-1" x-show="filters.start_date && filters.end_date" x-cloak>
                <span class="text-[11px] text-moto-amber uppercase tracking-widest font-bold block mb-0.5" x-text="`${Math.floor((Date.UTC(new Date(filters.end_date).getFullYear(), new Date(filters.end_date).getMonth(), new Date(filters.end_date).getDate()) - Date.UTC(new Date(filters.start_date).getFullYear(), new Date(filters.start_date).getMonth(), new Date(filters.start_date).getDate())) / (1000 * 60 * 60 * 24)) + 1} дней аренды`"></span>
                <span class="text-2xl font-extrabold text-white tracking-tight leading-none block"><span x-text="formatPrice(calculateCardTotalPrice({{ $bike->price_per_day }}))"></span> ₽</span>
            </div>

            <!-- Secondary Conversion Button -->
            <!-- Read as an action without competing with Hero CTA -->
            <button class="w-auto px-5 bg-white/5 text-silver hover:text-white group-hover:bg-moto-amber font-semibold h-11 rounded-xl transition-all flex justify-center items-center gap-2 border border-white/10 group-hover:border-moto-amber active:scale-[0.96]">
                Забронировать
                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </button>
        </div>
    </div>
</div>
