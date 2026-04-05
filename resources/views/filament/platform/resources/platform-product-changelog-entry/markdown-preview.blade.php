@php
    $summaryHtml = $summaryHtml ?? '';
    $bodyHtml = $bodyHtml ?? '';
    $summaryText = trim(strip_tags($summaryHtml));
    $bodyText = trim(strip_tags($bodyHtml));
    $hasSummary = $summaryText !== '';
    $hasBody = $bodyText !== '';
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 sm:p-6">
    @if (! $hasSummary && ! $hasBody)
        <p class="text-sm text-gray-600 dark:text-gray-400">Нет текста для предпросмотра — заполните поля «Кратко» или «Полный текст».</p>
    @else
        <div class="space-y-6">
            @if ($hasSummary)
                <div class="changelog-md-preview prose prose-sm dark:prose-invert max-w-none text-gray-800 dark:text-gray-200 [&_a]:text-primary-600 dark:[&_a]:text-primary-400">
                    {!! $summaryHtml !!}
                </div>
            @endif
            @if ($hasBody)
                <div class="changelog-md-preview prose prose-sm dark:prose-invert max-w-none text-gray-800 dark:text-gray-200 [&_a]:text-primary-600 dark:[&_a]:text-primary-400">
                    {!! $bodyHtml !!}
                </div>
            @endif
        </div>
    @endif
</div>
