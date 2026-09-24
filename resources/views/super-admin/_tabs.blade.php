@php
    $tabs = [
        ['super-admin.dashboard', 'Vue d’ensemble', 'super-admin.dashboard'],
        ['super-admin.associations.index', 'Associations', 'super-admin.associations.*'],
        ['super-admin.users.index', 'Utilisateurs', 'super-admin.users.*'],
    ];
@endphp
<div class="flex gap-1.5 overflow-x-auto scrollbar-none mb-5">
    @foreach ($tabs as [$route, $label, $pattern])
        @php($on = request()->routeIs($pattern))
        <a href="{{ route($route) }}" @class(['chip h-9 px-4 whitespace-nowrap', 'bg-navy-900 text-white' => $on, 'bg-white border border-slate-200 text-slate-600' => ! $on])>{{ $label }}</a>
    @endforeach
</div>

@if ($errors->hasAny(['association', 'reset']) && ! request()->routeIs('super-admin.*.create', 'super-admin.*.edit'))
    <div class="rounded-xl bg-red-50 text-red-700 text-sm font-semibold p-3 mb-5 flex items-center gap-2"><x-icon name="alert" class="w-4 h-4" />{{ $errors->first('association') ?: $errors->first('reset') }}</div>
@endif
