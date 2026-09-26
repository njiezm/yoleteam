<x-layouts.app title="Plan d’équipage" :crumb="ucfirst($outing->date->translatedFormat('D j M')).' · '.$outing->title.' · '.$plan->boat->name" :back="route('outings.show', $outing)">
    <x-slot:actions>
        <button type="button" data-action="reset" class="btn-ghost btn-sm"><x-icon name="refresh" class="w-4 h-4" />Vider</button>
        <a href="{{ route('crew-plans.show', [$outing, $plan]) }}" class="btn-ghost btn-sm"><x-icon name="printer" class="w-4 h-4" />Aperçu</a>
        <button type="button" data-action="validate" class="btn-sun btn-sm"><x-icon name="check" class="w-4 h-4" />Valider le plan</button>
    </x-slot:actions>
    <x-slot:sticky>
        <div class="flex gap-2">
            <div class="flex-1 card px-3 flex items-center gap-2 h-12">
                <span class="text-sm font-bold" data-progress-text></span>
                <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full bg-navy-900" data-progress-bar></div></div>
                <span class="chip" data-balance-chip></span>
            </div>
            <button type="button" data-action="validate" class="btn-sun h-12">Valider</button>
        </div>
    </x-slot:sticky>

    <x-outing-picker :current="$outing" target="plans" class="mb-4 max-w-xl" />

    @include('crew-plans._editor', ['inline' => false])
</x-layouts.app>
