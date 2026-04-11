@php
    use App\Tenant\Expert\ExpertBrandMediaUrl;
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $items = array_values(array_filter($items, fn ($i) => is_array($i) && trim((string) ($i['title'] ?? '')) !== ''));
    if ($items === []) {
        return;
    }
    $h = trim((string) ($data['section_heading'] ?? ''));
    $lead = trim((string) ($data['section_lead'] ?? ''));
    $fn = trim((string) ($data['footnote'] ?? ''));
    $accent = ExpertBrandMediaUrl::resolve(trim((string) ($data['accent_image_url'] ?? '')));
@endphp
<section class="expert-problems-mega relative mb-16 sm:mb-24">
    <div class="relative overflow-hidden rounded-[1.5rem] border border-white/[0.07] bg-gradient-to-br from-white/[0.035] to-transparent px-5 py-11 sm:px-8 sm:py-12 lg:px-11 lg:py-14">
        @if($accent !== '')
            <div class="pointer-events-none absolute inset-y-0 left-0 hidden w-[min(42%,20rem)] lg:block" aria-hidden="true">
                <img src="{{ e($accent) }}" alt="" class="h-full w-full object-cover object-center opacity-40">
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-[#070910]/80 to-[#070910]"></div>
            </div>
        @endif
        <div class="relative z-10">
            @if($h !== '')
                <h2 class="expert-section-title max-w-4xl text-balance text-[clamp(1.55rem,3.6vw,2.45rem)] font-bold leading-tight tracking-tight text-white">{{ $h }}</h2>
                @if($lead !== '')
                    <p class="mt-4 max-w-2xl text-base leading-relaxed text-silver/95 sm:mt-5 sm:text-lg">{{ $lead }}</p>
                @else
                    <p class="mt-4 max-w-2xl text-base leading-relaxed text-silver/95 sm:mt-5 sm:text-lg">Каждый запрос разбираем на практике — без шаблонных сценариев и лишнего давления.</p>
                @endif
            @endif

            <div class="mt-9 grid gap-4 sm:grid-cols-2 sm:gap-5 xl:grid-cols-3">
                @foreach($items as $item)
                    @php $featured = (bool) ($item['is_featured'] ?? false); @endphp
                    <article class="expert-problem-card flex flex-col rounded-2xl border p-5 sm:p-6 {{ $featured ? 'expert-problem-card--featured border-moto-amber/30 bg-gradient-to-br from-moto-amber/[0.09] to-white/[0.02] shadow-[0_16px_44px_-20px_rgba(201,168,124,0.28)]' : 'expert-problem-card--plain border-white/[0.07] bg-[#0a0d14]/70 backdrop-blur-sm' }}">
                        @if($featured)
                            <span class="mb-3 inline-flex w-fit rounded-full bg-moto-amber/20 px-3 py-1 text-[0.65rem] font-bold uppercase tracking-wider text-moto-amber">Частый запрос</span>
                        @endif
                        <h3 class="text-lg font-semibold text-white sm:text-xl">{{ $item['title'] ?? '' }}</h3>
                        @if(filled($item['description'] ?? ''))
                            <p class="mt-2.5 text-sm leading-relaxed text-silver/95">{{ $item['description'] }}</p>
                        @endif
                        @if(filled($item['solution'] ?? ''))
                            <p class="mt-3.5 border-t border-white/[0.08] pt-3.5 text-sm font-medium leading-relaxed text-moto-amber/92">{{ $item['solution'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>

            @if($fn !== '')
                <p class="mt-8 max-w-3xl text-sm leading-relaxed text-silver/85 sm:text-base">{{ $fn }}</p>
            @endif
        </div>
    </div>
</section>
