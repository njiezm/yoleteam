<x-layouts.app title="Modifier la régate" :crumb="$race->name" :back="route('races.show', $race)">
    <form method="POST" action="{{ route('races.update', $race) }}" id="race-form" class="max-w-3xl space-y-5">
        @csrf
        @method('PUT')
        <section class="card p-5 lg:p-6">
            @include('races._form')
        </section>
        <div class="hidden lg:flex gap-2">
            <button class="btn-primary">Enregistrer</button>
            <a href="{{ route('races.show', $race) }}" class="btn-ghost">Annuler</a>
        </div>
    </form>

    <section class="card p-5 lg:p-6 max-w-3xl mt-5 border-red-200">
        <h3 class="font-bold">Supprimer la régate</h3>
        <p class="text-sm muted mt-1">Les étapes et tous les résultats saisis seront supprimés.</p>
        <form method="POST" action="{{ route('races.destroy', $race) }}" class="mt-4" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Supprimer définitivement « {{ $race->name }} » et ses résultats ?">
            @csrf
            @method('DELETE')
            <button class="btn-danger btn-sm"><x-icon name="trash" class="w-4 h-4" />Supprimer la régate</button>
        </form>
    </section>

    <x-slot:sticky>
        <button form="race-form" class="btn-primary w-full">Enregistrer</button>
    </x-slot:sticky>
</x-layouts.app>
