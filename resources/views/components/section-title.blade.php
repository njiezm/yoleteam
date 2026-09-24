@props(['title'])
<div {{ $attributes->merge(['class' => 'flex items-center justify-between mb-3 gap-3']) }}>
    <h3 class="font-bold text-navy-950">{{ $title }}</h3>
    {{ $slot }}
</div>
