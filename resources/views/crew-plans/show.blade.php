<x-layouts.app :title="$plan->boat->name.' · '.($plan->isValidated() ? 'Plan validé' : 'Plan d’équipage')" :crumb="ucfirst($outing->date->translatedFormat('D j M')).' · '.$outing->title" :back="route('outings.show', $outing)">
    <x-slot:actions>
        <button type="button" data-print class="btn-ghost btn-sm"><x-icon name="printer" class="w-4 h-4" />Imprimer / PDF</button>
        @if ($plan->isValidated())
            <form method="POST" action="{{ route('crew-plans.validation.destroy', [$outing, $plan]) }}">
                @csrf
                @method('DELETE')
                <button class="btn-primary btn-sm"><x-icon name="edit" class="w-4 h-4" />Modifier</button>
            </form>
        @else
            <a href="{{ route('crew-plans.edit', [$outing, $plan]) }}" class="btn-primary btn-sm"><x-icon name="edit" class="w-4 h-4" />Continuer</a>
        @endif
    </x-slot:actions>
    <x-slot:sticky>
        <div class="flex gap-2">
            <button type="button" data-print class="btn-ghost flex-1 h-12"><x-icon name="printer" class="w-4 h-4" />Imprimer</button>
            @if ($plan->isValidated())
                <form method="POST" action="{{ route('crew-plans.validation.destroy', [$outing, $plan]) }}" class="flex-1">
                    @csrf
                    @method('DELETE')
                    <button class="btn-primary w-full h-12"><x-icon name="edit" class="w-4 h-4" />Modifier</button>
                </form>
            @else
                <a href="{{ route('crew-plans.edit', [$outing, $plan]) }}" class="btn-primary flex-1 h-12">Continuer</a>
            @endif
        </div>
    </x-slot:sticky>

    @php
        $facts = collect([
            'Version '.$plan->version,
            $balance['filled'].'/'.$balance['positions'].' postes',
            $plan->configuration->name,
            $plan->wind_direction !== null ? 'vent '.\App\Services\CrewPlanPresenter::windLabel($plan->wind_direction).($plan->wind_strength ? ' '.$plan->wind_strength.' nds' : '') : null,
            round($balance['total']).' kg à bord',
        ])->filter()->join(' · ');
    @endphp

    <h1 class="hidden print:block text-2xl font-extrabold mb-2">{{ $plan->boat->name }} — {{ $outing->title }} ({{ $outing->date->translatedFormat('j F Y') }})</h1>

    @if ($plan->isValidated())
        <div class="card p-4 flex flex-wrap items-center gap-3 bg-emerald-50 border-emerald-200">
            <span class="w-10 h-10 rounded-xl bg-emerald-500 text-white grid place-items-center"><x-icon name="check" /></span>
            <div class="flex-1 min-w-0">
                <p class="font-bold text-emerald-900">Plan validé{{ $plan->validator ? ' par '.$plan->validator->name : '' }} le {{ $plan->validated_at->translatedFormat('j M à H:i') }}</p>
                <p class="text-xs text-emerald-800">{{ $facts }}</p>
            </div>
        </div>
    @else
        <div class="card p-4 flex flex-wrap items-center gap-3 bg-amber-50 border-amber-200">
            <span class="w-10 h-10 rounded-xl bg-amber-400 text-navy-950 grid place-items-center"><x-icon name="edit" /></span>
            <div class="flex-1 min-w-0">
                <p class="font-bold text-amber-900">Brouillon — pas encore validé</p>
                <p class="text-xs text-amber-800">{{ $facts }}</p>
            </div>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[440px_1fr] mt-5">
        <div class="card p-3"><x-yole :data="$drawing" /></div>
        <div class="grid sm:grid-cols-2 gap-4 content-start">
            @forelse ($groups as $title => $assignments)
                <div class="card p-4">
                    <p class="font-bold mb-3">{{ $title }}</p>
                    <div class="space-y-2.5">
                        @foreach ($assignments as $assignment)
                            <div class="flex items-center gap-3">
                                <x-avatar :member="$assignment->member" size="w-9 h-9 text-xs" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold truncate">{{ $assignment->member->full_name }}</p>
                                    <p class="text-[11px] muted">{{ $assignment->position->label }}@if ($assignment->bwa_placement) · {{ mb_strtolower($assignment->bwa_placement->label()) }}@endif</p>
                                </div>
                                @if ($assignment->member->weight_kg)
                                    <span class="text-xs font-bold">{{ (float) $assignment->member->weight_kg }} kg</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-empty-state class="sm:col-span-2" icon="boat" title="Aucun membre placé" text="Composez l’équipage dans l’éditeur." />
            @endforelse

            <div class="card p-4 sm:col-span-2">
                <p class="font-bold mb-2 flex items-center gap-2"><x-icon name="scale" class="w-4 h-4" />Équilibre</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
                    @foreach (['Bâbord' => $balance['babord'], 'Tribord' => $balance['tribord'], 'Avant' => $balance['avant'], 'Arrière' => $balance['arriere']] as $label => $weight)
                        <div class="rounded-lg bg-slate-50 p-2"><p class="text-[10px] muted font-bold uppercase">{{ $label }}</p><p class="font-extrabold">{{ round($weight) }} kg</p></div>
                    @endforeach
                </div>
                <p class="mt-2 text-[11px] muted">Indication basée sur les poids déclarés.</p>
            </div>

            @if ($plan->notes)
                <div class="card p-4 sm:col-span-2"><p class="font-bold mb-1">Notes</p><p class="text-sm text-slate-600 whitespace-pre-line">{{ $plan->notes }}</p></div>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('crew-plans.destroy', [$outing, $plan]) }}" class="no-print mt-8 text-center" onsubmit="return confirm('Supprimer ce plan d’équipage ?')">
        @csrf
        @method('DELETE')
        <button class="text-sm font-semibold text-red-600 hover:underline cursor-pointer">Supprimer ce plan d’équipage</button>
    </form>
</x-layouts.app>
