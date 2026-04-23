@php
    $d = is_array($data ?? null) ? $data : [];
    $groups = is_array($d['groups'] ?? null) ? $d['groups'] : [];
    $items = is_array($d['items'] ?? null) ? $d['items'] : [];
    $heading = (string) ($d['heading'] ?? 'Услуги');
@endphp
<section class="bd-section bd-service-hub" aria-labelledby="bd-hub-heading">
    <h2 id="bd-hub-heading" class="text-2xl font-semibold text-[var(--ex-ink)]">{{ $heading }}</h2>
    @if (count($groups) > 0)
        <div class="mt-10 flex flex-col gap-14">
            @foreach ($groups as $g)
                @if (!is_array($g))
                    @continue
                @endif
                @php
                    $gTitle = (string) ($g['title'] ?? '');
                    $gIntro = trim((string) ($g['intro'] ?? ''));
                    $gItems = is_array($g['items'] ?? null) ? $g['items'] : [];
                @endphp
                <div class="scroll-mt-24" id="bd-svc-group-{{ e((string) ($g['group_key'] ?? '')) }}">
                    @if ($gTitle !== '')
                        <h3 class="text-lg font-semibold text-white sm:text-xl">{{ $gTitle }}</h3>
                    @endif
                    @if ($gIntro !== '')
                        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-zinc-400 sm:text-base">{{ $gIntro }}</p>
                    @endif
                    @if (count($gItems) > 0)
                        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" role="list">
                            @foreach ($gItems as $it)
                                @include('tenant.themes.black_duck.components.service_hub_card', ['it' => $it])
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @elseif (count($items) > 0)
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" role="list">
            @foreach ($items as $it)
                @include('tenant.themes.black_duck.components.service_hub_card', ['it' => $it])
            @endforeach
        </ul>
    @endif
</section>
