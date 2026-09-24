@props(['role', 'preferred' => false])
<span class="chip" style="background: {{ $role->color }}1f; color: color-mix(in srgb, {{ $role->color }} 62%, black)">
    @if ($preferred)
        <x-icon name="star" class="w-3 h-3 fill-current" />
    @else
        <i class="w-1.5 h-1.5 rounded-full" style="background: {{ $role->color }}"></i>
    @endif
    {{ $role->label }}
</span>
