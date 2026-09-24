@props(['value' => 0, 'color' => '#10B981'])
<div {{ $attributes->merge(['class' => 'h-2 rounded-full bg-slate-100 overflow-hidden']) }}><div class="h-full rounded-full" style="width: {{ max(0, min(100, $value)) }}%; background: {{ $color }}"></div></div>
