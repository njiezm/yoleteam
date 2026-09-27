{{-- Every crew plan of an outing side by side (read-only, printable), grouped by race on championship days. --}}
<x-layouts.app title="Tous les plans d’équipage" :crumb="ucfirst($outing->date->translatedFormat('D j M')).' · '.$outing->title" :back="route('outings.show', $outing).'#equipages'">
    <x-slot:actions>
        <button type="button" data-print class="btn-ghost btn-sm"><x-icon name="printer" class="w-4 h-4" />Imprimer / PDF</button>
    </x-slot:actions>

    @php
        $isRace = $outing->type === \App\Enums\OutingType::Regate;
        // Rowers seated on several boats of the same race (padlock open).
        $boatsByMember = $outing->crewPlans->groupBy('race_number')->map(fn ($plans) => $plans
            ->flatMap(fn ($plan) => $plan->assignments->map(fn ($assignment) => [$assignment->member_id, $plan->boat->name]))
            ->groupBy(0)->map(fn ($rows) => $rows->pluck(1)->unique()->values()));
    @endphp

    <h1 class="hidden print:block text-2xl font-extrabold mb-2">{{ $outing->title }} — {{ $outing->date->translatedFormat('j F Y') }}</h1>

    <div class="card p-4 flex flex-wrap items-center gap-3 no-print">
        <span @class(['chip', 'bg-emerald-100 text-emerald-800' => $outing->share_crew, 'bg-slate-100 text-slate-700' => ! $outing->share_crew])>
            <x-icon :name="$outing->share_crew ? 'unlock' : 'lock'" class="w-3.5 h-3.5" />{{ $outing->share_crew ? 'Coursiers réutilisables entre les yoles' : 'Un coursier = une yole' }}
        </span>
        <span class="text-sm muted">{{ $outing->crewPlans->count() }} plan(s) · {{ $outing->crewPlans->filter->isValidated()->count() }} validé(s)</span>
    </div>

    @forelse ($outing->crewPlans->groupBy('race_number') as $raceNumber => $plans)
        @if ($isRace)
            <h2 class="font-extrabold text-lg mt-6 mb-3">Manche {{ $raceNumber }}</h2>
        @endif
        <div @class(['grid gap-5 lg:grid-cols-2 print:grid-cols-2', 'mt-5' => ! $isRace])>
            @foreach ($plans as $plan)
                @php($balance = $presenter->balance($plan))
                @php($others = $boatsByMember->get($raceNumber))
                <section class="card p-4 min-w-0 print-avoid-break">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <h3 class="text-lg font-extrabold flex items-center gap-2"><i class="w-3 h-3 rounded-full" style="background: {{ $plan->boat->color() }}"></i>{{ $plan->boat->name }}</h3>
                        <x-plan-status :plan="$plan" />
                    </div>
                    <p class="text-xs muted mt-0.5">{{ $plan->configuration->name }} · {{ $balance['filled'] }}/{{ $balance['positions'] }} postes · {{ round($balance['total']) }} kg à bord</p>

                    <div class="grid sm:grid-cols-[minmax(0,220px)_1fr] gap-4 mt-3">
                        <x-yole class="w-full max-w-[220px] mx-auto" :data="$presenter->drawing($plan->boat, $plan->configuration, $plan, ['wind' => false])" />
                        <div class="space-y-3 min-w-0">
                            @forelse ($presenter->groupedAssignments($plan) as $title => $assignments)
                                <div>
                                    <p class="text-[11px] font-bold uppercase muted mb-1">{{ $title }}</p>
                                    <div class="space-y-1">
                                        @foreach ($assignments as $assignment)
                                            @php($alsoOn = collect($others?->get($assignment->member_id))->reject(fn ($name) => $name === $plan->boat->name))
                                            <div class="flex items-center gap-2 text-sm min-w-0">
                                                <x-avatar :member="$assignment->member" size="w-6 h-6 text-[9px]" />
                                                <span class="font-semibold truncate">{{ $assignment->member->short_name }}</span>
                                                <span class="text-[11px] muted truncate">{{ $assignment->position->label }}</span>
                                                @if ($alsoOn->isNotEmpty())
                                                    <span class="chip bg-sky-100 text-sky-800 py-0 text-[10px] whitespace-nowrap">aussi sur {{ $alsoOn->join(', ') }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm muted">Aucun coursier placé.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="no-print mt-4 flex gap-2">
                        <a href="{{ route('crew-plans.edit', [$outing, $plan]) }}" class="btn-primary btn-sm"><x-icon name="edit" class="w-4 h-4" />Composer</a>
                        <a href="{{ route('crew-plans.show', [$outing, $plan]) }}" class="btn-ghost btn-sm">Détail</a>
                    </div>
                </section>
            @endforeach
        </div>
    @empty
        <x-empty-state class="mt-5" icon="boat" title="Aucun plan d’équipage" text="Engagez une yole depuis la page de la sortie." />
    @endforelse
</x-layouts.app>
