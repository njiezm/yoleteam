<x-layouts.app title="Nouvelle yole" crumb="Yoles" :back="route('boats.index')">
    <form method="POST" action="{{ route('boats.store') }}" class="max-w-3xl space-y-5" id="boat-form">
        @csrf
        <section class="card p-5 lg:p-6">
            <h3 class="font-bold mb-4">Yole</h3>
            @include('boats._fields', ['boat' => new \App\Models\Boat(['is_active' => true])])
        </section>
        <div class="card p-4 flex gap-3 text-sm bg-sky-50 border-sky-100 text-sky-900">
            <x-icon name="boat" class="w-5 h-5 mt-0.5" />
            <p>Deux configurations sont créées automatiquement : <b>1 voile</b> (3 bwa) et <b>2 voiles</b> (4 bwa, par défaut), avec leurs postes. Vous pourrez les ajuster ensuite.</p>
        </div>
        <div class="hidden lg:flex gap-2">
            <button class="btn-primary">Enregistrer la yole</button>
            <a href="{{ route('boats.index') }}" class="btn-ghost">Annuler</a>
        </div>
    </form>

    <x-slot:sticky>
        <button form="boat-form" class="btn-primary w-full">Enregistrer la yole</button>
    </x-slot:sticky>
</x-layouts.app>
