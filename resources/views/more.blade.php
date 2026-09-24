@php
    $user = auth()->user();
    $initials = collect(explode(' ', $user->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join('');
    $links = [
        ['members.index', 'users', 'Membres'],
        ['boats.index', 'boat', 'Yoles'],
        ['races.index', 'trophy', 'Régates'],
        ['history.index', 'chart', 'Historique & stats'],
        ['sync.index', 'refresh', 'Synchronisation'],
    ];
@endphp
<x-layouts.app title="Plus">
    <div class="max-w-xl">
        <div class="card p-4 flex items-center gap-3">
            <span class="w-12 h-12 rounded-full bg-sun-400 text-navy-950 grid place-items-center font-bold">{{ $initials }}</span>
            <div class="flex-1 min-w-0">
                <p class="font-bold truncate">{{ $user->name }}</p>
                <p class="text-xs muted">{{ $user->role->label() }} · {{ $user->association->name }}</p>
            </div>
        </div>

        <div class="card mt-4 divide-y divide-slate-100">
            @foreach ($links as [$route, $icon, $label])
                <a href="{{ route($route) }}" class="flex items-center gap-3 px-4 py-3.5">
                    <span class="w-9 h-9 rounded-xl bg-navy-50 text-navy-700 grid place-items-center"><x-icon :name="$icon" class="w-[18px] h-[18px]" /></span>
                    <span class="flex-1 font-semibold">{{ $label }}</span>
                    <x-icon name="right" class="w-4 h-4 text-slate-400" />
                </a>
            @endforeach
            @can('manage')
                <a href="{{ route('settings.edit') }}" class="flex items-center gap-3 px-4 py-3.5">
                    <span class="w-9 h-9 rounded-xl bg-navy-50 text-navy-700 grid place-items-center"><x-icon name="settings" class="w-[18px] h-[18px]" /></span>
                    <span class="flex-1 font-semibold">Paramètres</span>
                    <x-icon name="right" class="w-4 h-4 text-slate-400" />
                </a>
            @endcan
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-4" data-logout>
            @csrf
            <button class="btn-ghost w-full text-red-600"><x-icon name="logout" class="w-4 h-4" />Se déconnecter</button>
        </form>
    </div>
</x-layouts.app>
