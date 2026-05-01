@php
    /** @var \Filament\Forms\Components\ViewField $field */
    $tiles = $tiles ?? [];
    $safeArea = $safeArea ?? ['bottomPercent' => 38, 'label' => ''];
    $editorConfig = $editorConfig ?? [];
    $previewKey = $previewKey ?? '';
    $viewComponentKey = (string) ($viewComponentKey ?? $previewKey ?? '');
    $overlayMobile = $overlayMobile ?? ['svc-program-mask-fade-start' => '78%', 'svc-program-mask-fade-mid' => '90%'];
    $overlayDesktop = $overlayDesktop ?? ['svc-program-mask-fade-start' => '80%', 'svc-program-mask-fade-mid' => '91%'];
    $fieldId = $field->getId();
@endphp

<div
    wire:key="svc-cover-preview-{{ $viewComponentKey }}"
    data-svc-focal-preview
    class="space-y-3 rounded-lg border border-gray-200 bg-gray-50/80 p-3 dark:border-white/10 dark:bg-white/5"
    x-data="serviceProgramCoverFocalEditor(@js($editorConfig))"
>
    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">Кадрирование</p>
            <p class="mt-0.5 max-w-xl text-[11px] leading-snug text-gray-500 dark:text-gray-400" title="Подсказка">
                Перетащите кадр. Zoom — масштаб слоя (в hero можно ниже 1 — вписать фото по высоте кадра). Height — высота медиа-зоны. Tablet: только уточняющий preview.
            </p>
        </div>
    </div>

    <div
        class="inline-flex w-full max-w-md flex-wrap rounded-lg border border-gray-200/90 bg-white/60 p-0.5 dark:border-white/10 dark:bg-white/5"
        role="tablist"
        aria-label="Вид превью"
    >
        <button
            type="button"
            role="tab"
            class="min-w-0 flex-1 rounded-md px-2 py-1.5 text-center text-xs font-medium transition"
            :class="activeViewport === 'mobile' ? 'bg-amber-600 text-white shadow-sm dark:bg-amber-500' : 'text-gray-600 hover:bg-gray-100/80 dark:text-gray-300 dark:hover:bg-white/10'"
            :aria-selected="activeViewport === 'mobile'"
            @click="setActiveViewport('mobile')"
        >Mobile</button>
        <button
            type="button"
            role="tab"
            class="min-w-0 flex-1 rounded-md px-2 py-1.5 text-center text-xs font-medium transition"
            :class="activeViewport === 'tablet' ? 'bg-amber-600 text-white shadow-sm dark:bg-amber-500' : 'text-gray-600 hover:bg-gray-100/80 dark:text-gray-300 dark:hover:bg-white/10'"
            :aria-selected="activeViewport === 'tablet'"
            @click="setActiveViewport('tablet')"
        >Tablet</button>
        <button
            type="button"
            role="tab"
            class="min-w-0 flex-1 rounded-md px-2 py-1.5 text-center text-xs font-medium transition"
            :class="activeViewport === 'desktop' ? 'bg-amber-600 text-white shadow-sm dark:bg-amber-500' : 'text-gray-600 hover:bg-gray-100/80 dark:text-gray-300 dark:hover:bg-white/10'"
            :aria-selected="activeViewport === 'desktop'"
            @click="setActiveViewport('desktop')"
        >Desktop</button>
    </div>

    <p class="text-[10px] text-gray-500 dark:text-gray-400">
        <span x-text="(config.tileMeta &amp;&amp; config.tileMeta[activeViewport] &amp;&amp; config.tileMeta[activeViewport].sourceLabel) ? config.tileMeta[activeViewport].sourceLabel : ''"></span>
        <span class="text-gray-400" x-show="config.tileMeta &amp;&amp; config.tileMeta[activeViewport] &amp;&amp; config.tileMeta[activeViewport].role"> — </span>
        <span x-text="(config.tileMeta &amp;&amp; config.tileMeta[activeViewport]) ? (config.tileMeta[activeViewport].role || '') : ''"></span>
    </p>

    @include('filament.forms.components._service-program-cover-preview-focal-frames', [
        'tiles' => $tiles,
        'overlayMobile' => $overlayMobile,
        'overlayDesktop' => $overlayDesktop,
        'focalFrameOuterClass' => 'w-full max-w-4xl',
    ])

    @include('filament.forms.components._service-program-cover-preview-focal-editor-toolbar', [
        'fieldId' => $fieldId,
    ])
</div>
