@extends('tenant.layouts.app')

@section('title', ($resolvedSeo ?? null)?->title ?? 'Контакты')

@section('content')
    <main class="expert-auto-theme min-w-0 pb-24 sm:pb-32">
        <section class="relative overflow-hidden pb-10 pt-[calc(4.5rem+1.5rem)] sm:pb-14 sm:pt-[calc(5rem+2rem)] lg:pb-16 lg:pt-[calc(5.5rem+2.5rem)]">
            <div class="pointer-events-none absolute -left-40 top-0 h-[40rem] w-[40rem] rounded-full bg-moto-amber/5 blur-[120px]" aria-hidden="true"></div>
            
            <div class="relative z-10 mx-auto max-w-6xl px-4 text-center sm:px-6 md:px-8">
                <span class="mb-6 inline-flex items-center justify-center rounded-full bg-moto-amber/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-widest text-moto-amber ring-1 ring-inset ring-moto-amber/30">
                    Контакты
                </span>
                <h1 class="text-balance text-[clamp(1.85rem,5.5vw,3.75rem)] font-extrabold leading-[1.1] tracking-tight text-white/95 sm:text-[clamp(2.25rem,5vw,4rem)]">{{ ($resolvedSeo ?? null)?->h1 ?? 'Связаться со мной' }}</h1>
                <p class="mx-auto mt-6 max-w-2xl text-[16px] leading-[1.7] text-silver/80 sm:text-[18px]">
                    Запишитесь на первое занятие или задайте любой вопрос. Подберем оптимальный формат под ваши задачи и текущий уровень навыков.
                </p>
            </div>
        </section>

        <section class="relative z-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 md:px-8">
                <div class="grid min-w-0 gap-5 md:grid-cols-2 md:gap-6 lg:gap-8">
                    
                    {{-- Главный контактный блок --}}
                    <div class="expert-contact-card relative min-w-0 overflow-hidden rounded-[1.5rem] border border-white/[0.08] bg-gradient-to-br from-[#12141c] to-[#0a0c12] p-6 shadow-[0_32px_80px_-24px_rgba(201,168,124,0.15)] sm:rounded-[2rem] sm:p-10 lg:p-12">
                        <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-moto-amber/10 blur-[80px]"></div>
                        
                        <h2 class="text-2xl font-bold text-white/95">Мои контакты</h2>
                        <p class="mt-3 text-[15px] leading-relaxed text-silver/70">Отвечаю лично, обычно в течение пары часов.</p>
                        
                        <div class="mt-8 space-y-6">
                            @if(filled(tenant()->phone))
                                <div class="flex items-center gap-5">
                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/[0.03] border border-white/[0.08] text-moto-amber">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    </span>
                                    <div>
                                        <p class="text-[12px] font-bold uppercase tracking-widest text-silver/50">Телефон</p>
                                        <a href="tel:{{ preg_replace('/[^+\d]/', '', tenant()->phone) }}" class="mt-1 block break-words text-lg font-semibold text-white/95 transition hover:text-moto-amber">{{ tenant()->phone }}</a>
                                    </div>
                                </div>
                            @endif
                            
                            @if(filled(tenant()->email))
                                <div class="flex items-center gap-5">
                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/[0.03] border border-white/[0.08] text-moto-amber">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    </span>
                                    <div>
                                        <p class="text-[12px] font-bold uppercase tracking-widest text-silver/50">Email</p>
                                        <a href="mailto:{{ tenant()->email }}" class="mt-1 block break-all text-lg font-semibold text-white/95 transition hover:text-moto-amber sm:break-words">{{ tenant()->email }}</a>
                                    </div>
                                </div>
                            @endif
                        </div>
                        
                        <div class="mt-10 border-t border-white/[0.06] pt-8">
                            <p class="text-[13px] font-bold uppercase tracking-widest text-silver/60 mb-5">Мессенджеры</p>
                            <div class="flex flex-wrap gap-2.5 sm:gap-3">
                                <a href="https://t.me/{{ ltrim(tenant()->telegram ?? '', '@') }}" target="_blank" rel="noopener noreferrer" class="group flex min-h-11 min-w-0 flex-1 items-center justify-center gap-2.5 rounded-xl border border-white/[0.08] bg-white/[0.03] px-4 py-3 transition hover:border-white/[0.15] hover:bg-white/[0.06] sm:flex-initial sm:justify-start sm:px-5">
                                    <svg class="h-5 w-5 text-[#38BDF8]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM16.64 9.11l-1.54 7.27c-.12.53-.43.66-.88.4L11.8 15l-1.18 1.12c-.13.13-.24.24-.49.24l.17-2.48 4.54-4.1c.2-.18-.04-.28-.31-.09l-5.6 3.53-2.41-.75c-.52-.16-.53-.52.11-.77l9.4-3.62c.44-.15.82.09.7.83z"/></svg>
                                    <span class="text-[14px] font-bold text-white/90 group-hover:text-white">Telegram</span>
                                </a>
                                <a href="https://wa.me/{{ preg_replace('/[^+\d]/', '', tenant()->whatsapp ?? '') }}" target="_blank" rel="noopener noreferrer" class="group flex min-h-11 min-w-0 flex-1 items-center justify-center gap-2.5 rounded-xl border border-white/[0.08] bg-white/[0.03] px-4 py-3 transition hover:border-white/[0.15] hover:bg-white/[0.06] sm:flex-initial sm:justify-start sm:px-5">
                                    <svg class="h-5 w-5 text-[#25D366]" viewBox="0 0 24 24" fill="currentColor"><path d="M17.47 6.53A7.95 7.95 0 0 0 12 4.2C7.59 4.2 4 7.79 4 12.2c0 1.4.37 2.76 1.07 3.96L4 20l3.96-1.04A7.9 7.9 0 0 0 12 20.2c4.41 0 8-3.59 8-8 0-2.14-.83-4.15-2.53-5.67zM12 18.47c-1.18 0-2.33-.31-3.34-.92l-.24-.14-2.48.65.66-2.42-.16-.25A6.23 6.23 0 0 1 5.72 12.2c0-3.46 2.82-6.28 6.28-6.28 1.68 0 3.25.65 4.43 1.84a6.23 6.23 0 0 1 1.83 4.43c0 3.47-2.82 6.28-6.26 6.28zM15.42 13.8c-.19-.09-1.11-.55-1.28-.61-.17-.06-.29-.09-.42.09-.12.18-.49.61-.59.74-.11.12-.22.14-.4.04-.19-.09-.8-.29-1.52-.92-.56-.5-.94-1.12-1.05-1.3-.11-.19-.01-.29.08-.38.08-.08.18-.21.28-.31.09-.1.12-.17.18-.28.06-.11.03-.22-.01-.31-.05-.09-.43-1.02-.59-1.4-.15-.36-.31-.31-.42-.32h-.36c-.15 0-.4.06-.61.28-.21.23-.8.78-.8 1.9s.82 2.2 1.05 2.5c.23.3 1.7 2.65 4.18 3.73.59.25 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.08.15-1.18-.06-.1-.23-.15-.42-.25z"/></svg>
                                    <span class="text-[14px] font-bold text-white/90 group-hover:text-white">WhatsApp</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Блок "Реквизиты и адрес" --}}
                    <div class="expert-address-card flex min-w-0 flex-col rounded-[1.5rem] border border-white/[0.05] bg-white/[0.015] p-6 transition-colors hover:bg-white/[0.02] sm:rounded-[2rem] sm:p-10 lg:p-12">
                        <h2 class="text-xl font-bold text-white/95">Для занятий в городе</h2>
                        <p class="mt-3 text-[14px] leading-relaxed text-silver/70">Встречи и выезды осуществляются по договоренности.</p>
                        
                        @if(filled(tenant()->address))
                            <div class="mt-8">
                                <p class="text-[11px] font-bold uppercase tracking-widest text-silver/50 mb-2 border-b border-white/[0.05] pb-2">Локация</p>
                                <p class="text-[15px] font-medium leading-[1.6] text-white/90">{{ tenant()->address }}</p>
                            </div>
                        @else
                            <div class="mt-8 rounded-xl border border-white/[0.04] bg-white/[0.01] p-5">
                                <p class="text-[14px] leading-relaxed text-silver/80">Обычно я подъезжаю в удобный для вас район или мы встречаемся у ближайшей станции метро.</p>
                            </div>
                        @endif
                        
                        <div class="mt-auto pt-10">
                            <p class="text-[11px] font-bold uppercase tracking-widest text-silver/50 mb-3 border-b border-white/[0.05] pb-2">Реквизиты</p>
                            <p class="text-[12px] leading-relaxed text-silver/60">
                                @if(filled(tenant()->company_name))
                                    {{ tenant()->company_name }}<br>
                                @endif
                                @if(filled(tenant()->inn))
                                    ИНН: {{ tenant()->inn }}<br>
                                @endif
                                Документы и сертификаты предоставляются по запросу.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
@endsection
