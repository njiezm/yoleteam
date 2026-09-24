@props(['level'])
@php
    $classes = match ($level) {
        \App\Enums\MemberLevel::Debutant => 'bg-slate-100 text-slate-700',
        \App\Enums\MemberLevel::Intermediaire => 'bg-sky-100 text-sky-800',
        \App\Enums\MemberLevel::Confirme => 'bg-navy-100 text-navy-800',
        \App\Enums\MemberLevel::Expert => 'bg-sun-100 text-amber-800',
    };
@endphp
<span class="chip {{ $classes }}">{{ $level->label() }}</span>
