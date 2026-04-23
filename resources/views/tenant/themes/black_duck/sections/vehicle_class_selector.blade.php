@php
    $d = is_array($data ?? null) ? $data : [];
    $opts = is_array($d['options'] ?? null) ? $d['options'] : [];
    $heading = (string) ($d['heading'] ?? 'Класс автомобиля');
@endphp
<fieldset class="bd-section" aria-describedby="bd-vclass-h">
    <legend id="bd-vclass-h" class="text-lg font-medium text-zinc-100">{{ $heading }}</legend>
    @if (count($opts) > 0)
        <div class="mt-3 flex flex-wrap gap-2" role="radiogroup" aria-label="{{ e($heading) }}">
            @foreach ($opts as $o)
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#36C7FF]">
                    <input type="radio" name="bd_vehicle_class" value="{{ (string) ($o['key'] ?? '') }}" class="text-[#F0FF00]" />
                    <span class="text-zinc-200">{{ (string) ($o['label'] ?? '') }}</span>
                </label>
            @endforeach
        </div>
    @endif
</fieldset>
