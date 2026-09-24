@if ($bag->has($key))
    <p class="text-xs text-red-600 font-semibold mt-1.5 flex items-center gap-1"><x-icon name="alert" class="w-3.5 h-3.5" />{{ $bag->first($key) }}</p>
@endif
