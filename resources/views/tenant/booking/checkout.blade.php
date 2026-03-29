@extends('tenant.layouts.app')

@section('title', 'Оформление бронирования')

@section('content')
<section class="mx-auto max-w-2xl px-3 pb-12 pt-24 sm:px-4 sm:pb-16 sm:pt-28 md:px-8">
    <a href="{{ route('booking.show', $motorcycle->slug) }}" class="mb-6 inline-flex min-h-10 items-center gap-2 text-sm text-silver transition-colors hover:text-white sm:mb-8 sm:text-base focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        Изменить даты
    </a>

    <div class="glass mb-6 rounded-2xl p-4 sm:p-6">
        <h2 class="mb-4 text-base font-bold text-white sm:text-lg">Ваше бронирование</h2>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-4">
            @if($motorcycle->cover_url)
                <img src="{{ $motorcycle->cover_url }}" alt="{{ $motorcycle->name }}" class="h-24 w-24 shrink-0 rounded-xl object-cover sm:h-28 sm:w-28">
            @endif
            <div class="min-w-0">
                <h3 class="font-bold text-white">{{ $motorcycle->name }}</h3>
                <p class="mt-1 text-sm text-silver">{{ $draft['start_date'] }} — {{ $draft['end_date'] }}</p>
                @php
                    $start = \Carbon\Carbon::parse($draft['start_date']);
                    $end = \Carbon\Carbon::parse($draft['end_date']);
                    $days = $start->diffInDays($end) + 1;
                @endphp
                <p class="text-sm text-silver">{{ $days }} {{ $days === 1 ? 'день' : ($days < 5 ? 'дня' : 'дней') }}</p>
            </div>
        </div>
        @if($addons->isNotEmpty())
            <div class="mt-4 border-t border-white/10 pt-4">
                <p class="mb-2 text-sm text-silver">Дополнительно:</p>
                @foreach($addons as $item)
                    <p class="text-sm text-white">{{ $item->addon->name }} × {{ $item->quantity }} — {{ number_format($item->addon->price * $item->quantity) }} ₽</p>
                @endforeach
            </div>
        @endif
    </div>

    <form action="{{ route('booking.store-checkout') }}" method="POST" class="glass rounded-2xl p-4 sm:p-6 md:p-8">
        @csrf
        <h2 class="mb-5 text-lg font-bold text-white sm:mb-6 sm:text-xl">Контактные данные</h2>

        @if(session('error'))
            <div class="mb-6 rounded-xl border border-red-500/50 bg-red-500/10 p-4 text-sm text-red-400 sm:text-base">
                {{ session('error') }}
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <label class="mb-2 block text-sm text-silver" for="checkout-name">Ваше имя *</label>
                <input id="checkout-name" type="text" name="customer_name" value="{{ old('customer_name') }}" required autocomplete="name"
                    class="h-12 w-full rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white outline-none focus:border-moto-amber focus:ring-1 focus:ring-moto-amber @error('customer_name') border-red-500 @enderror">
                @error('customer_name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm text-silver" for="checkout-phone">Телефон *</label>
                <input id="checkout-phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel"
                    class="h-12 w-full rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white outline-none focus:border-moto-amber focus:ring-1 focus:ring-moto-amber @error('phone') border-red-500 @enderror">
                @error('phone')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm text-silver" for="checkout-email">Email</label>
                <input id="checkout-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email"
                    class="h-12 w-full rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white outline-none focus:border-moto-amber focus:ring-1 focus:ring-moto-amber">
            </div>
            <div>
                <label class="mb-2 block text-sm text-silver" for="checkout-comment">Комментарий</label>
                <textarea id="checkout-comment" name="customer_comment" rows="3"
                    class="w-full resize-none rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white outline-none focus:border-moto-amber focus:ring-1 focus:ring-moto-amber">{{ old('customer_comment') }}</textarea>
            </div>
        </div>

        <button type="submit" class="mt-8 min-h-12 w-full rounded-xl bg-moto-amber py-3.5 font-bold text-white transition-colors hover:bg-moto-amber/90 touch-manipulation sm:min-h-14 sm:py-4">
            Подтвердить бронирование
        </button>
    </form>
</section>
@endsection
