@props(['name', 'value' => null, 'type' => 'text'])
<input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" {{ $attributes->class(['input', 'input-error' => $errors->has($name)]) }}>
