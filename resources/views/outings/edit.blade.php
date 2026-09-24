<x-layouts.app title="Modifier la sortie" :crumb="$outing->title" :back="route('outings.show', $outing)">
    <x-slot:actions>
        <a href="{{ route('outings.show', $outing) }}" class="btn-ghost btn-sm">Annuler</a>
        <button form="outing-form" class="btn-primary btn-sm">Enregistrer</button>
    </x-slot:actions>
    <x-slot:sticky>
        <button form="outing-form" class="btn-primary w-full h-12">Enregistrer</button>
    </x-slot:sticky>

    <form id="outing-form" method="POST" action="{{ route('outings.update', $outing) }}" class="max-w-3xl space-y-5">
        @csrf
        @method('PUT')
        @include('outings._form', ['statusField' => true])
    </form>

    <form method="POST" action="{{ route('outings.destroy', $outing) }}" class="max-w-3xl mt-5" onsubmit="return confirm('Supprimer cette sortie, son appel et ses plans d’équipage ?')">
        @csrf
        @method('DELETE')
        <div class="card p-5 flex flex-wrap items-center gap-3 border-red-200">
            <div class="flex-1 min-w-0">
                <p class="font-bold">Supprimer la sortie</p>
                <p class="text-xs muted">Pour garder l’historique, préférez le statut « Annulée ».</p>
            </div>
            <button class="btn-danger btn-sm"><x-icon name="trash" class="w-4 h-4" />Supprimer</button>
        </div>
    </form>
</x-layouts.app>
