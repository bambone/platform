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
<section class="expert-cred-mega relative mb-14 min-w-0 sm:mb-20 lg:mb-28" x-data="{ credMore: false }">
    <div class="relative overflow-hidden rounded-[1.5rem] border border-white/[0.08] sm:rounded-[2rem] lg:rounded-[2.5rem]">
        @if($bgUrl !== '')
            <img src="{{ e($bgUrl) }}" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-cover object-center opacity-40 mix-blend-overlay" loading="lazy" decoding="async" width="1600" height="900">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-[#060810]/95 via-[#060810]/70 to-[#030408]/95"></div>
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-[#060810]/90 via-transparent to-transparent"></div>
        @else
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-[#0a0d14] to-[#050608]"></div>
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-moto-amber/[0.05] via-transparent to-transparent"></div>
        @endif

        <div class="relative z-10 px-4 py-10 sm:px-8 sm:py-14 lg:px-14 lg:py-20">
            <div class="grid min-w-0 items-start gap-10 sm:gap-12 lg:grid-cols-12 lg:gap-16 xl:gap-20">
                <div class="{{ $supportUrl !== '' ? 'lg:col-span-7 xl:col-span-7' : 'lg:col-span-12' }} flex min-w-0 flex-col justify-center">
                    @if($h !== '')
                        <h2 class="expert-section-title text-balance text-[clamp(1.65rem,4vw,3rem)] font-bold tracking-tight text-white/95 leading-[1.12] sm:leading-[1.1]">{{ $h }}</h2>
                    @endif
                    @if($lead !== '')
                        <p class="mt-6 max-w-2xl text-[15px] font-normal leading-[1.6] text-silver/85 sm:text-lg">{{ $lead }}</p>
                    @endif

                    <div class="mt-6 grid min-w-0 gap-2.5 sm:mt-10 sm:grid-cols-2 sm:gap-5 lg:mt-12 xl:gap-6">
                        @foreach($items as $index => $item)
                            <div
                                class="expert-cred-mega__card group flex min-w-0 flex-col rounded-xl border px-4 py-4 transition-all duration-300 sm:rounded-2xl sm:px-6 sm:py-6 {{ $index === 0 ? 'sm:col-span-2 border-moto-amber/30 bg-gradient-to-br from-moto-amber/[0.05] to-transparent shadow-[0_18px_40px_-12px_rgba(0,0,0,0.55)] ring-1 ring-inset ring-white/[0.04] sm:px-8 sm:py-8' : 'border-white/[0.06] bg-white/[0.02] hover:bg-white/[0.04]' }}"
                                @if($index >= 4)
                                    x-bind:class="{ 'max-lg:hidden': !credMore }"
                                @endif
                            >
                                <h3 class="text-xl font-bold leading-snug tracking-wide {{ $index === 0 ? 'text-moto-amber sm:text-2xl' : 'text-white/90 sm:text-[1.35rem]' }}">
                                    {{ $item['title'] ?? '' }}
                                </h3>
                                @if(filled($item['description'] ?? ''))
                                    <p class="mt-3 text-[14px] leading-relaxed {{ $index === 0 ? 'text-silver/90 sm:mt-4 sm:max-w-xl sm:text-[15px]' : 'text-silver/70' }}">
                                        {{ $item['description'] }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                        @if(count($items) > 4)
                            <div class="col-span-full flex justify-center pt-1 sm:col-span-2 lg:hidden">
                                <button type="button" class="min-h-11 rounded-full border border-white/12 bg-white/[0.04] px-5 py-2 text-sm font-semibold text-white/88" @click="credMore = !credMore" x-text="credMore ? 'Свернуть' : 'Показать ещё'"></button>
                            </div>
                        @endif
                    </div>
                </div>

                @if($supportUrl !== '')
                    <div class="min-w-0 lg:col-span-5 xl:col-span-5 lg:pl-2 xl:pl-4">
                        <figure class="relative mx-auto max-w-md overflow-hidden rounded-[1.35rem] border border-white/10 shadow-[0_32px_80px_-24px_rgba(0,0,0,0.8)] ring-1 ring-inset ring-white/[0.05] sm:rounded-[1.75rem] lg:mx-0 lg:max-w-none">
                            <img src="{{ e($supportUrl) }}" alt="{{ e($supportAlt) }}" class="aspect-[4/5] w-full object-cover object-[center_35%] transition-transform duration-[1.5s] hover:scale-105 sm:aspect-[3/4] lg:aspect-[4/5]" loading="lazy" decoding="async" width="640" height="800">
                            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#050608]/80 via-transparent to-transparent"></div>
                        </figure>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
