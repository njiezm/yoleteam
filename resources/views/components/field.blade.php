@props(['label', 'name', 'hint' => null])
<div {{ $attributes }}>
    <label class="label" for="{{ $name }}">{{ $label }}</label>
    {{ $slot }}
    @if ($hint)
        <p class="text-xs muted mt-1.5">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="text-xs text-red-600 font-semibold mt-1.5 flex items-center gap-1"><x-icon name="alert" class="w-3.5 h-3.5" />{{ $message }}</p>
    @enderror
</div>
