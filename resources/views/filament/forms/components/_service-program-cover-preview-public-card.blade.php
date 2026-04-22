@php
    /** @var \Filament\Forms\Components\ViewField $field */
    $editorConfig = $editorConfig ?? [];
    $previewKey = $previewKey ?? '';
    $fieldId = $field->getId();
    /** @var \App\Models\TenantServiceProgram|null $previewProgram */
    $previewProgram = $previewProgram ?? null;
    $tenant = $tenant ?? currentTenant();
    $articleStyle = (string) ($articleStyle ?? '');
    $hasPaneMedia = (bool) ($hasPaneMedia ?? false);
    $tabletFocalSummary = (string) ($tabletFocalSummary ?? '');
    $tiles = $tiles ?? [];
    $overlayMobile = $overlayMobile ?? ['svc-program-mask-fade-start' => '78%', 'svc-program-mask-fade-mid' => '90%'];
    $overlayDesktop = $overlayDesktop ?? ['svc-program-mask-fade-start' => '80%', 'svc-program-mask-fade-mid' => '91%'];
    $viewComponentKey = (string) ($viewComponentKey ?? $previewKey ?? '');
@endphp

<div
    wire:key="svc-cover-wysiwyg-{{ $viewComponentKey }}"
    data-svc-focal-preview
    class="space-y-3 rounded-lg border border-gray-200 bg-gray-50/80 p-3 dark:border-white/10 dark:bg-white/5"
    x-data="serviceProgramCoverFocalEditor(@js($editorConfig))"
>
    <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
        <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">Обложка программы</p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
        <div class="min-w-0 space-y-3">
            <div>
                <p class="text-xs font-medium text-gray-800 dark:text-gray-200">Кадр снимка</p>
                <p class="mt-0.5 text-[11px] leading-snug text-gray-500 dark:text-gray-400">
                    Перетаскивайте снимок, меняйте zoom и height. Справа переключается тот же вид (mobile / tablet / desktop), что и вкладки выше.
                </p>
            </div>

            <div
                class="inline-flex w-full max-w-md flex-wrap rounded-lg border border-gray-200/90 bg-white/60 p-0.5 dark:border-white/10 dark:bg-white/5"
                role="tablist"
                aria-label="Активный кадр для настроек"
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
                'focalFrameOuterClass' => 'w-full min-w-0',
            ])

            <details class="rounded-md border border-dashed border-amber-500/30 bg-amber-500/5 p-2 text-[11px] text-gray-600 dark:text-gray-400">
                <summary class="cursor-pointer font-medium text-amber-900 dark:text-amber-100/90">Планшет (tablet) — только данные</summary>
                <p class="mt-2 text-[10px] leading-relaxed">На сайте в диапазоне 768–1023px по ширине для фокуса и кадра используется ветка mobile; отдельные поля tablet в JSON остаются для тонкой настройки и резерва.</p>
                @if ($tabletFocalSummary !== '')
                    <p class="mt-1 font-mono text-[10px] text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $tabletFocalSummary }}</p>
                @endif
            </details>

            @include('filament.forms.components._service-program-cover-preview-focal-editor-toolbar', [
                'fieldId' => $fieldId,
            ])
        </div>

        <div
            class="min-w-0 space-y-3 rounded-lg border border-white/[0.08] bg-[#0a0d14]/40 p-3"
            inert
            aria-label="Превью карточки (только просмотр)"
        >
            <div>
                <p class="text-xs font-medium text-white/90">Превью на сайте</p>
                <p class="mt-0.5 text-[11px] text-white/50">Совпадает с активной вкладкой слева (Mobile / Tablet / Desktop). Редактирование — только слева. Вёрстка — тот же компонент, брейкпоинты по ширине карточки в превью (container query), а не по ширине окна.</p>
            </div>

            @if ($previewProgram && $tenant)
                <div class="space-y-4">
                    <div x-show="activeViewport === 'mobile'" x-cloak class="space-y-2">
                        <p class="mb-1 text-[10px] font-medium uppercase tracking-wide text-white/45">Mobile (~390px по карточке)</p>
                        <div class="rb-expert-card-wysiwyg overflow-hidden rounded-xl border border-white/[0.08] bg-[#0a0d14] p-2 shadow-inner">
                            <div class="mx-auto w-full max-w-[390px] min-w-0">
                                <x-tenant.expert_auto.expert-program-card
                                    :program="$previewProgram"
                                    :tenant="$tenant"
                                    :article-style="$articleStyle"
                                    forced-picture-mode="mobile"
                                    focal-style-from-alpine
                                    focal-preview-mode="mobile"
                                    :program-index="0"
                                    :span-featured-in-grid="(bool) $previewProgram->is_featured"
                                    :is-preview="true"
                                />
                            </div>
                        </div>
                    </div>
                    <div x-show="activeViewport === 'tablet'" x-cloak class="space-y-2">
                        <p class="mb-1 text-[10px] font-medium uppercase tracking-wide text-white/45">Tablet (~768px; на сайте кадр как у mobile)</p>
                        <div class="rb-expert-card-wysiwyg overflow-hidden rounded-xl border border-white/[0.08] bg-[#0a0d14] p-2 shadow-inner">
                            <div class="mx-auto w-full max-w-[48rem] min-w-0">
                                <x-tenant.expert_auto.expert-program-card
                                    :program="$previewProgram"
                                    :tenant="$tenant"
                                    :article-style="$articleStyle"
                                    forced-picture-mode="mobile"
                                    focal-style-from-alpine
                                    focal-preview-mode="tablet"
                                    :program-index="0"
                                    :span-featured-in-grid="(bool) $previewProgram->is_featured"
                                    :is-preview="true"
                                />
                            </div>
                        </div>
                    </div>
                    <div x-show="activeViewport === 'desktop'" x-cloak class="space-y-2">
                        <p class="mb-1 text-[10px] font-medium uppercase tracking-wide text-white/45">Desktop (~1100px по карточке, ≥64rem — широкий кадр)</p>
                        <div class="rb-expert-card-wysiwyg overflow-hidden rounded-xl border border-white/[0.08] bg-[#0a0d14] p-2 shadow-inner">
                            <div class="mx-auto w-full max-w-[1100px] min-w-0">
                                <x-tenant.expert_auto.expert-program-card
                                    :program="$previewProgram"
                                    :tenant="$tenant"
                                    :article-style="$articleStyle"
                                    forced-picture-mode="desktop"
                                    focal-style-from-alpine
                                    focal-preview-mode="desktop"
                                    :program-index="0"
                                    :span-featured-in-grid="(bool) $previewProgram->is_featured"
                                    :is-preview="true"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-xs text-amber-200/90">Нет данных превью (тенант или программа).</p>
            @endif

            @if (! $hasPaneMedia)
                <p class="text-xs text-amber-200/90">Загрузите баннер для компьютера, чтобы увидеть превью.</p>
            @endif
        </div>
    </div>
</div>
