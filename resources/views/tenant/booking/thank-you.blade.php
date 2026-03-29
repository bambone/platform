@extends('tenant.layouts.app')

@section('title', 'Бронирование подтверждено')

@section('content')
<section class="mx-auto max-w-xl px-3 pb-12 pt-24 text-center sm:px-4 sm:pb-16 sm:pt-28 md:px-8">
    <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full border border-green-500/30 bg-green-500/20 text-green-400 sm:mb-8 sm:h-20 sm:w-20">
        <svg class="h-8 w-8 sm:h-10 sm:w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    </div>
    <h1 class="mb-4 text-balance text-2xl font-bold leading-tight text-white sm:text-3xl">Бронирование принято!</h1>
    <p class="mx-auto mb-8 max-w-md text-sm leading-relaxed text-silver sm:text-base">
        Наш менеджер свяжется с вами в ближайшее время для подтверждения и уточнения деталей.
    </p>

    @if($booking)
        <div class="glass mb-8 rounded-2xl p-4 text-left sm:p-6">
            <p class="mb-1 text-xs text-silver sm:text-sm">Номер бронирования</p>
            <p class="break-all text-xl font-bold text-moto-amber sm:text-2xl">{{ $booking->booking_number }}</p>
            <p class="mt-4 text-xs text-silver sm:text-sm">Сохраните этот номер для связи с нами.</p>
        </div>
    @endif

    <a href="{{ route('home') }}" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/10 px-6 py-3 font-semibold text-white transition-colors hover:bg-white/20 touch-manipulation sm:w-auto sm:px-8">
        На главную
    </a>
</section>
@endsection
