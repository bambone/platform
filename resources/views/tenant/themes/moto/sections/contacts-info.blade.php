@php
    use App\PageBuilder\Contacts\ContactChannelsResolver;
    $presentation = app(ContactChannelsResolver::class)->present(is_array($data ?? null) ? $data : []);
    // Все основные каналы — в первом блоке (раньше показывали только 3, остальные прятали в «Дополнительно»).
    $primaryCta = $presentation->primaryChannels;
    $secondaryAll = $presentation->secondaryChannels;
    $ctaCount = count($primaryCta);
    $ctaGridClass = $ctaCount >= 3
        ? 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5 xl:gap-6'
        : ($ctaCount === 2 ? 'grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 xl:gap-6' : 'grid grid-cols-1 gap-4 xl:max-w-4xl');
    $hasMap = $presentation->hasMap();
    $showMapBlock = $hasMap || $presentation->hasAddress();
@endphp
@if(! $presentation->shouldRenderShell())
@else
<section class="w-full min-w-0" data-page-section-type="{{ $section->section_type }}">
    @if($presentation->hasSectionHeading())
        <h2 class="mb-3 text-2xl font-bold tracking-tight text-white sm:text-3xl md:text-[1.75rem]">{{ $presentation->title }}</h2>
    @endif
    @if($presentation->hasDescription())
        <p class="mb-8 max-w-2xl text-base leading-relaxed text-white/80 sm:mb-10 sm:text-lg">{{ $presentation->description }}</p>
    @endif
    @if($presentation->hasAdditionalNote())
        <div class="mb-8 rounded-xl border border-white/10 bg-obsidian/40 p-5 text-sm leading-relaxed text-silver/90 ring-1 ring-inset ring-white/5 sm:p-6">
            {{ $presentation->additionalNote }}
        </div>
    @endif

    @if($ctaCount > 0)
        <div class="mb-2">
            <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-moto-amber/80">Связаться сейчас</p>
            <div class="{{ $ctaGridClass }} auto-rows-fr items-stretch">
                @foreach($primaryCta as $ch)
                    @include('tenant.partials.contact-channel-link', ['channel' => $ch, 'variant' => 'moto_cta'])
                @endforeach
            </div>
        </div>
    @endif

    @if($secondaryAll !== [])
        <div class="mt-10 rounded-2xl border border-white/5 bg-white/[0.02] p-6 ring-1 ring-inset ring-white/5 sm:mt-12 sm:p-7">
            <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-silver/50">Дополнительно</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                @foreach($secondaryAll as $ch)
                    @include('tenant.partials.contact-channel-link', ['channel' => $ch, 'variant' => 'moto_compact'])
                @endforeach
            </div>
        </div>
    @endif

    @include('tenant.partials.contacts-section-meta', ['presentation' => $presentation])

    @if($showMapBlock)
        <div class="mt-12 border-t border-white/10 pt-12 sm:mt-14 sm:pt-14">
            @if($hasMap)
                <div class="overflow-hidden rounded-2xl ring-1 ring-white/10 shadow-2xl shadow-black/50 p-4 sm:p-6">
                    <x-custom-pages.contacts.map-block :view="$presentation->mapBlock" />
                </div>
            @else
                <div class="rounded-2xl border border-white/10 bg-obsidian/50 p-8 ring-1 ring-inset ring-white/5 sm:p-10">
                    <div class="mx-auto max-w-lg text-center">
                        <svg class="mx-auto mb-4 h-11 w-11 text-moto-amber/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        <p class="text-base font-medium text-white/90">Карта появится после уточнения точки выдачи</p>
                        @if($presentation->hasAddress())
                            <p class="mt-4 text-sm leading-relaxed text-silver/80">{{ $presentation->address }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
</section>
@endif
