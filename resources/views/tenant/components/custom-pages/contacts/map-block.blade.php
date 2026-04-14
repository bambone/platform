@props([
    'view' => null,
    /** @var 'public'|'admin' Предпросмотр в Filament: светлый фон — заголовок не белым. */
    'previewVariant' => 'public',
])

@php
    use App\PageBuilder\Contacts\ContactMapResolvedView;
    use App\PageBuilder\Contacts\MapEffectiveRenderMode;

    $v = $view instanceof ContactMapResolvedView ? $view : null;
@endphp

@if($v instanceof ContactMapResolvedView && $v->shouldRenderMapBlock())
    <div class="flex flex-col gap-4">
        @if(filled($v->mapTitle))
            <h3 @class([
                'text-base font-semibold sm:text-lg',
                'text-gray-900' => $previewVariant === 'admin',
                'text-white' => $previewVariant !== 'admin',
            ])>{{ $v->mapTitle }}</h3>
        @endif

        @if(in_array($v->mapEffectiveRenderMode, [MapEffectiveRenderMode::EmbedOnly, MapEffectiveRenderMode::EmbedAndButton], true) && $v->mapEmbedUrl !== '')
            <div @class([
                'aspect-video w-full min-h-[200px] overflow-hidden rounded-lg border',
                'border-slate-300 bg-slate-100' => $previewVariant === 'admin',
                'border-white/10 bg-black/20' => $previewVariant !== 'admin',
            ])>
                <iframe
                    src="{{ $v->mapEmbedUrl }}"
                    class="h-full w-full border-0"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                    title="{{ $v->mapTitle ?: 'Карта' }}"
                ></iframe>
            </div>
        @endif

        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            @if(in_array($v->mapEffectiveRenderMode, [MapEffectiveRenderMode::ButtonOnly, MapEffectiveRenderMode::EmbedAndButton], true) && $v->mapPublicUrl !== '')
                <a
                    href="{{ $v->mapPublicUrl }}"
                    @class([
                        'inline-flex min-h-11 w-full items-center justify-center rounded-lg px-5 py-2.5 text-center text-sm font-semibold shadow-sm transition focus-visible:outline focus-visible:ring-2 sm:w-auto',
                        'bg-amber-500 text-slate-950 hover:bg-amber-400 focus-visible:ring-amber-400/60' => $previewVariant === 'admin',
                        'bg-moto-amber/90 text-[#0c0c0e] hover:bg-moto-amber focus-visible:ring-moto-amber/60' => $previewVariant !== 'admin',
                    ])
                    target="_blank"
                    rel="noopener noreferrer"
                >{{ $v->mapActionLabel }}</a>
            @endif

            @if($v->mapWillRenderSecondaryButton && filled($v->mapSecondaryPublicUrl))
                <a
                    href="{{ $v->mapSecondaryPublicUrl }}"
                    @class([
                        'inline-flex min-h-11 w-full items-center justify-center rounded-lg border-2 px-5 py-2.5 text-center text-sm font-semibold transition focus-visible:outline focus-visible:ring-2 sm:w-auto',
                        'border-amber-500 bg-transparent text-amber-100 hover:bg-amber-500/10 focus-visible:ring-amber-400/50' => $previewVariant === 'admin',
                        'border-moto-amber/80 bg-transparent text-white hover:bg-white/5 focus-visible:ring-moto-amber/50' => $previewVariant !== 'admin',
                    ])
                    target="_blank"
                    rel="noopener noreferrer"
                >{{ $v->mapSecondaryActionLabel }}</a>
            @endif
        </div>
    </div>
@endif
