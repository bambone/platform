@php
    use App\PageBuilder\Contacts\ContactChannelsResolver;

    $presentation = app(ContactChannelsResolver::class)->present(is_array($data ?? null) ? $data : []);
    $heading = $data['heading'] ?? '';
    $desc = $data['description'] ?? '';
    $hasChannel = filled($data['phone'] ?? '')
        || filled($data['whatsapp'] ?? '')
        || filled($data['telegram'] ?? '')
        || filled($data['email'] ?? '')
        || filled($data['social_note'] ?? '')
        || filled($data['address'] ?? '')
        || $presentation->hasMap();
    if (! filled($heading) && ! filled($desc) && ! $hasChannel) {
        return;
    }
@endphp
<section class="rounded-2xl border border-white/10 bg-white/5 p-6 sm:p-8">
    @if(filled($heading))
        <h2 class="text-xl font-bold text-white sm:text-2xl">{{ $heading }}</h2>
    @endif
    @if(filled($desc))
        <p class="mt-3 text-silver">{{ $desc }}</p>
    @endif
    @php
        $hasList = filled($data['phone'] ?? '')
            || filled($data['email'] ?? '')
            || filled($data['whatsapp'] ?? '')
            || filled($data['telegram'] ?? '')
            || filled($data['social_note'] ?? '')
            || filled($data['address'] ?? '');
    @endphp
    @if($hasList)
        <ul class="mt-4 space-y-2 text-sm text-silver">
            @if(filled($data['phone'] ?? ''))
                <li><span class="text-white/80">Телефон:</span> {{ $data['phone'] }}</li>
            @endif
            @if(filled($data['email'] ?? ''))
                <li><span class="text-white/80">Email:</span> <a href="mailto:{{ e($data['email']) }}" class="text-amber-400 underline hover:text-amber-300">{{ $data['email'] }}</a></li>
            @endif
            @if(filled($data['whatsapp'] ?? ''))
                <li><span class="text-white/80">WhatsApp:</span> {{ $data['whatsapp'] }}</li>
            @endif
            @if(filled($data['telegram'] ?? ''))
                <li><span class="text-white/80">Telegram:</span> {{ $data['telegram'] }}</li>
            @endif
            @if(filled($data['social_note'] ?? ''))
                <li><span class="text-white/80">Соцсети:</span> {{ $data['social_note'] }}</li>
            @endif
            @if(filled($data['address'] ?? ''))
                <li><span class="text-white/80">Адрес:</span> {{ $data['address'] }}</li>
            @endif
        </ul>
    @endif
    @if($presentation->hasMap())
        <div class="mt-6">
            <x-custom-pages.contacts.map-block :view="$presentation->mapBlock" />
        </div>
    @endif
</section>
