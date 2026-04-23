@php
    use App\Tenant\Expert\ExpertBrandMediaUrl;
    $src = ExpertBrandMediaUrl::resolve($logicalPath ?? '');
    $srcsetStr = trim((string) ($srcset ?? ''));
    $sizesStr = trim((string) ($sizes ?? ''));
    $altText = trim((string) ($alt ?? ''));
    $aspect = trim((string) ($aspectRatio ?? ''));
    $loadingAttr = in_array(($loading ?? 'lazy'), ['eager', 'lazy'], true) ? $loading : 'lazy';
    $fetchPr = ($fetchpriority ?? null) === 'high' ? 'high' : null;
    $wrap = trim('overflow-hidden rounded-xl border border-white/10 bg-white/[0.03] '.trim((string) ($wrapperClass ?? '')));
    $aspectStyleAttr = $aspect !== '' ? ' style="aspect-ratio: '.e($aspect).';"' : '';
@endphp
@if ($src !== '')
    <div class="{{ $wrap }}"{!! $aspectStyleAttr !!}>
        @if ($srcsetStr !== '' && $sizesStr !== '')
            <img
                src="{{ e($src) }}"
                srcset="{{ e($srcsetStr) }}"
                sizes="{{ e($sizesStr) }}"
                alt="{{ e($altText) }}"
                class="{{ trim('h-full w-full object-cover '.$class) }}"
                loading="{{ $loadingAttr }}"
                decoding="async"
                @if ($fetchPr === 'high') fetchpriority="high" @endif
            />
        @else
            <img
                src="{{ e($src) }}"
                alt="{{ e($altText) }}"
                class="{{ trim('h-full w-full object-cover '.$class) }}"
                loading="{{ $loadingAttr }}"
                decoding="async"
                @if ($fetchPr === 'high') fetchpriority="high" @endif
            />
        @endif
    </div>
@endif
