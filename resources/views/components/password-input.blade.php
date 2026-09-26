{{--
    Password input with a show / hide eye button (behaviour: resources/js/password-toggle.js).
    Extra attributes (autocomplete, required, minlength…) go to the <input>; pass :invalid for a named error bag.
--}}
@props(['name', 'id' => null, 'invalid' => null])
@php
    $id ??= $name;
    $invalid ??= $errors->has($name);
@endphp
<div class="relative" data-password-field>
    <input id="{{ $id }}" name="{{ $name }}" type="password" {{ $attributes->class(['input pr-12', 'input-error' => $invalid]) }}>
    <button type="button" class="absolute right-1.5 top-1/2 -translate-y-1/2 w-9 h-9 grid place-items-center rounded-lg text-slate-400 hover:text-navy-900 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-sun-400 cursor-pointer"
            data-password-toggle aria-controls="{{ $id }}" aria-pressed="false"
            aria-label="Afficher le mot de passe" title="Afficher le mot de passe"
            data-label-show="Afficher le mot de passe" data-label-hide="Masquer le mot de passe">
        <x-icon name="eye" class="w-[18px] h-[18px]" data-password-icon="show" />
        <x-icon name="eye-off" class="w-[18px] h-[18px] hidden" data-password-icon="hide" />
    </button>
</div>
