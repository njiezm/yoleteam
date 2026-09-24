<x-layouts.app title="Nouveau compte" crumb="Super admin · Utilisateurs" :back="route('super-admin.users.index')">
    <x-slot:actions>
        <a href="{{ route('super-admin.users.index') }}" class="btn-ghost btn-sm">Annuler</a>
        <button form="user-form" class="btn-primary btn-sm">Créer</button>
    </x-slot:actions>
    <x-slot:sticky>
        <button form="user-form" class="btn-primary w-full h-12">Créer le compte</button>
    </x-slot:sticky>

    @include('super-admin.users._form', ['action' => route('super-admin.users.store'), 'method' => 'POST'])
</x-layouts.app>
