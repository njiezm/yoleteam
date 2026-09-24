<x-layouts.app :title="$outing->title" :crumb="'Sorties · '.ucfirst($outing->date->translatedFormat('l j F Y'))" :back="route('outings.index')">
    <x-slot:actions>
        <a href="{{ route('outings.edit', $outing) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="w-4 h-4" />Modifier</a>
        <a href="{{ route('attendance.edit', $outing) }}" class="btn-sun btn-sm"><x-icon name="check-square" class="w-4 h-4" />Faire l’appel</a>
    </x-slot:actions>

    @php
        $onSite = $counts['present'] + $counts['retard'];
        $toRecord = max(0, $activeMembers - $counts['total']);
        $validated = $outing->crewPlans->filter->isValidated()->count();
        $filledSummary = $outing->crewPlans->map(fn ($plan) => $plan->boat->name.' '.$plan->assignments->count().'/'.$plan->configuration->positions->count())->join(' · ');
        $steps = [
            ['Présences', "$onSite présents · $toRecord à pointer", route('attendance.edit', $outing), $counts['total'] === 0],
            ['Constitution des équipages', $filledSummary ?: 'Aucune yole engagée', $outing->crewPlans->count() === 1 ? route('crew-plans.edit', [$outing, $outing->crewPlans->first()]) : '#equipages', $counts['total'] > 0 && $validated < $outing->crewPlans->count()],
            ['Validation', $outing->crewPlans->isEmpty() ? '—' : "$validated plan(s) sur {$outing->crewPlans->count()} validé(s)", '#equipages', false],
        ];
    @endphp

    <div class="card p-5 flex flex-wrap items-center gap-x-6 gap-y-3">
        <x-outing-status :status="$outing->status" />
        <span class="chip bg-slate-100 text-slate-700">{{ $outing->type->label() }}</span>
        <span class="text-sm font-semibold flex items-center gap-1.5 text-slate-700"><x-icon name="calendar" class="w-4 h-4 text-slate-400" />{{ ucfirst($outing->date->translatedFormat('D j M')) }}</span>
        @if ($outing->time_range)
            <span class="text-sm font-semibold flex items-center gap-1.5 text-slate-700"><x-icon name="clock" class="w-4 h-4 text-slate-400" />{{ $outing->time_range }}</span>
        @endif
        @if ($outing->location)
            <span class="text-sm font-semibold flex items-center gap-1.5 text-slate-700"><x-icon name="pin" class="w-4 h-4 text-slate-400" />{{ $outing->location }}</span>
        @endif
        @if ($outing->conditionsSummary())
            <span class="text-sm font-semibold flex items-center gap-1.5 text-slate-700"><x-icon name="wind" class="w-4 h-4 text-slate-400" />{{ $outing->conditionsSummary() }}</span>
        @endif
        @if ($outing->raceStage)
            <a href="{{ route('races.show', $outing->raceStage->race) }}" class="text-sm font-semibold flex items-center gap-1.5 text-navy-700"><x-icon name="trophy" class="w-4 h-4 text-slate-400" />{{ $outing->raceStage->race->name }} · {{ $outing->raceStage->name }}</a>
        @endif
        @if ($outing->creator)
            <span class="text-sm font-semibold flex items-center gap-1.5 text-slate-700"><x-icon name="user" class="w-4 h-4 text-slate-400" />{{ $outing->creator->name }}</span>
        @endif
    </div>

    @if ($outing->notes)
        <div class="card p-5 mt-5"><p class="text-[11px] font-bold uppercase muted mb-2">Consignes</p><p class="text-sm text-slate-700 whitespace-pre-line">{{ $outing->notes }}</p></div>
    @endif

    <div class="card p-5 mt-5">
        <p class="text-[11px] font-bold uppercase muted mb-4">Déroulé avant la sortie</p>
        <ol class="grid sm:grid-cols-3 gap-3">
            @foreach ($steps as $index => [$label, $summary, $url, $isCurrent])
                <li>
                    <a href="{{ $url }}" @class(['flex items-center gap-3 p-4 rounded-2xl', 'bg-sun-100 ring-2 ring-sun-400' => $isCurrent, 'bg-slate-50 hover:bg-slate-100' => ! $isCurrent])>
                        <span @class(['w-9 h-9 shrink-0 rounded-full grid place-items-center font-extrabold', 'bg-sun-400 text-navy-950' => $isCurrent, 'bg-white text-navy-900 border border-slate-200' => ! $isCurrent])>{{ $index + 1 }}</span>
                        <div class="flex-1 min-w-0"><p class="font-bold">{{ $label }}</p><p class="text-xs muted truncate">{{ $summary }}</p></div>
                        <x-icon name="right" class="w-4 h-4 text-slate-400" />
                    </a>
                </li>
            @endforeach
        </ol>
    </div>

    <div id="equipages" class="grid gap-5 lg:grid-cols-2 mt-5">
        @foreach ($outing->crewPlans as $plan)
            @php($total = $plan->configuration->positions->count())
            @php($filled = $plan->assignments->count())
            <a href="{{ $plan->isValidated() ? route('crew-plans.show', [$outing, $plan]) : route('crew-plans.edit', [$outing, $plan]) }}" class="card p-5 flex gap-5 hover:shadow-lg transition">
                <x-yole class="w-28 shrink-0 self-start" :data="$presenter->drawing($plan->boat, $plan->configuration, $plan, ['labels' => false, 'compact' => true, 'wind' => false])" />
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2"><h3 class="text-lg font-extrabold">{{ $plan->boat->name }}</h3><x-plan-status :plan="$plan" /></div>
                    <p class="text-sm muted">{{ $plan->configuration->name }} · {{ $plan->configuration->bwa_count }} bwa dressés</p>
                    <x-bar class="mt-4" :value="$total ? $filled / $total * 100 : 0" :color="$plan->boat->color()" />
                    <p class="text-xs font-bold mt-1.5">{{ $filled }}/{{ $total }} postes pourvus</p>
                    @if ($filled)
                        <div class="flex -space-x-2 mt-4">
                            @foreach ($plan->assignments->take(7) as $assignment)
                                <x-avatar :member="$assignment->member" size="w-8 h-8 text-[10px]" class="ring-2 ring-white" />
                            @endforeach
                            @if ($filled > 7)
                                <span class="w-8 h-8 rounded-full bg-slate-100 ring-2 ring-white grid place-items-center text-[10px] font-bold">+{{ $filled - 7 }}</span>
                            @endif
                        </div>
                    @endif
                    <p class="mt-4 text-sm font-bold text-navy-700 flex items-center gap-1">{{ $plan->isValidated() ? 'Voir le plan' : ($filled ? 'Continuer le plan' : 'Composer l’équipage') }} <x-icon name="right" class="w-4 h-4" /></p>
                </div>
            </a>
        @endforeach

        @if ($availableBoats->isNotEmpty())
            <form method="POST" action="{{ route('crew-plans.store', $outing) }}" class="card p-5 border-dashed border-2 border-slate-300 bg-slate-50/50 flex flex-col justify-center gap-3">
                @csrf
                <p class="font-bold flex items-center gap-2"><x-icon name="plus" class="w-4 h-4" />Engager une yole</p>
                <div class="flex flex-wrap gap-2">
                    <select name="boat_id" class="input flex-1 min-w-40" aria-label="Yole">
                        @foreach ($availableBoats as $boat)
                            <option value="{{ $boat->id }}">{{ $boat->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn-primary">Créer le plan</button>
                </div>
                @error('boat_id')<p class="text-xs text-red-600 font-semibold">{{ $message }}</p>@enderror
                <p class="text-xs muted">La configuration par défaut de la yole est utilisée ; vous pourrez passer de 1 à 2 voiles dans l’éditeur.</p>
            </form>
        @elseif ($outing->crewPlans->isEmpty())
            <x-empty-state class="lg:col-span-2" icon="boat" title="Aucune yole disponible" text="Ajoutez une yole ou remettez-en une en service pour composer un équipage." />
        @endif
    </div>

    <x-delete-zone :action="route('outings.destroy', $outing)" label="Supprimer la sortie"
                   :confirm="'Supprimer la sortie « '.$outing->title.' », son appel et ses plans d’équipage ?'"
                   hint="L’appel et les plans d’équipage de cette sortie seront supprimés. Pour garder l’historique, passez-la plutôt en « Annulée »." />
</x-layouts.app>
