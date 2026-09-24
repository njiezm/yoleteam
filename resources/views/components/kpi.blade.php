@props(['label', 'value', 'sub' => null, 'icon', 'tone' => 'navy'])
@php
    $tones = ['navy' => 'bg-navy-50 text-navy-700', 'sun' => 'bg-sun-100 text-amber-700', 'green' => 'bg-emerald-50 text-emerald-700', 'sky' => 'bg-sky-50 text-sky-700'];
@endphp
<div class="card p-4 lg:p-5">
    <div class="flex items-start justify-between gap-2">
        <p class="text-[13px] font-semibold muted">{{ $label }}</p>
        <span class="w-9 h-9 shrink-0 rounded-xl grid place-items-center {{ $tones[$tone] }}"><x-icon :name="$icon" class="w-[18px] h-[18px]" /></span>
    </div>
    <p class="mt-2 text-2xl lg:text-[28px] font-extrabold tracking-tight">{{ $value }}</p>
    @if ($sub)
        <p class="text-xs muted mt-0.5">{{ $sub }}</p>
    @endif
</div>
