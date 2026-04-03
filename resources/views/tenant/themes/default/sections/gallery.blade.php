@php
    use App\Support\Storage\TenantPublicAssetResolver;

    $h = $data['heading'] ?? '';
    $images = is_array($data['images'] ?? null) ? $data['images'] : [];
@endphp
<section>
    @if(filled($h))
        <h2 class="mb-6 text-xl font-bold text-white sm:text-2xl">{{ $h }}</h2>
    @endif
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach($images as $img)
            @php
                $srcRaw = $img['url'] ?? '';
                $src = TenantPublicAssetResolver::resolveForCurrentTenant(is_string($srcRaw) ? $srcRaw : '');
            @endphp
            @if(filled($src))
                <figure class="overflow-hidden rounded-xl border border-white/10">
                    <img src="{{ e($src) }}" alt="{{ e($img['caption'] ?? '') }}" class="h-auto w-full max-w-full object-cover" loading="lazy" />
                    @if(filled($img['caption'] ?? ''))
                        <figcaption class="p-2 text-center text-xs text-silver">{{ $img['caption'] }}</figcaption>
                    @endif
                </figure>
            @endif
        @endforeach
    </div>
</section>
