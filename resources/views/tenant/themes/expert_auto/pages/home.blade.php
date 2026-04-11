@php
    $sections = $sections ?? [];
    $homeLayoutSections = $homeLayoutSections ?? collect();
@endphp
@extends('tenant.layouts.app')

@section('content')
    <div class="expert-home w-full min-w-0 pb-24 lg:pb-8">
        <div class="expert-home-main mx-auto max-w-[min(88rem,calc(100vw-1.5rem))] px-3 pt-12 sm:px-4 sm:pt-16 md:px-8 lg:px-12 lg:pt-20">
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
