<x-layouts.app title="Membres" :crumb="$activeCount.' membres actifs · saison '.today()->year">
    @can('manage')
        <x-slot:actions>
            <a href="{{ route('members.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Ajouter un membre</a>
        </x-slot:actions>
        <x-slot:sticky>
            <a href="{{ route('members.create') }}" class="btn-primary w-full h-12"><x-icon name="plus" class="w-4 h-4" />Ajouter un membre</a>
        </x-slot:sticky>
    @endcan

    <form method="GET" action="{{ route('members.index') }}" class="flex flex-col lg:flex-row gap-3">
        @if ($filters['role'])
            <input type="hidden" name="role" value="{{ $filters['role'] }}">
        @endif
        <label class="relative flex-1">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><x-icon name="search" class="w-4 h-4" /></span>
            <input name="q" value="{{ $filters['q'] }}" class="input pl-9" placeholder="Nom, surnom, téléphone…" aria-label="Rechercher">
        </label>
        <div class="flex gap-2 overflow-x-auto scrollbar-none">
            <select name="level" class="input w-44" aria-label="Niveau" onchange="this.form.submit()">
                <option value="">Tous niveaux</option>
                @foreach (\App\Enums\MemberLevel::options() as $value => $label)
                    <option value="{{ $value }}" @selected($filters['level'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="input w-32" aria-label="Statut" onchange="this.form.submit()">
                @foreach (['active' => 'Actifs', 'inactive' => 'Inactifs', 'all' => 'Tous'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn-ghost shrink-0" title="Filtrer"><x-icon name="filter" class="w-4 h-4" /><span class="sr-only">Filtrer</span></button>
        </div>
    </form>

    @php($tabQuery = array_filter(['q' => $filters['q'], 'level' => $filters['level'], 'status' => $filters['status'] !== 'active' ? $filters['status'] : null]))
    <div class="flex gap-1.5 overflow-x-auto scrollbar-none mt-3">
        <a href="{{ route('members.index', $tabQuery) }}" @class(['chip h-8 px-3 whitespace-nowrap', 'bg-navy-900 text-white' => ! $filters['role'], 'bg-white border border-slate-200 text-slate-600' => $filters['role']])>Tous · {{ $total }}</a>
        @foreach ($crewRoles as $crewRole)
            @php($on = $filters['role'] === $crewRole->code)
            <a href="{{ route('members.index', [...$tabQuery, 'role' => $crewRole->code]) }}" @class(['chip h-8 px-3 whitespace-nowrap', 'bg-navy-900 text-white' => $on, 'bg-white border border-slate-200 text-slate-600' => ! $on])>{{ $crewRole->label }} · {{ $roleCounts[$crewRole->code] }}</a>
        @endforeach
    </div>

    @if ($members->isEmpty())
        <x-empty-state class="mt-4" icon="users" title="Aucun membre trouvé" :text="$total === 0 && ! $filters['q'] && ! $filters['level'] && $filters['status'] === 'active' ? 'Ajoutez les membres de l’association pour faire l’appel et composer les équipages.' : 'Modifiez les filtres ou la recherche.'">
            @can('manage')
                <a href="{{ route('members.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Ajouter un membre</a>
            @endcan
        </x-empty-state>
    @else
        <div class="card mt-4 overflow-hidden hidden lg:block">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr><th class="th">Membre</th><th class="th">Postes maîtrisés</th><th class="th">Niveau</th><th class="th">Gabarit</th><th class="th w-48">Présence (saison)</th><th class="th">Téléphone</th><th class="th"></th></tr>
                </thead>
                <tbody>
                    @foreach ($members as $member)
                        @php($rate = $rates[$member->id] ?? null)
                        <tr class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('members.show', $member) }}'">
                            <td class="td">
                                <a href="{{ route('members.show', $member) }}" class="flex items-center gap-3">
                                    <x-avatar :member="$member" size="w-9 h-9 text-xs" />
                                    <div>
                                        <p class="font-bold">{{ $member->full_name }}</p>
                                        <p class="text-xs muted">
                                            @if ($member->nickname)« {{ $member->nickname }} »@endif
                                            @if ($member->nickname && $member->category) · @endif
                                            {{ $member->category?->label() }}
                                            @unless ($member->is_active)<span class="chip bg-slate-100 text-slate-500 ml-1 py-0">Inactif</span>@endunless
                                        </p>
                                    </div>
                                </a>
                            </td>
                            <td class="td">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($member->orderedCrewRoles() as $crewRole)
                                        <x-role-pill :role="$crewRole" :preferred="$crewRole->pivot->is_preferred" />
                                    @empty
                                        <span class="text-xs muted">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="td"><x-level-pill :level="$member->level" /></td>
                            <td class="td text-sm whitespace-nowrap">{!! $member->weight_kg !== null ? '<b>'.e($member->formattedWeight()).'</b> kg' : '—' !!}{{ $member->height_cm ? ' · '.$member->height_cm.' cm' : '' }}</td>
                            <td class="td">
                                @if ($rate !== null)
                                    <div class="flex items-center gap-2"><div class="flex-1"><x-bar :value="$rate" :color="\App\Services\AttendanceStats::rateColor($rate)" /></div><span class="text-xs font-bold w-9 text-right">{{ $rate }}%</span></div>
                                @else
                                    <span class="text-xs muted">Aucun appel</span>
                                @endif
                            </td>
                            <td class="td text-sm muted whitespace-nowrap">{{ $member->phone ?? '—' }}</td>
                            <td class="td text-right text-slate-400"><x-icon name="right" class="w-4 h-4 inline" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="lg:hidden mt-4 space-y-2">
            @foreach ($members as $member)
                @php($rate = $rates[$member->id] ?? null)
                @php($primary = $member->primaryCrewRole())
                <a href="{{ route('members.show', $member) }}" class="card p-3 flex items-center gap-3">
                    <x-avatar :member="$member" />
                    <div class="flex-1 min-w-0">
                        <p class="font-bold truncate">{{ $member->full_name }}</p>
                        <div class="flex items-center gap-1.5 mt-1">
                            @if ($primary)
                                <x-role-pill :role="$primary" :preferred="$primary->pivot->is_preferred" />
                            @endif
                            @if ($member->weight_kg !== null)
                                <span class="text-xs muted">{{ $member->formattedWeight() }} kg</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-extrabold" style="color: {{ \App\Services\AttendanceStats::rateColor($rate) }}">{{ $rate !== null ? $rate.'%' : '—' }}</p>
                        <p class="text-[10px] muted">présence</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
