<x-layouts.app :title="'Modifier '.$member->full_name" crumb="Membres" :back="route('members.show', $member)">
    <x-slot:actions>
        <a href="{{ route('members.show', $member) }}" class="btn-ghost btn-sm">Annuler</a>
        <button form="member-form" class="btn-primary btn-sm">Enregistrer</button>
    </x-slot:actions>
    <x-slot:sticky>
        <button form="member-form" class="btn-primary w-full h-12">Enregistrer le membre</button>
    </x-slot:sticky>

    @include('members._form', ['action' => route('members.update', $member), 'method' => 'PUT'])

    <section class="card p-5 lg:p-6 max-w-4xl mt-5 flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex-1">
            <h3 class="font-bold">Supprimer ce membre</h3>
            <p class="text-xs muted mt-0.5">Le membre disparaît des listes ; son historique de présences est conservé.</p>
        </div>
        <form method="POST" action="{{ route('members.destroy', $member) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Supprimer '.$member->full_name.' ?') }})">
            @csrf
            @method('DELETE')
            <button class="btn-danger btn-sm"><x-icon name="trash" class="w-4 h-4" />Supprimer</button>
        </form>
    </section>
</x-layouts.app>
