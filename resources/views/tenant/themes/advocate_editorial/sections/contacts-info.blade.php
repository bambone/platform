@php
    use App\PageBuilder\Contacts\ContactChannelsResolver;

    $presentation = app(ContactChannelsResolver::class)->present(is_array($data ?? null) ? $data : []);
    $allRows = array_merge($presentation->primaryChannels, $presentation->secondaryChannels);
@endphp
@if(! $presentation->shouldRenderShell())
@else
<section
    class="advocate-contacts-premium-card relative w-full min-w-0 overflow-hidden rounded-[1.75rem] border border-white/10 bg-gradient-to-br from-[#10131c] via-[#0b0d14] to-[#050609] p-6 shadow-[0_36px_96px_-36px_rgba(0,0,0,0.65)] ring-1 ring-inset ring-white/[0.06] sm:p-9 lg:p-10"
    data-page-section-type="{{ $section->section_type }}"
>
    <div class="pointer-events-none absolute -right-24 top-0 h-48 w-48 rounded-full bg-moto-amber/[0.07] blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -left-16 bottom-0 h-40 w-40 rounded-full bg-white/[0.04] blur-3xl" aria-hidden="true"></div>

    <div class="relative z-10">
        @if($presentation->hasSectionHeading())
            <h2 class="mb-3 text-balance text-2xl font-bold tracking-tight text-white sm:text-[1.65rem]">{{ $presentation->title }}</h2>
        @endif
        @if($presentation->hasDescription())
            <p class="mb-5 max-w-3xl text-pretty text-[15px] leading-relaxed text-silver/85 sm:text-base">{{ $presentation->description }}</p>
        @endif
        @if($presentation->hasAdditionalNote())
            <p class="mb-8 max-w-3xl border-l-2 border-moto-amber/40 pl-4 text-pretty text-sm leading-relaxed text-silver/80 sm:text-[15px]">{{ $presentation->additionalNote }}</p>
        @endif

        @if($allRows !== [] || $presentation->hasAddress() || $presentation->hasWorkingHours())
            <dl class="grid gap-5 text-sm text-silver sm:grid-cols-2 sm:gap-x-8 sm:gap-y-6 sm:text-base">
                @foreach($allRows as $ch)
                    @include('tenant.partials.contact-channel-link', ['channel' => $ch, 'variant' => 'default_dl'])
                @endforeach
                @if($presentation->hasAddress())
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-bold uppercase tracking-[0.16em] text-white/45">Адрес офиса</dt>
                        <dd class="mt-2 whitespace-pre-line text-[15px] leading-relaxed text-white/90">{{ $presentation->address }}</dd>
                    </div>
                @endif
                @if($presentation->hasWorkingHours())
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-bold uppercase tracking-[0.16em] text-white/45">Режим работы</dt>
                        <dd class="mt-2 whitespace-pre-line text-[15px] leading-relaxed text-white/90">{{ $presentation->workingHours }}</dd>
                    </div>
                @endif
            </dl>
        @endif

        @if($presentation->hasMap())
            <div class="mt-8 rounded-xl border border-white/12 bg-white/[0.04] px-4 py-7 text-center sm:px-6">
                <x-custom-pages.contacts.map-block :view="$presentation->mapBlock" />
            </div>
        @elseif(! $presentation->hasMap() && ($allRows !== [] || $presentation->hasAddress()))
            {{-- каналы уже есть, пустой map не показываем --}}
        @else
            <div class="mt-8 rounded-xl border border-dashed border-white/12 bg-white/[0.02] px-4 py-6 text-center sm:px-6">
                <p class="text-sm text-silver/75">Добавьте провайдера и ссылку на карту в настройках блока «Контакты» в конструкторе страницы.</p>
            </div>
        @endif
    </div>
</section>
@endif
