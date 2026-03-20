@extends('layouts.app')

@section('title', 'Оформление бронирования')

@section('content')
<section class="py-16 container mx-auto px-4 max-w-2xl">
    <a href="{{ route('booking.show', $motorcycle->slug) }}" class="inline-flex items-center gap-2 text-silver hover:text-white mb-8 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        Изменить даты
    </a>

    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-bold text-white mb-4">Ваше бронирование</h2>
        <div class="flex gap-4">
            @if($motorcycle->cover_url)
                <img src="{{ $motorcycle->cover_url }}" alt="{{ $motorcycle->name }}" class="w-24 h-24 object-cover rounded-xl">
            @endif
            <div>
                <h3 class="text-white font-bold">{{ $motorcycle->name }}</h3>
                <p class="text-silver text-sm">{{ $draft['start_date'] }} — {{ $draft['end_date'] }}</p>
                @php
                    $start = \Carbon\Carbon::parse($draft['start_date']);
                    $end = \Carbon\Carbon::parse($draft['end_date']);
                    $days = $start->diffInDays($end) + 1;
                @endphp
                <p class="text-silver text-sm">{{ $days }} {{ $days === 1 ? 'день' : ($days < 5 ? 'дня' : 'дней') }}</p>
            </div>
        </div>
        @if($addons->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-white/10">
                <p class="text-silver text-sm mb-2">Дополнительно:</p>
                @foreach($addons as $item)
                    <p class="text-white text-sm">{{ $item->addon->name }} × {{ $item->quantity }} — {{ number_format($item->addon->price * $item->quantity) }} ₽</p>
                @endforeach
            </div>
        @endif
    </div>

    <form action="{{ route('booking.store-checkout') }}" method="POST" class="glass rounded-2xl p-6 md:p-8">
        @csrf
        <h2 class="text-xl font-bold text-white mb-6">Контактные данные</h2>

        @if(session('error'))
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/50 rounded-xl text-red-400">
                {{ session('error') }}
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <label class="block text-sm text-silver mb-2">Ваше имя *</label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                    class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-moto-amber focus:border-moto-amber outline-none @error('customer_name') border-red-500 @enderror">
                @error('customer_name')
                    <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm text-silver mb-2">Телефон *</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" required
                    class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-moto-amber focus:border-moto-amber outline-none @error('phone') border-red-500 @enderror">
                @error('phone')
                    <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm text-silver mb-2">Email</label>
                <input type="email" name="email" value="{{ old('email') }}"
                    class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-moto-amber focus:border-moto-amber outline-none">
            </div>
            <div>
                <label class="block text-sm text-silver mb-2">Комментарий</label>
                <textarea name="customer_comment" rows="3"
                    class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-moto-amber focus:border-moto-amber outline-none resize-none">{{ old('customer_comment') }}</textarea>
            </div>
        </div>

        <button type="submit" class="w-full mt-8 bg-moto-amber hover:bg-moto-amber/90 text-white font-bold py-4 rounded-xl transition-colors">
            Подтвердить бронирование
        </button>
    </form>
</section>
@endsection
