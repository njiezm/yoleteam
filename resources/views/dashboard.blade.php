<x-layouts.app :title="'Bonjour '.explode(' ', auth()->user()->name)[0]" :crumb="ucfirst(today()->translatedFormat('l j F Y'))">
    <x-slot:actions>
        <a href="{{ route('outings.create') }}" class="btn-ghost btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle sortie</a>
    </x-slot:actions>

    <script type="application/json" data-offline-urls>@json($offlineUrls)</script>

    <div class="grid gap-5 xl:grid-cols-3">
        @if ($training)
            @php
                $onSite = $trainingCounts['present'] + $trainingCounts['retard'];
                $toRecord = max(0, $activeMembers - $trainingCounts['total']);
                $when = $training->isToday() ? 'Aujourd’hui' : ($training->date->isFuture() ? 'Prochain entraînement · '.$training->date->translatedFormat('l j F') : 'Dernier entraînement · '.$training->date->translatedFormat('l j F'));
            @endphp
            <section class="xl:col-span-2 rounded-3xl bg-navy-900 text-white p-5 lg:p-7">
                <div class="flex items-center gap-2 flex-wrap">
                    <span @class(['chip', 'bg-emerald-400/20 text-emerald-300' => $training->isToday(), 'bg-white/10 text-navy-100' => ! $training->isToday()])>
                        @if ($training->isToday())<i class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></i>@endif{{ ucfirst($when) }}
                    </span>
                    @if ($training->time_range)
                        <span class="text-navy-200 text-sm">{{ $training->time_range }}</span>
                    @endif
                </div>
                <h2 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-3">{{ $training->title }}</h2>
                @if ($training->location)
                    <p class="text-navy-200 text-sm mt-1 flex items-center gap-1.5"><x-icon name="pin" class="w-4 h-4" />{{ $training->location }}</p>
                @endif
                <div class="mt-5 max-w-lg">
                    <div class="flex justify-between text-xs text-navy-200 mb-1.5"><span>{{ $onSite }} / {{ $activeMembers }} sur place</span><span>{{ $toRecord }} à pointer</span></div>
                    <div class="h-2 rounded-full bg-white/10"><div class="h-full rounded-full bg-sun-400" style="width: {{ $activeMembers ? round($onSite / $activeMembers * 100) : 0 }}%"></div></div>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('attendance.edit', $training) }}" class="btn-sun"><x-icon name="check-square" class="w-4 h-4" />Faire l’appel</a>
                    <a href="{{ route('outings.show', $training) }}" class="btn bg-white/10 text-white hover:bg-white/15"><x-icon name="boat" class="w-4 h-4" />Équipages</a>
                </div>
            </section>

            <section class="card p-5">
                <x-section-title title="Équipages" />
                @forelse ($training->crewPlans as $plan)
                    @php($total = $plan->configuration->crewSeatCount())
                    <a href="{{ $plan->isValidated() ? route('crew-plans.show', [$training, $plan]) : route('crew-plans.edit', [$training, $plan]) }}" class="flex items-center gap-3 p-3 -mx-2 rounded-xl hover:bg-slate-50">
                        <span class="w-11 h-11 rounded-xl grid place-items-center text-white" style="background: {{ $plan->boat->color() }}"><x-icon name="boat" /></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2"><p class="font-bold truncate">{{ $plan->boat->name }}</p><x-plan-status :plan="$plan" /></div>
                            <p class="text-xs muted">{{ $plan->assignments_count }}/{{ $total }} postes</p>
                        </div>
                        <x-icon name="right" class="w-4 h-4 text-slate-400" />
                    </a>
                @empty
                    <p class="text-sm muted">Aucune yole engagée.</p>
                    <a href="{{ route('outings.show', $training) }}" class="btn-ghost btn-sm mt-3"><x-icon name="plus" class="w-4 h-4" />Engager une yole</a>
                @endforelse
                @foreach ($unavailableBoats as $boat)
                    <p class="mt-3 rounded-xl bg-amber-50 text-amber-800 text-[13px] p-3"><b>{{ $boat->name }}</b> indisponible.</p>
                @endforeach
            </section>
        @else
            <x-empty-state class="xl:col-span-3" title="Aucun entraînement programmé" text="Créez un entraînement pour faire l’appel et composer les équipages.">
                <a href="{{ route('outings.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle sortie</a>
            </x-empty-state>
        @endif
    </div>

    <div class="grid grid-cols-3 gap-3 lg:gap-5 mt-5">
        <x-kpi label="Membres actifs" :value="$activeMembers" icon="users" />
        <x-kpi label="Présence (30 j)" :value="$rate30 !== null ? $rate30.' %' : '—'" icon="chart" tone="green" />
        <x-kpi label="Sorties ce mois" :value="$outingsThisMonth" icon="calendar" tone="sky" />
    </div>

    <div class="grid gap-5 xl:grid-cols-2 mt-5">
        @include('attendance._alerts', ['alerts' => $alerts, 'limit' => 5])

        <section class="card p-5">
            <x-section-title title="Prochaines sorties">
                <a href="{{ route('outings.index') }}" class="text-sm font-semibold text-navy-700">Toutes</a>
            </x-section-title>
            <div class="divide-y divide-slate-100">
                @forelse ($upcoming as $outing)
                    <x-outing-row :outing="$outing" />
                @empty
                    <p class="text-sm muted py-3">Rien de prévu pour l’instant.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
