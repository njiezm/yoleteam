<x-layouts.app title="Nouvelle association" crumb="Super admin · Associations" :back="route('super-admin.associations.index')">
    <x-slot:actions>
        <a href="{{ route('super-admin.associations.index') }}" class="btn-ghost btn-sm">Annuler</a>
        <button form="association-form" class="btn-primary btn-sm">Créer</button>
    </x-slot:actions>
    <x-slot:sticky>
        <button form="association-form" class="btn-primary w-full h-12">Créer l’association</button>
    </x-slot:sticky>

    @include('super-admin.associations._form', ['action' => route('super-admin.associations.store'), 'method' => 'POST'])
</x-layouts.app>
