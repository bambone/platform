@php
    $t = isset($data['title']) ? trim((string) $data['title']) : '';
    $desc = $data['description'] ?? '';
    $phone = $data['phone'] ?? '';
    $email = $data['email'] ?? '';
    $wa = $data['whatsapp'] ?? '';
    $tgRaw = rtrim(ltrim((string) ($data['telegram'] ?? ''), '@'), '/');
    $tgUsernameValid = $tgRaw !== '' && (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_]{3,31}$/', $tgRaw);
    $tgUrl = $tgUsernameValid ? 'https://t.me/'.$tgRaw : 'https://t.me/';
    $addr = $data['address'] ?? '';
    $hours = $data['working_hours'] ?? '';
    $mapEmbed = $data['map_embed'] ?? '';
    $mapLink = $data['map_link'] ?? '';
    $waDigits = preg_replace('/\D+/', '', (string) $wa) ?? '';
    $hasMap = filled($mapEmbed) || filled($mapLink);
    $hasTelegramField = $tgRaw !== '';
    $hasAnyChannel = filled($phone) || filled($email) || filled($wa) || $hasTelegramField || filled($addr) || filled($hours);
    $phoneTel = filled($phone) ? preg_replace('/[^\d+]/', '', (string) $phone) : '';
    $showMapBlock = $hasMap || filled($addr);
    $ctaCount = (filled($phone) ? 1 : 0) + ($waDigits !== '' ? 1 : 0) + ($hasTelegramField ? 1 : 0);
    $ctaGridClass = $ctaCount >= 3
        ? 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5 xl:gap-6'
        : ($ctaCount === 2 ? 'grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 xl:gap-6' : 'grid grid-cols-1 gap-4 xl:max-w-4xl');
