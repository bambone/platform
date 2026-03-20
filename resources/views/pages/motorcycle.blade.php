@php
    $seoMeta = $seoMeta ?? null;
@endphp
<x-app-layout :meta="$seoMeta">
    <div class="max-w-7xl mx-auto px-4 md:px-8 py-20">
        <a href="{{ route('home') }}#catalog" class="text-moto-amber hover:underline mb-6 inline-block">← Назад к каталогу</a>
        <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">{{ $motorcycle->name }}</h1>
        @if($motorcycle->short_description)
            <p class="text-silver/90 text-lg mb-8">{{ $motorcycle->short_description }}</p>
        @endif
        <div class="grid md:grid-cols-2 gap-8">
            @if($motorcycle->cover_url)
                <img src="{{ $motorcycle->cover_url }}" alt="{{ $motorcycle->name }}" class="rounded-2xl w-full object-cover aspect-[4/3]">
            @endif
            <div>
                <p class="text-2xl font-bold text-moto-amber mb-4">от {{ number_format($motorcycle->price_per_day) }} ₽/сутки</p>
                @if($motorcycle->full_description)
                    <div class="prose prose-invert max-w-none text-silver">
                        {!! $motorcycle->full_description !!}
                    </div>
                @endif
            </div>
        </div>
        @php $gallery = $motorcycle->getMedia('gallery'); @endphp
        @if($gallery->isNotEmpty())
            <div class="mt-12">
                <h2 class="text-xl font-bold text-white mb-4">Галерея</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($gallery as $media)
                        <img src="{{ $media->getUrl() }}" alt="{{ $media->name }}" class="rounded-xl w-full object-cover aspect-square">
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
