@php
    $isEmpty = $association->users_count + $association->members_count + $association->boats_count + $association->outings_count + $association->races_count === 0;
@endphp
<x-layouts.app :title="$association->name" crumb="Super admin · Associations" :back="route('super-admin.associations.index')">
    <x-slot:actions>
        <a href="{{ route('super-admin.associations.index') }}" class="btn-ghost btn-sm">Annuler</a>
        <button form="association-form" class="btn-primary btn-sm">Enregistrer</button>
    </x-slot:actions>
    <x-slot:sticky>
        <button form="association-form" class="btn-primary w-full h-12">Enregistrer l’association</button>
    </x-slot:sticky>

    @error('association')
        <div class="rounded-xl bg-red-50 text-red-700 text-sm font-semibold p-3 mb-5 flex items-center gap-2 max-w-3xl"><x-icon name="alert" class="w-4 h-4" />{{ $message }}</div>
    @enderror

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-3xl mb-5">
        @foreach (['Comptes' => $association->users_count, 'Membres' => $association->members_count, 'Yoles' => $association->boats_count, 'Sorties' => $association->outings_count] as $label => $count)
            <div class="card p-3"><p class="text-xl font-extrabold">{{ $count }}</p><p class="text-xs muted font-semibold">{{ $label }}</p></div>
        @endforeach
    </div>

    @include('super-admin.associations._form', ['action' => route('super-admin.associations.update', $association), 'method' => 'PUT'])

    <section class="card p-5 lg:p-6 max-w-3xl mt-5">
        <h3 class="font-bold mb-3">Actions</h3>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('super-admin.associations.switch', $association) }}">
                @csrf
                <button class="btn-ghost btn-sm"><x-icon name="swap" class="w-4 h-4" />Ouvrir cette association</button>
            </form>
            <a href="{{ route('super-admin.users.index', ['association' => $association->id]) }}" class="btn-ghost btn-sm"><x-icon name="users" class="w-4 h-4" />Voir les comptes</a>
            <a href="{{ route('super-admin.users.create', ['association' => $association->id]) }}" class="btn-ghost btn-sm"><x-icon name="plus" class="w-4 h-4" />Ajouter un compte</a>
            @if ($isEmpty)
                <form method="POST" action="{{ route('super-admin.associations.destroy', $association) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Supprimer définitivement l’association {{ $association->name }} ?">
                    @csrf
                    @method('DELETE')
                    <button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="w-4 h-4" />Supprimer</button>
                </form>
            @endif
        </div>
        @unless ($isEmpty)
            <p class="text-xs muted mt-3">La suppression n’est possible que pour une association vide (aucun compte, membre, yole, sortie ni régate).</p>
        @endunless
    </section>
</x-layouts.app>
