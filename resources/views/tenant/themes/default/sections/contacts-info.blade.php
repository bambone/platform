@php
    use App\PageBuilder\Contacts\ContactChannelsResolver;

    $presentation = app(ContactChannelsResolver::class)->present(is_array($data ?? null) ? $data : []);
    $allRows = array_merge($presentation->primaryChannels, $presentation->secondaryChannels);
@endphp
@if(! $presentation->shouldRenderShell())
@else
<section class="w-full min-w-0 rounded-xl border border-white/10 bg-white/[0.03] p-5 sm:p-6" data-page-section-type="{{ $section->section_type }}">
    @if($presentation->hasSectionHeading())
        <h2 class="mb-2 text-xl font-semibold text-white sm:text-2xl">{{ $presentation->title }}</h2>
    @endif
    @if($presentation->hasDescription())
        <p class="mb-6 text-sm leading-relaxed text-silver sm:text-base">{{ $presentation->description }}</p>
    @endif
    @if($presentation->hasAdditionalNote())
        <p class="mb-6 whitespace-pre-line text-sm text-silver/90">{{ $presentation->additionalNote }}</p>
    @endif

    @if($allRows !== [] || $presentation->hasAddress() || $presentation->hasWorkingHours())
        <dl class="grid gap-4 text-sm text-silver sm:grid-cols-2 sm:gap-6 sm:text-base">
            @foreach($allRows as $ch)
                @include('tenant.partials.contact-channel-link', ['channel' => $ch, 'variant' => 'default_dl'])
            @endforeach
            @if($presentation->hasAddress())
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wide text-white/50">Адрес</dt>
                    <dd class="mt-1 whitespace-pre-line text-white/90">{{ $presentation->address }}</dd>
                </div>
            @endif
            @if($presentation->hasWorkingHours())
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wide text-white/50">Режим работы</dt>
                    <dd class="mt-1 whitespace-pre-line text-white/90">{{ $presentation->workingHours }}</dd>
                </div>
            @endif
        </dl>
    @endif

    @if($presentation->hasMap())
        <div class="mt-6 rounded-xl border border-white/10 bg-white/[0.02] p-4 sm:p-6">
            <x-custom-pages.contacts.map-block :view="$presentation->mapBlock" />
        </div>
    @else
        <div class="mt-6 rounded-lg border border-dashed border-white/15 bg-white/[0.02] px-4 py-8 text-center sm:px-6">
            <p class="text-sm text-silver/80">Карту можно добавить в настройках блока: выберите провайдер и вставьте ссылку на объект или маршрут.</p>
        </div>
    @endif
</section>
@endif
