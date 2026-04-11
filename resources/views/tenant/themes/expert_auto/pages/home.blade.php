@php
    $sections = $sections ?? [];
    $homeLayoutSections = $homeLayoutSections ?? collect();
@endphp
@extends('tenant.layouts.app')

@section('content')
    <div class="expert-home w-full min-w-0 pb-24 lg:pb-8">
        {{-- Отступ под фиксированную шапку (h 4.5rem / 5rem / 5.5rem) + небольшой зазор --}}
        <div class="expert-home-main mx-auto max-w-[min(88rem,calc(100vw-1.5rem))] px-3 pt-[calc(3.75rem+0.5rem)] sm:px-4 md:px-8 md:pt-[calc(5rem+0.75rem)] lg:px-12 lg:pt-[calc(5.5rem+1rem)]">
            @forelse ($homeLayoutSections as $section)
                @php
                    $sk = (string) ($section->section_key ?? '');
                    $skClass = $sk !== '' ? 'expert-home-section--'.preg_replace('/[^a-z0-9_-]+/i', '-', $sk) : 'expert-home-section--unknown';
                @endphp
                <div class="expert-home-section {{ $skClass }}" data-section-key="{{ e($sk) }}">
                    @include('tenant.pages.partials.home-section-slot', [
                        'section' => $section,
                        'bikes' => $bikes ?? collect(),
                        'badges' => $badges ?? [],
                        'faqs' => $faqs ?? collect(),
                        'reviews' => $reviews ?? collect(),
                    ])
                </div>
            @empty
                <p class="text-center text-silver">Главная страница ещё не настроена.</p>
            @endforelse
        </div>
    </div>
@endsection
