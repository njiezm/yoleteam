<x-layouts.app title="Nouveau membre" crumb="Membres" :back="route('members.index')">
    <x-slot:actions>
        <a href="{{ route('members.index') }}" class="btn-ghost btn-sm">Annuler</a>
        <button form="member-form" class="btn-primary btn-sm">Enregistrer</button>
    </x-slot:actions>
    <x-slot:sticky>
        <button form="member-form" class="btn-primary w-full h-12">Enregistrer le membre</button>
    </x-slot:sticky>

    @include('members._form', ['action' => route('members.store'), 'method' => 'POST'])
</x-layouts.app>
