@php
    use App\Tenant\Expert\ExpertBrandMediaUrl;
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $items = array_values(array_filter($items, fn ($i) => is_array($i) && trim((string) ($i['title'] ?? '')) !== ''));
    if ($items === []) {
        return;
    }
    $h = trim((string) ($data['section_heading'] ?? ''));
    $lead = trim((string) ($data['lead'] ?? ''));
    $bgUrl = trim((string) ($data['background_image_url'] ?? ''));
    if ($bgUrl === '' && isset($data['background_media_slot']) && is_array($data['background_media_slot'])) {
        $bgUrl = trim((string) ($data['background_media_slot']['url'] ?? ''));
    }
    $bgUrl = ExpertBrandMediaUrl::resolve($bgUrl);
    $supportUrl = ExpertBrandMediaUrl::resolve(trim((string) ($data['supporting_image_url'] ?? '')));
    $supportAlt = trim((string) ($data['supporting_image_alt'] ?? ''));
    if ($supportAlt === '') {
        $supportAlt = $h !== '' ? $h : 'Инструктор и спортивный опыт';
    }
@endphp
<section class="expert-cred-mega relative mb-16 sm:mb-24">
    <div class="relative overflow-hidden rounded-[1.35rem] border border-white/10">
        @if($bgUrl !== '')
            <img src="{{ e($bgUrl) }}" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-cover object-center opacity-30" loading="lazy" decoding="async" width="1600" height="900">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-[#05070c]/97 via-[#060810]/94 to-[#070910]"></div>
        @else
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-moto-amber/[0.06] via-transparent to-sky-500/[0.04]"></div>
        @endif

        <div class="relative z-10 px-5 py-12 sm:px-8 sm:py-14 lg:px-12 lg:py-16">
            <div class="grid items-start gap-10 lg:grid-cols-12 lg:gap-14">
                @if($supportUrl !== '')
                    <div class="lg:col-span-5">
                        <figure class="overflow-hidden rounded-2xl border border-white/12 shadow-[0_24px_70px_-20px_rgba(0,0,0,0.65)]">
                            <img src="{{ e($supportUrl) }}" alt="{{ e($supportAlt) }}" class="aspect-[4/5] w-full object-cover object-center sm:aspect-[3/4]" loading="lazy" decoding="async" width="640" height="800">
                        </figure>
                    </div>
                @endif
                <div class="{{ $supportUrl !== '' ? 'lg:col-span-7' : 'lg:col-span-12' }}">
                    @if($h !== '')
                        <h2 class="expert-section-title text-balance text-[clamp(1.6rem,3.6vw,2.55rem)] font-bold tracking-tight text-white">{{ $h }}</h2>
                    @endif
                    @if($lead !== '')
                        <p class="mt-5 max-w-2xl text-base leading-relaxed text-silver sm:text-lg">{{ $lead }}</p>
                    @endif

                    <div class="mt-9 grid gap-3 sm:grid-cols-2 sm:gap-4">
                        @foreach($items as $item)
                            <div class="expert-cred-mega__card rounded-xl border border-white/[0.08] bg-[#0a0c12]/65 px-5 py-4 backdrop-blur-md">
                                <h3 class="font-semibold text-white">{{ $item['title'] ?? '' }}</h3>
                                @if(filled($item['description'] ?? ''))
                                    <p class="mt-2 text-sm leading-relaxed text-silver">{{ $item['description'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
