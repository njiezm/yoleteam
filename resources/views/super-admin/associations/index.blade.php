<x-layouts.app title="Associations" crumb="Super admin" :back="route('super-admin.dashboard')">
    <x-slot:actions>
        <a href="{{ route('super-admin.associations.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle association</a>
    </x-slot:actions>
    <x-slot:sticky>
        <a href="{{ route('super-admin.associations.create') }}" class="btn-primary w-full h-12"><x-icon name="plus" class="w-4 h-4" />Nouvelle association</a>
    </x-slot:sticky>

    @include('super-admin._tabs')

    @if ($associations->isEmpty())
        <x-empty-state icon="boat" title="Aucune association" text="Créez la première association et son compte bureau.">
            <a href="{{ route('super-admin.associations.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle association</a>
        </x-empty-state>
    @else
        <div class="card overflow-hidden hidden lg:block">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr><th class="th">Association</th><th class="th">Identifiant</th><th class="th text-right">Comptes</th><th class="th text-right">Membres</th><th class="th text-right">Yoles</th><th class="th text-right">Sorties</th><th class="th"></th></tr>
                </thead>
                <tbody>
                    @foreach ($associations as $association)
                        <tr class="hover:bg-slate-50">
                            <td class="td">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-xl text-sun-400 grid place-items-center shrink-0" style="background: {{ $association->primary_color }}"><x-icon name="boat" class="w-[18px] h-[18px]" /></span>
                                    <div class="min-w-0">
                                        <p class="font-bold truncate">{{ $association->name }}@if ($association->id === auth()->user()->association_id) <span class="chip bg-sun-100 text-amber-800 ml-1 py-0">Actuelle</span>@endif</p>
                                        <p class="text-xs muted">{{ $association->city ?: 'Commune non renseignée' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="td text-sm muted">{{ $association->slug }}</td>
                            <td class="td text-right font-semibold">{{ $association->users_count }}</td>
                            <td class="td text-right font-semibold">{{ $association->members_count }}</td>
                            <td class="td text-right font-semibold">{{ $association->boats_count }}</td>
                            <td class="td text-right font-semibold">{{ $association->outings_count }}</td>
                            <td class="td">
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('super-admin.associations.switch', $association) }}">
                                        @csrf
                                        <button class="btn-ghost btn-sm">Ouvrir</button>
                                    </form>
                                    <a href="{{ route('super-admin.associations.edit', $association) }}" class="w-9 h-9 grid place-items-center rounded-lg text-slate-400 hover:text-navy-900 hover:bg-slate-100" title="Modifier" aria-label="Modifier {{ $association->name }}"><x-icon name="edit" class="w-4 h-4" /></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="lg:hidden space-y-2">
            @foreach ($associations as $association)
                <div class="card p-3 flex items-center gap-3">
                    <a href="{{ route('super-admin.associations.edit', $association) }}" class="flex items-center gap-3 flex-1 min-w-0">
                        <span class="w-10 h-10 rounded-xl text-sun-400 grid place-items-center shrink-0" style="background: {{ $association->primary_color }}"><x-icon name="boat" class="w-5 h-5" /></span>
                        <div class="min-w-0">
                            <p class="font-bold truncate">{{ $association->name }}</p>
                            <p class="text-xs muted">{{ $association->users_count }} comptes · {{ $association->members_count }} membres · {{ $association->outings_count }} sorties</p>
                        </div>
                    </a>
                    <form method="POST" action="{{ route('super-admin.associations.switch', $association) }}">
                        @csrf
                        <button class="btn-ghost btn-sm">Ouvrir</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
