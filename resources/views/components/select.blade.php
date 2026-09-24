@props(['name', 'options' => [], 'value' => null, 'placeholder' => null])
@php
    $current = (string) old($name, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['input', 'input-error' => $errors->has($name)]) }}>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
    @endforeach
</select>
