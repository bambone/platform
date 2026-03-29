@extends('tenant.layouts.app')

@section('title', 'Бронирование')

@section('content')
<section class="mx-auto max-w-6xl px-3 pb-12 pt-24 sm:px-4 sm:pb-16 sm:pt-28 md:px-8">
    <h1 class="mb-2 text-balance text-2xl font-bold leading-tight text-white sm:text-3xl">Онлайн-бронирование</h1>
    <p class="mb-8 text-sm text-silver sm:mb-10 sm:text-base">Выберите транспорт и даты аренды</p>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 lg:gap-6">
        @foreach($motorcycles as $m)
            <a href="{{ route('booking.show', $m->slug) }}" class="glass-card group block overflow-hidden rounded-2xl transition-shadow hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber touch-manipulation">
                <div class="aspect-[4/3] overflow-hidden bg-carbon">
                    @if($m->cover_url)
                        <img src="{{ $m->cover_url }}" alt="{{ $m->name }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-silver/50">
                            <svg class="h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                    @endif
                </div>
                <div class="p-4 sm:p-5">
                    <h3 class="mb-1 text-base font-bold text-white sm:text-lg">{{ $m->name }}</h3>
                    <p class="mb-3 line-clamp-2 text-xs text-silver sm:text-sm">{{ $m->short_description }}</p>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="font-bold text-moto-amber">{{ number_format($m->price_per_day ?? 0) }} ₽</span>
                        <span class="text-xs text-silver sm:text-sm">/ сутки</span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    @if($motorcycles->isEmpty())
        <div class="rounded-2xl border border-white/10 bg-carbon py-12 text-center sm:py-16">
            <p class="px-4 text-sm text-silver sm:text-base">Нет доступных мотоциклов для бронирования.</p>
        </div>
    @endif
</section>
@endsection
