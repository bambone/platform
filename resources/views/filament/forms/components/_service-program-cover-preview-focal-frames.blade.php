@php
    $tiles = $tiles ?? [];
    $overlayMobile = $overlayMobile ?? ['svc-program-mask-fade-start' => '78%', 'svc-program-mask-fade-mid' => '90%'];
    $overlayDesktop = $overlayDesktop ?? ['svc-program-mask-fade-start' => '80%', 'svc-program-mask-fade-mid' => '91%'];
    $focalFrameOuterClass = (string) ($focalFrameOuterClass ?? 'w-full max-w-4xl');

    $byKey = collect($tiles)->keyBy(fn ($t) => (string) ($t['key'] ?? ''));
    $tileMobile = $byKey->get('mobile', []);
    $tileTablet = $byKey->get('tablet', []);
    $tileDesktop = $byKey->get('desktop', []);

    $fadeMidM = $overlayMobile['svc-program-mask-fade-mid'] ?? '90%';
    $mobileGradientStyle = sprintf(
        'background: linear-gradient(to bottom, transparent 0%%, transparent 35%%, rgba(0,0,0,0.05) 50%%, rgba(0,0,0,0.2) %s, rgba(0,0,0,0.45) 100%%);',
        e((string) $fadeMidM)
    );
    $tabletGradientStyle = sprintf(
        'background: linear-gradient(to bottom, transparent 0%%, transparent 35%%, rgba(0,0,0,0.05) 50%%, rgba(0,0,0,0.2) %s, rgba(0,0,0,0.45) 100%%);',
        e((string) $fadeMidM)
    );
    $fadeMidD = $overlayDesktop['svc-program-mask-fade-mid'] ?? '91%';
    $desktopGradientStyle = sprintf(
        'background: linear-gradient(to bottom, transparent 0%%, transparent 35%%, rgba(0,0,0,0.05) 50%%, rgba(0,0,0,0.2) %s, rgba(0,0,0,0.45) 100%%);',
        e((string) $fadeMidD)
    );
