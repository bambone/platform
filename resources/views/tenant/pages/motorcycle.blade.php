@php
    $seoMeta = $seoMeta ?? null;
@endphp
@extends('tenant.layouts.app')

@section('content')
    <div class="mx-auto max-w-7xl px-3 pb-12 pt-24 sm:px-4 sm:pb-16 sm:pt-28 md:px-8">
        <a href="{{ route('home') }}#catalog" class="mb-6 inline-flex min-h-10 items-center gap-1 text-sm text-moto-amber transition-colors hover:underline sm:text-base focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">← Назад к каталогу</a>
        <h1 class="mb-4 text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">{{ $motorcycle->name }}</h1>
        @if($motorcycle->short_description)
            <p class="mb-8 text-sm leading-relaxed text-silver/90 sm:text-base md:text-lg">{{ $motorcycle->short_description }}</p>
        @endif
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 md:gap-8">
            @if($motorcycle->cover_url)
                <img src="{{ $motorcycle->cover_url }}" alt="{{ $motorcycle->name }}" class="aspect-[4/3] w-full max-w-full rounded-2xl object-cover">
            @endif
            <div class="min-w-0">
                <p class="mb-4 break-words text-xl font-bold text-moto-amber sm:text-2xl">от {{ number_format($motorcycle->price_per_day) }} ₽/сутки</p>
                @if($motorcycle->full_description)
                    <div class="prose prose-invert max-w-none text-sm text-silver prose-p:leading-relaxed sm:text-base">
                        {!! $motorcycle->full_description !!}
                    </div>
                @endif
            </div>
        </div>
        @php $gallery = $motorcycle->getMedia('gallery'); @endphp
        @if($gallery->isNotEmpty())
            <div class="mt-10 sm:mt-12">
                <h2 class="mb-4 text-xl font-bold text-white sm:text-2xl">Галерея</h2>
                <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                    @foreach($gallery as $media)
                        <img src="{{ $media->getUrl() }}" alt="{{ $media->name }}" class="aspect-square w-full max-w-full rounded-xl object-cover">
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
