@props(['plan'])
@if ($plan->isValidated())
    <span {{ $attributes->merge(['class' => 'chip bg-emerald-100 text-emerald-800']) }}>Validé</span>
@else
    <span {{ $attributes->merge(['class' => 'chip bg-amber-100 text-amber-800']) }}>Brouillon</span>
@endif
