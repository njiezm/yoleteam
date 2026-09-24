<x-layouts.app :title="'Bonjour '.explode(' ', auth()->user()->name)[0]" :crumb="ucfirst(today()->translatedFormat('l j F Y'))">
    <x-slot:actions>
        <a href="{{ route('outings.create') }}" class="btn-ghost btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle sortie</a>
    </x-slot:actions>

    <script type="application/json" data-offline-urls>@json($offlineUrls)</script>

    <div class="grid gap-5 xl:grid-cols-3">
        @if ($current)
            <section class="xl:col-span-2 rounded-3xl bg-navy-900 text-white p-5 lg:p-7 relative overflow-hidden">
                <div class="absolute -right-10 -bottom-16 w-72 h-72 rounded-full bg-sun-400/10 hidden sm:block"></div>
                <div class="absolute right-16 -top-20 w-48 h-48 rounded-full bg-white/5 hidden sm:block"></div>
                <div class="relative">
                    <div class="flex items-center gap-2 flex-wrap">
                        @if ($current->isToday())
                            <span class="chip bg-emerald-400/20 text-emerald-300"><i class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></i>Aujourd’hui</span>
                        @else
                            <span class="chip bg-white/10 text-navy-100">{{ ucfirst($current->date->translatedFormat('l j F')) }}</span>
                        @endif
                        <span class="text-navy-200 text-sm">{{ $current->type->label() }}@if ($current->time_range) · {{ $current->time_range }}@endif</span>
                    </div>
                    <h2 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-3">{{ $current->title }}</h2>
                    @if ($current->location)
                        <p class="text-navy-200 text-sm mt-1 flex items-center gap-1.5"><x-icon name="pin" class="w-4 h-4" />{{ $current->location }}</p>
                    @endif
                    <div class="mt-5 grid grid-cols-4 gap-2 max-w-lg">
                        @foreach (\App\Enums\AttendanceStatus::cases() as $status)
                            <div class="rounded-2xl bg-white/5 p-3">
                                <p class="text-2xl font-extrabold" style="color: {{ $status->color() }}">{{ $currentCounts[$status->value] }}</p>
                                <p class="text-[11px] text-navy-200 font-semibold">{{ $status->label() }}</p>
                            </div>
                        @endforeach
                    </div>
                    @php
                        $onSite = $currentCounts['present'] + $currentCounts['retard'];
                        $toRecord = max(0, $activeMembers - $currentCounts['total']);
                    @endphp
                    <div class="mt-4 max-w-lg">
                        <div class="flex justify-between text-xs text-navy-200 mb-1.5"><span>{{ $onSite }} / {{ $activeMembers }} membres sur place</span><span>{{ $toRecord }} non pointés</span></div>
                        <div class="h-2 rounded-full bg-white/10"><div class="h-full rounded-full bg-sun-400" style="width: {{ $activeMembers ? round($onSite / $activeMembers * 100) : 0 }}%"></div></div>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-2">
                        <a href="{{ route('attendance.edit', $current) }}" class="btn-sun"><x-icon name="check-square" class="w-4 h-4" />Faire l’appel</a>
                        <a href="{{ route('outings.show', $current) }}" class="btn bg-white/10 text-white hover:bg-white/15"><x-icon name="boat" class="w-4 h-4" />Équipages</a>
                    </div>
                </div>
            </section>

            <section class="card p-5">
                <x-section-title title="Équipages">
                    <a href="{{ route('outings.show', $current) }}" class="text-sm font-semibold text-navy-700">Voir</a>
                </x-section-title>
                @forelse ($current->crewPlans as $plan)
                    @php($total = $plan->configuration->positions->count())
                    <a href="{{ $plan->isValidated() ? route('crew-plans.show', [$current, $plan]) : route('crew-plans.edit', [$current, $plan]) }}" class="flex items-center gap-3 p-3 -mx-2 rounded-xl hover:bg-slate-50">
                        <span class="w-11 h-11 rounded-xl grid place-items-center text-white" style="background: {{ $plan->boat->color() }}"><x-icon name="boat" /></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2"><p class="font-bold">{{ $plan->boat->name }}</p><x-plan-status :plan="$plan" /></div>
                            <p class="text-xs muted">{{ $plan->configuration->name }} · {{ $plan->assignments_count }}/{{ $total }} postes</p>
                            <x-bar class="mt-1.5" :value="$total ? $plan->assignments_count / $total * 100 : 0" :color="$plan->boat->color()" />
                        </div>
                        <x-icon name="right" class="w-4 h-4 text-slate-400" />
                    </a>
                @empty
                    <p class="text-sm muted">Aucune yole engagée pour cette sortie.</p>
                    <a href="{{ route('outings.show', $current) }}" class="btn-ghost btn-sm mt-3"><x-icon name="plus" class="w-4 h-4" />Engager une yole</a>
                @endforelse
                @foreach ($unavailableBoats as $boat)
                    <div class="mt-3 rounded-xl bg-amber-50 text-amber-800 text-[13px] p-3 flex gap-2"><x-icon name="alert" class="w-4 h-4 mt-0.5" /><p><b>{{ $boat->name }}</b> indisponible pour les sorties.</p></div>
                @endforeach
            </section>
        @else
            <x-empty-state class="xl:col-span-3" title="Aucune sortie programmée" text="Créez votre première sortie pour faire l’appel et composer les équipages.">
                <a href="{{ route('outings.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle sortie</a>
            </x-empty-state>
        @endif
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-5 mt-5">
        <x-kpi label="Membres actifs" :value="$activeMembers" icon="users" />
        <x-kpi label="Présence (30 j)" :value="$rate30 !== null ? $rate30.' %' : '—'" sub="présents ou en retard" icon="chart" tone="green" />
        <x-kpi label="Sorties ce mois" :value="$outingsThisMonth" :sub="ucfirst(today()->translatedFormat('F Y'))" icon="calendar" tone="sky" />
        <x-kpi label="Prochaine régate"
               :value="$nextRegatta ? ($nextRegatta->isToday() ? 'Aujourd’hui' : 'J-'.(int) today()->diffInDays($nextRegatta->date)) : '—'"
               :sub="$nextRegatta ? $nextRegatta->title.' · '.$nextRegatta->date->translatedFormat('j M') : $racesThisSeason.' régate(s) cette saison'"
               icon="trophy" tone="sun" />
    </div>

    <div class="grid gap-5 xl:grid-cols-3 mt-5">
        <section class="card p-5 xl:col-span-2">
            <x-section-title title="Prochaines sorties">
                <a href="{{ route('outings.index') }}" class="text-sm font-semibold text-navy-700">Toutes les sorties</a>
            </x-section-title>
            <div class="divide-y divide-slate-100">
                @forelse ($upcoming as $outing)
                    <x-outing-row :outing="$outing" />
                @empty
                    <p class="text-sm muted py-3">Rien de prévu pour l’instant.</p>
                @endforelse
            </div>
        </section>
        <section class="card p-5">
            <x-section-title title="Présence — dernières sorties" />
            @if ($recentOutings->isEmpty())
                <p class="text-sm muted">Pas encore d’appel enregistré.</p>
            @else
                <div class="flex items-end gap-2 h-36 mt-2">
                    @foreach ($recentOutings as $row)
                        <a href="{{ route('outings.show', $row['outing']) }}" class="flex-1 h-full flex flex-col justify-end items-center gap-1.5" title="{{ $row['outing']->title }} · {{ $row['outing']->date->translatedFormat('j M') }}">
                            <span class="text-[10px] font-bold muted">{{ $row['rate'] }}</span>
                            <div @class(['w-full rounded-t-lg', 'bg-sun-400' => $loop->last, 'bg-navy-200' => ! $loop->last]) style="height: {{ max(2, $row['rate']) }}%"></div>
                        </a>
                    @endforeach
                </div>
                <div class="flex justify-between text-[10px] muted mt-2 font-semibold">
                    <span>{{ $recentOutings->first()['outing']->date->translatedFormat('j M') }}</span>
                    <span>{{ $recentOutings->last()['outing']->date->translatedFormat('j M') }}</span>
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
