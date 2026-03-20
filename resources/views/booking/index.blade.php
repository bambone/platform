@extends('layouts.app')

@section('title', 'Бронирование')

@section('content')
<section class="py-16 container mx-auto px-4 max-w-6xl">
    <h1 class="text-3xl font-bold text-white mb-2">Онлайн-бронирование</h1>
    <p class="text-silver mb-12">Выберите транспорт и даты аренды</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($motorcycles as $m)
            <a href="{{ route('booking.show', $m->slug) }}" class="glass-card rounded-2xl overflow-hidden block group">
                <div class="aspect-[4/3] bg-carbon overflow-hidden">
                    @if($m->cover_url)
                        <img src="{{ $m->cover_url }}" alt="{{ $m->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-silver/50">
                            <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                    @endif
                </div>
                <div class="p-5">
                    <h3 class="text-lg font-bold text-white mb-1">{{ $m->name }}</h3>
                    <p class="text-silver text-sm mb-3 line-clamp-2">{{ $m->short_description }}</p>
                    <div class="flex justify-between items-center">
                        <span class="text-moto-amber font-bold">{{ number_format($m->price_per_day ?? 0) }} ₽</span>
                        <span class="text-silver text-sm">/ сутки</span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    @if($motorcycles->isEmpty())
        <div class="text-center py-16 bg-carbon rounded-2xl border border-white/10">
            <p class="text-silver">Нет доступных мотоциклов для бронирования.</p>
        </div>
    @endif
</section>
@endsection
