@php
    /** @var string $fieldId */
@endphp
<p
    x-show="commitError"
    x-cloak
    class="text-[11px] text-red-700 dark:text-red-300"
    x-text="commitError"
    role="alert"
></p>
<p
    class="text-[10px] leading-snug text-amber-800/90 dark:text-amber-200/80"
    x-show="axisSlackHint(activeViewport) !== ''"
    x-text="axisSlackHint(activeViewport)"
></p>
<p
    x-show="activeViewport === 'tablet' &amp;&amp; heightFactorEnabled()"
    x-cloak
    class="text-[10px] text-gray-500"
>Height на сайте = mobile: <span class="font-mono" x-text="(local.mobile.heightFactor ?? 1).toFixed(2)"></span></p>
<p
    x-show="sync"
    x-cloak
    class="text-[10px] text-gray-500"
>
    <span x-show="heightFactorEnabled()" x-cloak>Zoom синхронизируется между всеми размерами. Height в sync-режиме задаётся через mobile.</span>
    <span x-show="!heightFactorEnabled()" x-cloak>Позиция и zoom синхронизируются между всеми размерами.</span>
</p>

<div class="mt-1 space-y-2 border-t border-gray-200/80 pt-3 dark:border-white/10">
    <div x-show="showZoomSliderForActive()" class="flex flex-wrap items-center gap-2">
        <label class="text-xs font-medium text-gray-600 dark:text-gray-400" for="{{ $fieldId }}-focal-zoom-active">Zoom</label>
        <input
            id="{{ $fieldId }}-focal-zoom-active"
            type="range"
            class="h-2 min-w-0 max-w-sm flex-1 cursor-pointer accent-amber-600"
            :min="scaleBounds().min"
            :max="scaleBounds().max"
            :step="scaleBounds().step"
            :value="local[activeViewport].s"
            :disabled="previewShowFullImage"
            @input="onScaleInput(activeViewport, $event.target.value)"
            @change="commitScaleFromSlider()"
        />
        <span class="min-w-[3.5rem] text-xs font-mono text-gray-800 dark:text-gray-100" x-text="(local[activeViewport].s ?? 1).toFixed(2) + '×'"></span>
        <button
            type="button"
            class="fi-btn fi-btn-size-sm fi-color-gray rounded-lg px-2 py-1 text-[11px] leading-none"
            x-show="showZoomSliderForActive() &amp;&amp; canDrag(activeViewport)"
            x-cloak
            @click="fitScaleToFrameHeight(activeViewport)"
        >По высоте кадра</button>
    </div>
    <div x-show="showHeightSliderForActive()" x-cloak class="flex flex-wrap items-center gap-2">
        <label class="text-xs font-medium text-gray-600 dark:text-gray-400" for="{{ $fieldId }}-focal-height-active">Height</label>
        <input
            id="{{ $fieldId }}-focal-height-active"
            type="range"
            class="h-2 min-w-0 max-w-sm flex-1 cursor-pointer accent-amber-600"
            :min="heightBounds().min"
            :max="heightBounds().max"
            :step="heightBounds().step"
            :value="activeViewport === 'mobile' ? local.mobile.heightFactor : local.desktop.heightFactor"
            :disabled="previewShowFullImage"
            @input="onHeightInputForActive($event.target.value)"
            @change="commitHeightFromSlider()"
        />
        <div
            class="min-w-[3.5rem] rounded border border-amber-500/30 bg-amber-500/5 px-2 py-0.5 text-center font-mono text-xs text-amber-900 dark:text-amber-100"
            x-text="(activeViewport === 'mobile' ? (local.mobile.heightFactor ?? 1) : (local.desktop.heightFactor ?? 1)).toFixed(2)"
        ></div>
    </div>
    <div
        class="flex w-full min-w-0 flex-wrap items-baseline gap-x-3 gap-y-0.5 rounded border border-white/20 bg-white/5 px-2 py-1.5 text-xs text-gray-600 dark:border-white/10 dark:bg-white/[0.04] dark:text-gray-300"
    >
        <span class="shrink-0 font-mono whitespace-nowrap" title="X">X <span x-text="local[activeViewport].x.toFixed(1)"></span>%</span>
        <span class="shrink-0 font-mono whitespace-nowrap" title="Y">Y <span x-text="local[activeViewport].y.toFixed(1)"></span>%</span>
        <span class="shrink-0 font-mono whitespace-nowrap" title="Zoom">Z <span x-text="(local[activeViewport].s ?? 1).toFixed(2)"></span>×</span>
        <span
            x-show="activeViewport !== 'tablet' &amp;&amp; heightFactorEnabled()"
            x-cloak
            class="shrink-0 font-mono whitespace-nowrap"
            title="Height"
        >H <span
                x-text="(activeViewport === 'desktop' ? (local.desktop.heightFactor ?? 1) : (local.mobile.heightFactor ?? 1)).toFixed(2)"
            ></span></span>
    </div>
