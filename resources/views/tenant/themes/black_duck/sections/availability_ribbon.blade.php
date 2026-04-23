@php
    $d = is_array($data ?? null) ? $data : [];
    $text = trim((string) ($d['text'] ?? ''));
    $aria = (string) ($d['aria_label'] ?? 'Информация о записи');
@endphp
@if ($text !== '')
    <div
        class="my-4 rounded-lg border border-[#36C7FF]/30 bg-[#36C7FF]/10 px-4 py-3 text-sm text-zinc-200"
        role="status"
        aria-label="{{ e($aria) }}"
    >{{ $text }}</div>
@endif
