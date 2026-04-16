@php
    /** @var \Filament\Forms\Components\ViewField $field */
    $tiles = $tiles ?? [];
    $safeArea = $safeArea ?? ['bottomPercent' => 38, 'label' => ''];
    $bottomPct = (float) ($safeArea['bottomPercent'] ?? 38);
@endphp

<x-dynamic-component :component="$field->getFieldWrapperView()" :field="$field">
    <div class="space-y-3 rounded-lg border border-gray-200 bg-gray-50/80 p-3 dark:border-white/10 dark:bg-white/5">
        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Превью кадрирования (пропорции слота как на сайте)</p>
        <div class="flex flex-wrap gap-4">
            @foreach ($tiles as $tile)
                @php
                    $w = (int) ($tile['width'] ?? 200);
                    $h = (int) ($tile['height'] ?? 120);
                    $fx = (float) ($tile['fx'] ?? 50);
                    $fy = (float) ($tile['fy'] ?? 50);
                    $src = $tile['src'] ?? null;
                    $label = (string) ($tile['label'] ?? '');
                    $srcOk = filled($src);
                @endphp
                <div class="min-w-0">
                    <p class="mb-1 text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <div
                        class="relative overflow-hidden rounded-md border border-gray-200 bg-gray-900/5 dark:border-white/10"
                        style="width: {{ $w }}px; max-width: 100%; aspect-ratio: {{ $w }} / {{ $h }};"
                    >
                        @if ($srcOk)
                            <img
                                src="{{ e($src) }}"
                                alt=""
                                class="h-full w-full object-cover"
                                style="object-position: {{ $fx }}% {{ $fy }}%;"
                                loading="lazy"
                            />
                            <div
                                class="pointer-events-none absolute inset-x-0 bottom-0 border-t border-dashed border-amber-500/60 bg-amber-500/10"
                                style="height: {{ $bottomPct }}%;"
                                title="{{ e($safeArea['label'] ?? 'Safe area') }}"
                            ></div>
                        @else
                            <div class="flex h-full min-h-[4rem] items-center justify-center p-2 text-center text-[10px] text-gray-400">Нет изображения для превью</div>
                        @endif
                    </div>
                    <p class="mt-0.5 text-[10px] text-gray-500">Фокус {{ number_format($fx, 1) }}% × {{ number_format($fy, 1) }}%</p>
                </div>
            @endforeach
        </div>
    </div>
</x-dynamic-component>
