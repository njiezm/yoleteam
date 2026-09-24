{{--
    Stage fields. Expects $stage (?RaceStage), $formKey (old-input marker), $bag (MessageBag), $id (id prefix), $defaultNumber (int).
--}}
@php
    $old = fn (string $key, mixed $default = null) => old('_form') === $formKey ? old($key, $default) : $default;
    $fields = [
        ['number', 'N° *', 'number', $stage?->number ?? $defaultNumber, 'min=1 max=99 required', ''],
        ['name', 'Nom *', 'text', $stage?->name, 'required maxlength=255', 'Étape 1 — Fort-de-France / Schœlcher'],
        ['date', 'Date *', 'date', $stage?->date?->toDateString(), 'required', ''],
        ['start_location', 'Départ', 'text', $stage?->start_location, 'maxlength=255', 'Fort-de-France'],
        ['end_location', 'Arrivée', 'text', $stage?->end_location, 'maxlength=255', 'Schœlcher'],
        ['distance_nm', 'Distance (milles)', 'number', $stage?->distance_nm, 'min=0 max=999 step=0.1', '8,5'],
    ];
@endphp
<input type="hidden" name="_form" value="{{ $formKey }}">
<div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
    @foreach ($fields as [$name, $label, $type, $value, $extra, $placeholder])
        <div @class(['col-span-2 sm:col-span-4' => $name === 'name', 'col-span-1 sm:col-span-2' => $name !== 'name' && $name !== 'number', 'col-span-2 sm:col-span-2' => $name === 'number'])>
            <label class="label" for="{{ $id }}-{{ $name }}">{{ $label }}</label>
            <input id="{{ $id }}-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $old($name, $value) }}" placeholder="{{ $placeholder }}" {!! $extra !!} @class(['input', 'input-error' => $bag->has($name)])>
            @include('boats._error', ['bag' => $bag, 'key' => $name])
        </div>
    @endforeach
</div>
