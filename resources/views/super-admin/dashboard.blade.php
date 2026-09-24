<x-layouts.app title="Super admin" crumb="Plateforme YoleTeam" :back="route('more')">
    <x-slot:actions>
        <a href="{{ route('super-admin.users.create') }}" class="btn-ghost btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouveau compte</a>
        <a href="{{ route('super-admin.associations.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle association</a>
    </x-slot:actions>

    @include('super-admin._tabs')

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-5">
        <x-kpi label="Associations" :value="$associationCount" icon="boat" />
        <x-kpi label="Utilisateurs actifs" :value="$activeUsers" :sub="$disabledUsers.' désactivé(s)'" icon="user" tone="green" />
        <x-kpi label="Membres" :value="$memberCount" sub="toutes associations" icon="users" tone="sky" />
        <x-kpi label="Sorties (30 j)" :value="$recentOutings" sub="30 derniers jours" icon="calendar" tone="sun" />
    </div>

    <div class="grid gap-5 xl:grid-cols-3 mt-5">
        <section class="card overflow-hidden xl:col-span-2 self-start">
            <div class="px-5 pt-5">
                <x-section-title title="Associations">
                    <a href="{{ route('super-admin.associations.index') }}" class="text-sm font-semibold text-navy-700">Gérer</a>
                </x-section-title>
            </div>
            @if ($associations->isEmpty())
                <p class="text-sm muted px-5 pb-5">Aucune association pour l’instant.</p>
            @else
                <div class="hidden lg:block">
                    <table class="w-full">
                        <thead class="bg-slate-50">
                            <tr><th class="th">Association</th><th class="th text-right">Comptes</th><th class="th text-right">Membres</th><th class="th text-right">Yoles</th><th class="th text-right">Sorties</th><th class="th">Dernière activité</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($associations as $association)
                                <tr class="hover:bg-slate-50">
                                    <td class="td">
                                        <a href="{{ route('super-admin.associations.edit', $association) }}" class="flex items-center gap-3">
                                            <span class="w-9 h-9 rounded-xl text-sun-400 grid place-items-center shrink-0" style="background: {{ $association->primary_color }}"><x-icon name="boat" class="w-[18px] h-[18px]" /></span>
                                            <div class="min-w-0">
                                                <p class="font-bold truncate">{{ $association->name }}@if ($association->id === auth()->user()->association_id) <span class="chip bg-sun-100 text-amber-800 ml-1 py-0">Actuelle</span>@endif</p>
                                                <p class="text-xs muted">{{ $association->city ?: 'Commune non renseignée' }}</p>
                                            </div>
                                        </a>
                                    </td>
                                    <td class="td text-right font-semibold">{{ $association->users_count }}</td>
                                    <td class="td text-right font-semibold">{{ $association->members_count }}</td>
                                    <td class="td text-right font-semibold">{{ $association->boats_count }}</td>
                                    <td class="td text-right font-semibold">{{ $association->outings_count }}</td>
                                    <td class="td text-sm muted whitespace-nowrap">{{ $lastActivity[$association->id]?->diffForHumans() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="lg:hidden divide-y divide-slate-100">
                    @foreach ($associations as $association)
                        <a href="{{ route('super-admin.associations.edit', $association) }}" class="px-5 py-3 flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl text-sun-400 grid place-items-center shrink-0" style="background: {{ $association->primary_color }}"><x-icon name="boat" class="w-5 h-5" /></span>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold truncate">{{ $association->name }}</p>
                                <p class="text-xs muted">{{ $association->users_count }} comptes · {{ $association->members_count }} membres · {{ $association->outings_count }} sorties</p>
                                <p class="text-[11px] muted">Activité : {{ $lastActivity[$association->id]?->diffForHumans() ?? '—' }}</p>
                            </div>
                            <x-icon name="right" class="w-4 h-4 text-slate-400" />
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="card p-5 self-start">
            <x-section-title title="Dernières connexions">
                <a href="{{ route('super-admin.users.index') }}" class="text-sm font-semibold text-navy-700">Comptes</a>
            </x-section-title>
            <div class="divide-y divide-slate-100">
                @forelse ($latestLogins as $login)
                    <a href="{{ route('super-admin.users.edit', $login) }}" class="flex items-center gap-3 py-2.5">
                        <span class="w-9 h-9 rounded-full bg-navy-100 text-navy-800 grid place-items-center font-bold text-xs shrink-0">{{ mb_strtoupper(collect(preg_split('/\s+/', trim($login->name)))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join('')) }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate">{{ $login->name }}</p>
                            <p class="text-xs muted truncate">{{ $login->association?->name ?? 'Sans association' }}</p>
                        </div>
                        <span class="text-xs muted whitespace-nowrap" title="{{ $login->last_login_at->translatedFormat('j M Y H:i') }}">{{ $login->last_login_at->diffForHumans() }}</span>
                    </a>
                @empty
                    <p class="text-sm muted">Aucune connexion enregistrée.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
