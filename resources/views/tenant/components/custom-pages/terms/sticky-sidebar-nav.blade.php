@props([
    'sections' => []
])

@if(count($sections) > 0)
    <!-- Desktop Sticky Sidebar -->
    <div class="hidden w-80 shrink-0 lg:block">
        <div class="sticky top-28 flex flex-col gap-1.5">
            <h3 class="mb-4 px-1 text-sm font-bold uppercase tracking-wider text-white/90">Содержание</h3>
            <nav class="flex flex-col gap-2 border-l border-white/10 pl-1" aria-label="Навигация по условиям">
                @foreach($sections as $id => $label)
                    <a href="#{{ $id }}"
                       data-terms-nav-link="{{ $id }}"
                       @click.prevent="window.location.hash = '{{ $id }}'; document.getElementById('{{ $id }}')?.scrollIntoView({ behavior: 'smooth' })"
                       class="group terms-nav-link relative flex items-center rounded-lg py-3.5 pl-4 pr-3 text-sm font-medium text-silver/80 transition-colors duration-200 hover:bg-white/[0.08] hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-moto-amber/45">
                        <span class="absolute -left-px top-1/2 h-9 w-0.5 -translate-y-1/2 rounded-full bg-moto-amber opacity-0 transition-opacity duration-200 group-hover:opacity-80 group-focus-visible:opacity-100" aria-hidden="true"></span>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>

    <!-- Mobile Jump/Accordion Nav (Compact) -->
    <div class="relative z-20 mb-8 w-full lg:hidden" x-data="{ open: false }">
        <button @click="open = !open"
                type="button"
                class="flex w-full items-center justify-between rounded-xl border border-white/10 bg-obsidian/80 px-4 py-3.5 text-sm font-semibold text-white shadow-sm ring-1 ring-inset ring-white/5 focus:outline-none focus:ring-2 focus:ring-moto-amber"
                aria-haspopup="true"
                :aria-expanded="open.toString()">
            <span class="flex items-center gap-2">
                <svg class="h-4 w-4 text-moto-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                Содержание разделов
            </span>
            <svg class="h-4 w-4 text-silver transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>

        <div x-show="open"
             @click.away="open = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="absolute left-0 right-0 top-full z-50 mt-2 origin-top overflow-hidden rounded-xl border border-white/10 bg-obsidian shadow-xl shadow-black/40 ring-1 ring-white/5"
             style="display: none;">
            <div class="py-1">
                @foreach($sections as $id => $label)
                    <a href="#{{ $id }}"
                       @click="open = false; window.location.hash = '{{ $id }}'; setTimeout(() => document.getElementById('{{ $id }}')?.scrollIntoView({ behavior: 'smooth' }), 150)"
                       class="block px-4 py-4 text-sm font-medium text-silver/85 transition-colors duration-200 hover:bg-white/[0.08] hover:text-white focus:bg-white/[0.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-moto-amber/40">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <style>
        @foreach($sections as $navId => $navLabel)
        html:has(#{{ $navId }}:target) a[href="#{{ $navId }}"][data-terms-nav-link="{{ $navId }}"] {
            background-color: rgba(255, 255, 255, 0.1);
            color: rgb(255 255 255);
            box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.38);
        }
        @endforeach
    </style>
@endif