</div>

<details class="group rounded-md border border-gray-200/90 bg-white/30 text-xs text-gray-600 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-400">
    <summary class="cursor-pointer select-none px-2 py-1.5 font-medium text-gray-700 hover:bg-gray-100/50 dark:text-gray-300 dark:hover:bg-white/5">Сброс, копирование, весь кадр</summary>
    <div class="space-y-2 border-t border-gray-200/80 p-2 dark:border-white/10">
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="fi-btn fi-btn-size-sm fi-color-gray rounded-lg px-2 py-1 text-xs"
                @click="resetBoth()"
                x-show="sync"
            >Сбросить все</button>
            <button
                type="button"
                class="fi-btn fi-btn-size-sm fi-color-gray rounded-lg px-2 py-1 text-xs"
                @click="resetMobile()"
                x-show="!sync"
            >Сбросить mobile</button>
            <button
                type="button"
                class="fi-btn fi-btn-size-sm fi-color-gray rounded-lg px-2 py-1 text-xs"
                @click="resetTablet()"
                x-show="!sync"
                x-cloak
            >Сбросить tablet</button>
            <button
                type="button"
                class="fi-btn fi-btn-size-sm fi-color-gray rounded-lg px-2 py-1 text-xs"
                @click="resetDesktop()"
                x-show="!sync"
            >Сбросить desktop</button>
        </div>
        <div class="flex flex-wrap gap-2" x-show="!sync" x-cloak>
            <button type="button" class="fi-btn fi-btn-size-sm fi-color-gray rounded-lg px-2 py-1 text-xs" @click="copyToDesktop()">Mobile → desktop</button>
            <button type="button" class="fi-btn fi-btn-size-sm fi-color-gray rounded-lg px-2 py-1 text-xs" @click="copyToMobile()">Desktop → mobile + tablet</button>
            <button type="button" class="fi-btn fi-btn-size-sm fi-color-gray rounded-lg px-2 py-1 text-xs" @click="copyMobileToTablet()">Mobile → tablet</button>
            <button type="button" class="fi-btn fi-btn-size-sm fi-color-gray rounded-lg px-2 py-1 text-xs" @click="copyTabletToMobile()">Tablet → mobile</button>
            <button type="button" class="fi-btn fi-btn-size-sm fi-color-gray rounded-lg px-2 py-1 text-xs" @click="copyTabletToDesktop()">Tablet → desktop</button>
            <button type="button" class="fi-btn fi-btn-size-sm fi-color-gray rounded-lg px-2 py-1 text-xs" @click="copyDesktopToTablet()">Desktop → tablet</button>
        </div>
        <label class="flex cursor-pointer items-start gap-2">
            <input
                type="checkbox"
                class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-white/20 dark:bg-white/5"
                x-model="previewShowFullImage"
            />
            <span class="text-[11px]">
                <span x-show="config.slotId === 'page_hero_cover'" x-cloak>Весь кадр (contain) — проверка композиции; desktop hero использует fit по высоте.</span>
                <span x-show="config.slotId !== 'page_hero_cover'" x-cloak>Весь кадр (contain) — проверка композиции; на сайте остаётся cover.</span>
            </span>
        </label>
        <p class="text-[10px] text-amber-800/90 dark:text-amber-200/70" x-show="previewShowFullImage" x-cloak>
            В режиме contain не двигаем кадр; зона текста/градиент скрыты.
        </p>
    </div>
</details>
