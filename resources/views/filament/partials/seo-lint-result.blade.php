@php
    /** @var \App\Services\Seo\Data\TenantSeoLintResult $result */
@endphp
<div class="space-y-4 text-sm">
    <p><strong>Оценка:</strong> {{ $result->score }}/100</p>
    <p class="text-gray-600 dark:text-gray-400">Проверено URL: {{ count($result->checkedPages) }} (режим internal).</p>
    @if($result->errors !== [])
        <div>
            <p class="font-medium text-danger-600 dark:text-danger-400">Ошибки</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach($result->errors as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if($result->warnings !== [])
        <div>
            <p class="font-medium text-warning-600 dark:text-warning-400">Предупреждения</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach($result->warnings as $w)
                    <li>{{ $w }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if($result->notices !== [])
        <div>
            <p class="font-medium text-gray-700 dark:text-gray-300">Замечания</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach($result->notices as $n)
                    <li>{{ $n }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
