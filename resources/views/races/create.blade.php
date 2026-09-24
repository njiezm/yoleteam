<x-layouts.app title="Nouvelle régate" crumb="Régates" :back="route('races.index')">
    <form method="POST" action="{{ route('races.store') }}" id="race-form" class="max-w-3xl space-y-5">
        @csrf
        <section class="card p-5 lg:p-6">
            @include('races._form')
        </section>
        <div class="hidden lg:flex gap-2">
            <button class="btn-primary">Enregistrer la régate</button>
            <a href="{{ route('races.index') }}" class="btn-ghost">Annuler</a>
        </div>
    </form>

    <x-slot:sticky>
        <button form="race-form" class="btn-primary w-full">Enregistrer la régate</button>
    </x-slot:sticky>
</x-layouts.app>
