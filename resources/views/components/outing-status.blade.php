@props(['status'])
@php
    $classes = match ($status) {
        \App\Enums\OutingStatus::Planifiee => 'bg-slate-100 text-slate-700',
        \App\Enums\OutingStatus::EnCours => 'bg-emerald-100 text-emerald-800',
        \App\Enums\OutingStatus::Terminee => 'bg-navy-50 text-navy-700',
        \App\Enums\OutingStatus::Annulee => 'bg-red-100 text-red-700',
    };
@endphp
<span {{ $attributes->merge(['class' => "chip $classes"]) }}>{{ $status->label() }}</span>
