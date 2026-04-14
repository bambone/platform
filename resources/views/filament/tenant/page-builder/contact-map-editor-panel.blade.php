@php
    /** @var array{status: string, message: string, resolved: ?\App\PageBuilder\Contacts\ContactMapResolvedView} $preview */
    $tier = $preview['status'];
    $border = match ($tier) {
        'error' => 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/40',
        'warning' => 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40',
        'success' => 'border-emerald-300 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/30',
        default => 'border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5',
    };
@endphp

<div class="space-y-4">
    <div class="rounded-lg border p-4 {{ $border }}">
        <p class="text-sm text-gray-900 dark:text-gray-100">{{ $preview['message'] }}</p>
    </div>

    @include('filament.tenant.page-builder.contact-map-block-preview', ['preview' => $preview])
</div>
