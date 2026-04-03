@php
    $heading = $data['heading'] ?? 'Наш автопарк';
    $subheading = $data['subheading'] ?? null;
@endphp

@if (isset($bikes))
    @include('tenant.partials.home-motorcycle-catalog', [
        'bikes' => $bikes,
        'badges' => $badges ?? [],
        'heading' => $heading,
        'subheading' => $subheading,
    ])
@else
    <section id="catalog" class="relative z-10 border-t border-white/[0.02] bg-[#0c0c0e] py-8">
        <div class="mx-auto max-w-7xl px-4 text-sm text-silver">
            <p class="font-medium text-white">{{ $heading }}</p>
            <p class="mt-1">Этот блок показывает каталог только на главной странице сайта.</p>
        </div>
    </section>
@endif
