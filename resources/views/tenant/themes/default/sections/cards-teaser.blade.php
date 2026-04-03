@php
    use App\Support\Storage\TenantPublicAssetResolver;

    $h = $data['heading'] ?? '';
    $desc = $data['description'] ?? '';
    $cards = is_array($data['cards'] ?? null) ? $data['cards'] : [];
@endphp
<section>
    @if(filled($h))
        <h2 class="text-balance text-xl font-bold text-white sm:text-2xl">{{ $h }}</h2>
    @endif
    @if(filled($desc))
        <p class="mt-3 max-w-2xl text-silver">{{ $desc }}</p>
    @endif
    <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2">
        @foreach($cards as $card)
            @php
                $imgRaw = $card['image'] ?? '';
                $imgUrl = TenantPublicAssetResolver::resolveForCurrentTenant(is_string($imgRaw) ? $imgRaw : '');
            @endphp
            <article class="flex flex-col overflow-hidden rounded-xl border border-white/10 bg-white/5">
                @if(filled($imgUrl))
                    <img src="{{ e($imgUrl) }}" alt="" class="h-40 w-full object-cover" loading="lazy" />
                @endif
                <div class="flex flex-1 flex-col p-4">
                    <h3 class="font-semibold text-white">{{ $card['title'] ?? '' }}</h3>
                    @if(filled($card['text'] ?? ''))
                        <p class="mt-2 flex-1 text-sm text-silver">{{ $card['text'] }}</p>
                    @endif
                    @if(filled($card['button_text'] ?? ''))
                        <a href="{{ e($card['button_url'] ?? '#') }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg border border-white/20 px-4 py-2 text-sm font-medium text-white hover:bg-white/10">{{ $card['button_text'] }}</a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
