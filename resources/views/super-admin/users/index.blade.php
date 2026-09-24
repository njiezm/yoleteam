@php
    $initials = fn (string $name) => mb_strtoupper(collect(preg_split('/\s+/', trim($name)))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join(''));
    $roleChip = fn ($user) => match (true) {
        $user->isSuperAdmin() => 'bg-sun-400 text-navy-950',
        $user->isAdmin() => 'bg-navy-900 text-white',
        default => 'bg-sun-100 text-amber-800',
    };
@endphp
<x-layouts.app title="Utilisateurs" :crumb="'Super admin · '.$users->total().' compte(s)'" :back="route('super-admin.dashboard')">
    <x-slot:actions>
        <a href="{{ route('super-admin.users.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouveau compte</a>
    </x-slot:actions>
    <x-slot:sticky>
        <a href="{{ route('super-admin.users.create') }}" class="btn-primary w-full h-12"><x-icon name="plus" class="w-4 h-4" />Nouveau compte</a>
    </x-slot:sticky>

    @include('super-admin._tabs')

    <form method="GET" action="{{ route('super-admin.users.index') }}" class="flex flex-col lg:flex-row gap-3">
        <label class="relative flex-1">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><x-icon name="search" class="w-4 h-4" /></span>
            <input name="q" value="{{ $filters['q'] }}" class="input pl-9" placeholder="Nom ou adresse e-mail…" aria-label="Rechercher">
        </label>
        <div class="flex gap-2 overflow-x-auto scrollbar-none">
            <select name="association" class="input w-56" aria-label="Association" onchange="this.form.submit()">
                <option value="">Toutes les associations</option>
                @foreach ($associations as $id => $name)
                    <option value="{{ $id }}" @selected($filters['association'] === $id)>{{ $name }}</option>
                @endforeach
            </select>
            <select name="role" class="input w-40" aria-label="Rôle" onchange="this.form.submit()">
                <option value="">Tous rôles</option>
                @foreach (\App\Enums\UserRole::options() as $value => $label)
                    <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="input w-36" aria-label="Statut" onchange="this.form.submit()">
                @foreach (['' => 'Tous statuts', 'active' => 'Actifs', 'disabled' => 'Désactivés'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) $filters['status'] === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn-ghost shrink-0" title="Filtrer"><x-icon name="filter" class="w-4 h-4" /><span class="sr-only">Filtrer</span></button>
        </div>
    </form>

    @if ($users->isEmpty())
        <x-empty-state class="mt-4" icon="users" title="Aucun compte trouvé" text="Modifiez les filtres ou la recherche." />
    @else
        <div class="card mt-4 overflow-hidden hidden lg:block">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr><th class="th">Compte</th><th class="th">Association</th><th class="th">Rôle</th><th class="th">Statut</th><th class="th">Dernière connexion</th><th class="th"></th></tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        @php($isSelf = $user->is(auth()->user()))
                        <tr class="hover:bg-slate-50">
                            <td class="td">
                                <a href="{{ route('super-admin.users.edit', $user) }}" class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-full bg-navy-100 text-navy-800 grid place-items-center font-bold text-xs shrink-0">{{ $initials($user->name) }}</span>
                                    <div class="min-w-0">
                                        <p class="font-bold truncate">{{ $user->name }}@if ($isSelf) <span class="muted font-normal">(vous)</span>@endif</p>
                                        <p class="text-xs muted truncate">{{ $user->email }}</p>
                                    </div>
                                </a>
                            </td>
                            <td class="td text-sm">{{ $user->association?->name ?? '—' }}</td>
                            <td class="td"><span class="chip {{ $roleChip($user) }}">{{ $user->role->label() }}</span></td>
                            <td class="td">
                                @if ($user->isDisabled())
                                    <span class="chip bg-red-50 text-red-700">Désactivé</span>
                                @else
                                    <span class="chip bg-emerald-50 text-emerald-700">Actif</span>
                                @endif
                            </td>
                            <td class="td text-sm muted whitespace-nowrap">{{ $user->last_login_at?->diffForHumans() ?? 'Jamais' }}</td>
                            <td class="td">
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST" action="{{ route('super-admin.users.reset-link', $user) }}">
                                        @csrf
                                        <button class="w-9 h-9 grid place-items-center rounded-lg text-slate-400 hover:text-navy-900 hover:bg-slate-100 cursor-pointer" title="Envoyer un lien de réinitialisation" aria-label="Envoyer un lien de réinitialisation à {{ $user->name }}"><x-icon name="mail" class="w-4 h-4" /></button>
                                    </form>
                                    @unless ($isSelf)
                                        <form method="POST" action="{{ route('super-admin.users.toggle', $user) }}">
                                            @csrf
                                            <button class="w-9 h-9 grid place-items-center rounded-lg text-slate-400 hover:text-navy-900 hover:bg-slate-100 cursor-pointer" title="{{ $user->isDisabled() ? 'Réactiver' : 'Désactiver' }}" aria-label="{{ $user->isDisabled() ? 'Réactiver' : 'Désactiver' }} {{ $user->name }}"><x-icon name="lock" class="w-4 h-4" /></button>
                                        </form>
                                    @endunless
                                    <a href="{{ route('super-admin.users.edit', $user) }}" class="w-9 h-9 grid place-items-center rounded-lg text-slate-400 hover:text-navy-900 hover:bg-slate-100" title="Modifier" aria-label="Modifier {{ $user->name }}"><x-icon name="edit" class="w-4 h-4" /></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="lg:hidden mt-4 space-y-2">
            @foreach ($users as $user)
                <a href="{{ route('super-admin.users.edit', $user) }}" class="card p-3 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-navy-100 text-navy-800 grid place-items-center font-bold text-sm shrink-0">{{ $initials($user->name) }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold truncate">{{ $user->name }}</p>
                        <p class="text-xs muted truncate">{{ $user->email }} · {{ $user->association?->name ?? '—' }}</p>
                        <div class="flex items-center gap-1.5 mt-1">
                            <span class="chip {{ $roleChip($user) }}">{{ $user->role->label() }}</span>
                            @if ($user->isDisabled())
                                <span class="chip bg-red-50 text-red-700">Désactivé</span>
                            @endif
                        </div>
                    </div>
                    <x-icon name="right" class="w-4 h-4 text-slate-400" />
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $users->links() }}</div>
    @endif
</x-layouts.app>
