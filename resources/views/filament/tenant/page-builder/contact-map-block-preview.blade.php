@php
    /** @var array{status: string, message: string, resolved: ?\App\PageBuilder\Contacts\ContactMapResolvedView} $preview */
@endphp

<div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900/40">
    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Предпросмотр</p>
    @if($preview['resolved'] instanceof \App\PageBuilder\Contacts\ContactMapResolvedView)
        @php($v = $preview['resolved'])
        @if(! $v->shouldRenderMapBlock())
            <p class="text-sm text-gray-600 dark:text-gray-300">Предпросмотр появится после добавления валидной ссылки.</p>
        @else
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/15 dark:bg-slate-950/80">
                <x-custom-pages.contacts.map-block :view="$v" preview-variant="admin" />
            </div>
        @endif
    @else
        <p class="text-sm text-gray-600 dark:text-gray-300">Предпросмотр появится после добавления валидной ссылки.</p>
    @endif
</div>
