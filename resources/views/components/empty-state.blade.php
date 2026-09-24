@props(['icon' => 'calendar', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'card p-8 text-center']) }}>
    <div class="w-16 h-16 rounded-2xl bg-navy-50 text-navy-500 grid place-items-center mx-auto"><x-icon :name="$icon" class="w-8 h-8" /></div>
    <p class="font-extrabold mt-4">{{ $title }}</p>
    @if ($text)
        <p class="text-sm muted mt-1 max-w-md mx-auto">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4 flex justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