@endphp
<div class="{{ e($focalFrameOuterClass) }}">
    @php
        $mSrc = $tileMobile['src'] ?? null;
        $mOk = filled($mSrc);
    @endphp
    <div x-show="activeViewport === 'mobile'" x-cloak class="w-full min-w-0">
        <div
            class="svc-program-focal-frame touch-none relative isolate overflow-hidden rounded-md border border-gray-200 bg-zinc-900/5 dark:border-white/10 dark:bg-zinc-950/40"
            :class="[ framePointerClass('mobile'), previewShowFullImage &amp;&amp; 'ring-1 ring-amber-500/20' ]"
            x-bind:style="frameOuterStyle('mobile')"
            x-init="frameRefs.mobile = $el"
            tabindex="0"
            role="group"
            aria-label="Кадрирование mobile"
            @pointerdown.capture="startDrag('mobile', $event)"
            @keydown="if ($event.target === $el) { const s = $event.shiftKey; const k = $event.key; if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(k)) { $event.preventDefault(); let dx = 0, dy = 0; if (k === 'ArrowLeft') { dx = -1; } if (k === 'ArrowRight') { dx = 1; } if (k === 'ArrowUp') { dy = -1; } if (k === 'ArrowDown') { dy = 1; } nudge('mobile', dx, dy, s); } }"
        >
            @if ($mOk)
                <div
                    x-show="!isNaturalReady('mobile')"
                    x-cloak
                    class="absolute inset-0 z-20 flex items-center justify-center bg-white/80 text-[10px] text-gray-600 dark:bg-gray-900/85 dark:text-gray-300"
                >Загрузка изображения…</div>
                <div class="pointer-events-none absolute inset-0 overflow-hidden bg-zinc-900 dark:bg-zinc-950">
                    <div class="pointer-events-none absolute inset-0" x-bind:style="layerTransformStyle('mobile')">
                        <img
                            src="{{ e($mSrc) }}"
                            alt=""
                            class="svc-program-focal-img pointer-events-none absolute inset-0 block h-full w-full select-none"
                            :class="previewShowFullImage ? 'object-contain' : 'object-cover'"
                            x-bind:style="{ objectPosition: objectPositionStyle('mobile') }"
                            draggable="false"
                            loading="lazy"
                            x-on:load="onImgLoad('mobile', $event)"
                            x-on:error="onImgError('mobile')"
                        />
                    </div>
                </div>
                <div
                    x-show="!previewShowFullImage &amp;&amp; (config &amp;&amp; config.showFocalSafeAreaOverlay !== false)"
                    x-cloak
                    class="pointer-events-none absolute inset-x-0 bottom-0 z-20 border-t border-dashed border-amber-500/60 bg-amber-500/10"
                    x-bind:style="safeAreaStyle('mobile')"
                    x-bind:title="(config &amp;&amp; config.safeAreaLabel) ? config.safeAreaLabel : 'Текст / CTA'"
                ></div>
                <div
                    x-show="!previewShowFullImage"
                    x-cloak
                    class="pointer-events-none absolute inset-0 z-10 opacity-85"
                    style="<?php echo e($mobileGradientStyle); ?>"
                ></div>
            @else
                <div class="flex h-full min-h-[6rem] items-center justify-center p-2 text-center text-[10px] text-gray-400">Нет изображения для превью</div>
            @endif
        </div>
    </div>

    @php
        $tSrc = $tileTablet['src'] ?? null;
        $tOk = filled($tSrc);
    @endphp
    <div x-show="activeViewport === 'tablet'" x-cloak class="mx-auto w-full min-w-0 max-w-xl">
        <p class="mb-1 text-[10px] font-medium uppercase tracking-wide text-amber-800/90 dark:text-amber-200/80">Только preview</p>
        <div
            class="svc-program-focal-frame touch-none relative isolate overflow-hidden rounded-md border border-gray-200 bg-zinc-900/5 dark:border-white/10 dark:bg-zinc-950/40"
            :class="[ framePointerClass('tablet'), previewShowFullImage &amp;&amp; 'ring-1 ring-amber-500/20' ]"
            x-bind:style="frameOuterStyle('tablet')"
            x-init="frameRefs.tablet = $el"
            tabindex="0"
            role="group"
            aria-label="Превью tablet"
            @pointerdown.capture="startDrag('tablet', $event)"
            @keydown="if ($event.target === $el) { const s = $event.shiftKey; const k = $event.key; if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(k)) { $event.preventDefault(); let dx = 0, dy = 0; if (k === 'ArrowLeft') { dx = -1; } if (k === 'ArrowRight') { dx = 1; } if (k === 'ArrowUp') { dy = -1; } if (k === 'ArrowDown') { dy = 1; } nudge('tablet', dx, dy, s); } }"
        >
            @if ($tOk)
                <div
                    x-show="!isNaturalReady('tablet')"
                    x-cloak
                    class="absolute inset-0 z-20 flex items-center justify-center bg-white/80 text-[10px] text-gray-600 dark:bg-gray-900/85 dark:text-gray-300"
                >Загрузка изображения…</div>
                <div class="pointer-events-none absolute inset-0 overflow-hidden bg-zinc-900 dark:bg-zinc-950">
                    <div class="pointer-events-none absolute inset-0" x-bind:style="layerTransformStyle('tablet')">
                        <img
                            src="{{ e($tSrc) }}"
                            alt=""
                            class="svc-program-focal-img pointer-events-none absolute inset-0 block h-full w-full select-none"
                            :class="previewShowFullImage ? 'object-contain' : 'object-cover'"
                            x-bind:style="{ objectPosition: objectPositionStyle('tablet') }"
                            draggable="false"
                            loading="lazy"
                            x-on:load="onImgLoad('tablet', $event)"
                            x-on:error="onImgError('tablet')"
                        />
                    </div>
                </div>
                <div
                    x-show="!previewShowFullImage &amp;&amp; (config &amp;&amp; config.showFocalSafeAreaOverlay !== false)"
                    x-cloak
                    class="pointer-events-none absolute inset-x-0 bottom-0 z-20 border-t border-dashed border-amber-500/60 bg-amber-500/10"
                    x-bind:style="safeAreaStyle('tablet')"
                    x-bind:title="(config &amp;&amp; config.safeAreaLabel) ? config.safeAreaLabel : 'Текст / CTA'"
                ></div>
                <div
                    x-show="!previewShowFullImage"
                    x-cloak
                    class="pointer-events-none absolute inset-0 z-10 opacity-85"
                    style="<?php echo e($tabletGradientStyle); ?>"
                ></div>
            @else
                <div class="flex h-full min-h-[6rem] items-center justify-center p-2 text-center text-[10px] text-gray-400">Нет изображения для превью</div>
            @endif
        </div>
    </div>

    @php
        $dSrc = $tileDesktop['src'] ?? null;
        $dOk = filled($dSrc);
    @endphp
    <div x-show="activeViewport === 'desktop'" x-cloak class="w-full min-w-0">
        <div
            class="svc-program-focal-frame touch-none relative isolate overflow-hidden rounded-md border border-gray-200 bg-zinc-900/5 dark:border-white/10 dark:bg-zinc-950/40"
            :class="[ framePointerClass('desktop'), previewShowFullImage &amp;&amp; 'ring-1 ring-amber-500/20' ]"
            x-bind:style="frameOuterStyle('desktop')"
            x-init="frameRefs.desktop = $el"
            tabindex="0"
            role="group"
            aria-label="Кадрирование desktop"
            @pointerdown.capture="startDrag('desktop', $event)"
            @keydown="if ($event.target === $el) { const s = $event.shiftKey; const k = $event.key; if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(k)) { $event.preventDefault(); let dx = 0, dy = 0; if (k === 'ArrowLeft') { dx = -1; } if (k === 'ArrowRight') { dx = 1; } if (k === 'ArrowUp') { dy = -1; } if (k === 'ArrowDown') { dy = 1; } nudge('desktop', dx, dy, s); } }"
        >
            @if ($dOk)
                <div
                    x-show="!isNaturalReady('desktop')"
                    x-cloak
                    class="absolute inset-0 z-20 flex items-center justify-center bg-white/80 text-[10px] text-gray-600 dark:bg-gray-900/85 dark:text-gray-300"
                >Загрузка изображения…</div>
                <div class="pointer-events-none absolute inset-0 overflow-hidden bg-zinc-900 dark:bg-zinc-950">
                    <div class="pointer-events-none absolute inset-0" x-bind:style="layerTransformStyle('desktop')">
                        <img
                            src="{{ e($dSrc) }}"
                            alt=""
                            class="svc-program-focal-img pointer-events-none absolute inset-0 block h-full w-full select-none"
                            :class="previewShowFullImage ? 'object-contain' : 'object-cover'"
                            x-bind:style="{ objectPosition: objectPositionStyle('desktop') }"
                            draggable="false"
                            loading="lazy"
                            x-on:load="onImgLoad('desktop', $event)"
                            x-on:error="onImgError('desktop')"
                        />
                    </div>
                </div>
                <div
                    x-show="!previewShowFullImage &amp;&amp; (config &amp;&amp; config.showFocalSafeAreaOverlay !== false)"
                    x-cloak
                    class="pointer-events-none absolute inset-x-0 bottom-0 z-20 border-t border-dashed border-amber-500/60 bg-amber-500/10"
                    x-bind:style="safeAreaStyle('desktop')"
                    x-bind:title="(config &amp;&amp; config.safeAreaLabel) ? config.safeAreaLabel : 'Текст / CTA'"
                ></div>
                <div
                    x-show="!previewShowFullImage"
                    x-cloak
                    class="pointer-events-none absolute inset-0 z-10 opacity-85"
                    style="<?php echo e($desktopGradientStyle); ?>"
                ></div>
            @else
                <div class="flex h-full min-h-[6rem] items-center justify-center p-2 text-center text-[10px] text-gray-400">Нет изображения для превью</div>
            @endif
        </div>
    </div>
</div>