@endphp
@if($t === '' && ! $hasAnyChannel && ! $hasMap && ! filled($desc))
@else
<section class="w-full min-w-0" data-page-section-type="{{ $section->section_type }}">
    @if(filled($t))
        <h2 class="mb-3 text-2xl font-bold tracking-tight text-white sm:text-3xl md:text-[1.75rem]">{{ $t }}</h2>
    @endif
    @if(filled($desc))
        <p class="mb-8 max-w-2xl text-base leading-relaxed text-white/80 sm:mb-10 sm:text-lg">{{ $desc }}</p>
    @endif

    @if($ctaCount > 0)
        <div class="mb-2">
            <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-moto-amber/80">Связаться сейчас</p>
            <div class="{{ $ctaGridClass }} auto-rows-fr items-stretch">
                @if(filled($phone))
                    <a href="tel:{{ $phoneTel }}"
                       class="group flex min-h-[168px] flex-col justify-between rounded-2xl border border-moto-amber/30 bg-gradient-to-b from-obsidian/95 to-carbon/85 p-7 shadow-lg shadow-black/35 ring-1 ring-inset ring-white/5 transition-all duration-200 hover:-translate-y-0.5 hover:border-moto-amber/55 hover:shadow-xl hover:shadow-moto-amber/15 focus-visible:outline focus-visible:ring-2 focus-visible:ring-moto-amber/50 sm:min-h-[176px] sm:p-8">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-moto-amber/15 text-moto-amber ring-1 ring-moto-amber/30 transition group-hover:bg-moto-amber/25" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </span>
                        <div class="mt-5">
                            <span class="block text-lg font-semibold text-white sm:text-xl">Позвонить</span>
                            <span class="mt-1.5 block text-sm text-silver/90 transition group-hover:text-white">{{ $phone }}</span>
                        </div>
                    </a>
                @endif
                @if($waDigits !== '')
                    <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener noreferrer"
                       class="group flex min-h-[168px] flex-col justify-between rounded-2xl border border-[#25D366]/35 bg-gradient-to-b from-obsidian/95 to-carbon/85 p-7 shadow-lg shadow-black/35 ring-1 ring-inset ring-white/5 transition-all duration-200 hover:-translate-y-0.5 hover:border-[#25D366]/60 hover:shadow-xl hover:shadow-[#25D366]/15 focus-visible:outline focus-visible:ring-2 focus-visible:ring-[#25D366]/50 sm:min-h-[176px] sm:p-8"
                       aria-label="Написать в WhatsApp">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#25D366]/15 text-[#25D366] ring-1 ring-[#25D366]/35 transition group-hover:bg-[#25D366]/25" aria-hidden="true">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                        </span>
                        <div class="mt-5">
                            <span class="block text-lg font-semibold text-white sm:text-xl">Написать в WhatsApp</span>
                            <span class="mt-1.5 block text-sm text-silver/90">Откроется чат в приложении</span>
                        </div>
                    </a>
                @endif
                @if($hasTelegramField)
                    <a href="{{ $tgUrl }}" target="_blank" rel="noopener noreferrer"
                       class="group flex min-h-[168px] flex-col justify-between rounded-2xl border border-[#2AABEE]/35 bg-gradient-to-b from-obsidian/95 to-carbon/85 p-7 shadow-lg shadow-black/35 ring-1 ring-inset ring-white/5 transition-all duration-200 hover:-translate-y-0.5 hover:border-[#2AABEE]/60 hover:shadow-xl hover:shadow-[#2AABEE]/15 focus-visible:outline focus-visible:ring-2 focus-visible:ring-[#2AABEE]/50 sm:min-h-[176px] sm:p-8"
                       aria-label="Написать в Telegram">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#2AABEE]/15 text-[#2AABEE] ring-1 ring-[#2AABEE]/35 transition group-hover:bg-[#2AABEE]/25" aria-hidden="true">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                        </span>
                        <div class="mt-5">
                            @if($tgUsernameValid)
                                <span class="block text-lg font-semibold text-white sm:text-xl">Написать в Telegram</span>
                                <span class="mt-1.5 block text-sm text-silver/90 transition group-hover:text-white">{{ '@'.$tgRaw }}</span>
                            @else
                                <span class="block text-lg font-semibold text-white sm:text-xl">Telegram</span>
                                <span class="mt-1.5 block text-sm font-medium text-silver/85">Написать в Telegram</span>
                            @endif
                        </div>
                    </a>
                @endif
            </div>
        </div>
    @endif

    @if(filled($wa) && $waDigits === '')
        <div class="mb-8 rounded-xl border border-white/10 bg-obsidian/50 p-5 text-sm text-silver/90 ring-1 ring-inset ring-white/5 sm:p-6">
            <span class="font-medium text-white">WhatsApp</span>
            <p class="mt-2 break-words leading-relaxed">{{ $wa }}</p>
            <p class="mt-3 text-xs text-silver/65">Быстрее всего ответим по телефону или в Telegram — ссылки выше.</p>
        </div>
    @endif

    @if(filled($email) || filled($addr) || filled($hours))
        <div class="mt-10 rounded-2xl border border-white/5 bg-white/[0.02] p-6 ring-1 ring-inset ring-white/5 sm:mt-12 sm:p-7">
            @if(filled($email) || filled($addr))
                <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-silver/50">Дополнительно</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                    @if(filled($email))
                        <div class="flex items-start gap-4 rounded-xl border border-white/8 bg-obsidian/40 p-5 ring-1 ring-inset ring-white/5">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/5 text-moto-amber/90">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-medium text-white/90">Email</h3>
                                <a href="mailto:{{ $email }}" class="mt-1 block break-all text-sm text-silver/85 transition hover:text-moto-amber focus-visible:outline focus-visible:ring-2 focus-visible:ring-moto-amber/45 rounded-sm">{{ $email }}</a>
                            </div>
                        </div>
                    @endif
                    @if(filled($addr))
                        <div class="flex items-start gap-4 rounded-xl border border-white/8 bg-obsidian/40 p-5 ring-1 ring-inset ring-white/5">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/5 text-moto-amber/90">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-medium text-white/90">Адрес и выдача</h3>
                                <p class="mt-1 text-sm leading-relaxed text-silver/80">{{ $addr }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            @if(filled($hours))
                <div class="@if(filled($email) || filled($addr)) mt-6 border-t border-white/5 pt-6 @endif">
                    <x-custom-pages.contacts.working-hours :working-hours="$hours" />
                </div>
            @endif
        </div>
    @endif

    @if($showMapBlock)
        <div class="mt-12 border-t border-white/10 pt-12 sm:mt-14 sm:pt-14">
            @if($hasMap)
                <div class="overflow-hidden rounded-2xl ring-1 ring-white/10 shadow-2xl shadow-black/50">
                    <x-custom-pages.contacts.map-block :map-embed-code="$mapEmbed" :map-url="$mapLink" />
                </div>
            @else
                <div class="rounded-2xl border border-white/10 bg-obsidian/50 p-8 ring-1 ring-inset ring-white/5 sm:p-10">
                    <div class="mx-auto max-w-lg text-center">
                        <svg class="mx-auto mb-4 h-11 w-11 text-moto-amber/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        <p class="text-base font-medium text-white/90">Карта появится после уточнения точки выдачи</p>
                        @if(filled($addr))
                            <p class="mt-4 text-sm leading-relaxed text-silver/80">{{ $addr }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
</section>
@endif
