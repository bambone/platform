@php
    $usedPercent = min(100, max(0, (float) ($usedPercent ?? 0.0)));
    $tier = $tier ?? 'normal';
    $variant = $variant ?? 'page';
    [$fillStart, $fillEnd] = match ($tier) {
        'exceeded' => ['#f87171', '#b91c1c'],
        'danger' => ['#fca5a5', '#dc2626'],
        'warning' => ['#fde68a', '#d97706'],
        default => ['#fde68a', '#d97706'],
    };
    $vbH = $variant === 'widget' ? 2 : 3;
    $rx = $variant === 'widget' ? 1 : 1.5;
    $dur = app()->runningUnitTests() ? '0.01s' : '0.88s';
    $gid = 'sqm-'.str_replace('-', '', (string) \Illuminate\Support\Str::uuid());
@endphp
{{-- Явные hex + градиент: fill-current в SVG часто даёт чёрный. SMIL-animate — плавное заполнение без style="{{ }}" в Blade. --}}
<svg
    class="fi-storage-quota-meter-svg block h-full w-full drop-shadow-[0_1px_2px_rgba(0,0,0,0.25)]"
    viewBox="0 0 100 {{ $vbH }}"
    preserveAspectRatio="none"
    aria-hidden="true"
>
    <defs>
        <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stop-color="{{ $fillStart }}" />
            <stop offset="100%" stop-color="{{ $fillEnd }}" />
        </linearGradient>
    </defs>
    <rect x="0" y="0" width="0" height="{{ $vbH }}" rx="{{ $rx }}" fill="url(#{{ $gid }})">
        <animate
            attributeName="width"
            from="0"
            to="{{ $usedPercent }}"
            dur="{{ $dur }}"
            fill="freeze"
            calcMode="spline"
            keySplines="0.16 1 0.3 1"
            keyTimes="0;1"
        />
    </rect>
</svg>
