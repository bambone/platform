@extends('layouts.app')

@section('title', 'Бронирование подтверждено')

@section('content')
<section class="py-24 container mx-auto px-4 max-w-xl text-center">
    <div class="w-20 h-20 mx-auto mb-8 bg-green-500/20 text-green-400 rounded-full flex items-center justify-center border border-green-500/30">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    </div>
    <h1 class="text-3xl font-bold text-white mb-4">Бронирование принято!</h1>
    <p class="text-silver mb-8">
        Наш менеджер свяжется с вами в ближайшее время для подтверждения и уточнения деталей.
    </p>

    @if($booking)
        <div class="glass rounded-2xl p-6 text-left mb-8">
            <p class="text-silver text-sm mb-1">Номер бронирования</p>
            <p class="text-2xl font-bold text-moto-amber">{{ $booking->booking_number }}</p>
            <p class="text-silver text-sm mt-4">Сохраните этот номер для связи с нами.</p>
        </div>
    @endif

    <a href="{{ route('home') }}" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-8 py-3 rounded-xl font-semibold transition-colors border border-white/10">
        На главную
    </a>
</section>
@endsection
