<x-layouts.app :title="$outing->title" :crumb="'Sorties · '.ucfirst($outing->date->translatedFormat('l j F Y'))" :back="route('outings.index')">
    <x-slot:actions>
        <a href="{{ route('outings.edit', $outing) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="w-4 h-4" />Modifier</a>
        <a href="#appel" class="btn-sun btn-sm"><x-icon name="check-square" class="w-4 h-4" />Faire l’appel</a>
    </x-slot:actions>

    @php
        $statuses = \App\Enums\AttendanceStatus::cases();
        $isRace = $outing->type === \App\Enums\OutingType::Regate;
        $onSite = $counts['present'] + $counts['retard'];
        $toRecord = max(0, $members->count() - $attendances->count());
        $plansByRace = $outing->crewPlans->groupBy('race_number');
        $validated = $outing->crewPlans->filter->isValidated()->count();
        $completed = $outing->status === \App\Enums\OutingStatus::Terminee;
        $hasNavigation = $outing->impressions || $outing->distance_nm !== null || $outing->end_time;
        $steps = array_values(array_filter([
            ['appel', 'Appel', $counts['total'] > 0, $counts['total'] ? "$onSite présent(s)" : 'À faire'],
            ['equipages', 'Équipages', $outing->crewPlans->isNotEmpty() && $validated === $outing->crewPlans->count(), $outing->crewPlans->isEmpty() ? 'Aucune yole' : "$validated/{$outing->crewPlans->count()} validé(s)"],
            ['navigation', 'Navigation', (bool) $hasNavigation, $hasNavigation ? 'Saisie' : 'Après la sortie'],
            $outing->type->hasResults() ? ['resultats', 'Résultats', $outing->races->isNotEmpty() || $outing->day_rank || $outing->stage_rank, $outing->races->isNotEmpty() ? $outing->races->count().' course(s)' : 'À saisir'] : null,
            ['validation', 'Validation', $completed, $completed ? 'Validée' : 'À la fin'],
        ]));
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
        @if ($outing->creator)
            <span class="text-sm font-semibold flex items-center gap-1.5 text-slate-700"><x-icon name="user" class="w-4 h-4 text-slate-400" />{{ $outing->creator->name }}</span>
        @endif
        @if ($outing->notes)
            <div class="basis-full pt-3 border-t border-slate-100">
                <p class="text-[11px] font-bold uppercase muted mb-1">Consignes</p>
                <p class="text-sm text-slate-700 whitespace-pre-line">{{ $outing->notes }}</p>
            </div>
        @endif
    </div>

    {{-- Steps of the outing, in order: the page scrolls from one to the next. --}}
    <nav class="mt-4 -mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto scrollbar-none" aria-label="Étapes de la sortie">
        <ol class="flex gap-2 min-w-max sm:min-w-0">
            @foreach ($steps as $index => [$anchor, $label, $done, $summary])
                <li class="sm:flex-1">
                    <a href="#{{ $anchor }}" @class(['flex items-center gap-2 rounded-xl px-3 py-2 border', 'bg-emerald-50 border-emerald-200' => $done, 'bg-white border-slate-200 hover:bg-slate-50' => ! $done])>
                        <span @class(['w-7 h-7 shrink-0 rounded-full grid place-items-center text-xs font-extrabold', 'bg-emerald-500 text-white' => $done, 'bg-slate-100 text-navy-900' => ! $done])>
                            @if ($done)<x-icon name="check" class="w-3.5 h-3.5" />@else{{ $index + 1 }}@endif
                        </span>
                        <span class="min-w-0"><span class="block text-sm font-bold leading-tight">{{ $label }}</span><span class="block text-[11px] muted leading-tight truncate">{{ $summary }}</span></span>
                    </a>
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- 1. Appel --}}
    <section id="appel" class="mt-5 scroll-mt-24">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="font-extrabold text-lg">1. Appel</h2>
            <div class="flex gap-2">
                <a href="{{ route('attendance.edit', $outing) }}" class="btn-ghost btn-sm hidden sm:inline-flex" title="Ouvrir l’appel en plein écran"><x-icon name="check-square" class="w-4 h-4" />Plein écran</a>
                <button type="button" class="btn-ghost btn-sm" data-all-present><x-icon name="check" class="w-4 h-4" />Tous présents</button>
            </div>
        </div>
        <div data-attendance data-url="{{ route('attendance.update', $outing) }}" data-outing-uuid="{{ $outing->uuid }}" data-outing-label="{{ $outing->title }} · {{ $outing->date->translatedFormat('j M') }}">
            <div class="grid grid-cols-5 gap-2">
                @foreach ($statuses as $status)
                    <div class="rounded-xl p-2 sm:p-2.5 text-center min-w-0" style="background: {{ $status->background() }}; color: {{ $status->textColor() }}">
                        <p class="text-lg sm:text-xl font-extrabold" data-count="{{ $status->value }}">{{ $counts[$status->value] }}</p>
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wide truncate">{{ $status->label() }}</p>
                    </div>
                @endforeach
                <div class="rounded-xl p-2 sm:p-2.5 text-center bg-slate-100 min-w-0">
                    <p class="text-lg sm:text-xl font-extrabold text-slate-600" data-count="none">{{ $toRecord }}</p>
                    <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wide text-slate-500 truncate">À pointer</p>
                </div>
            </div>
            <label class="relative block mt-4">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><x-icon name="search" class="w-4 h-4" /></span>
                <input type="search" data-search class="input pl-9" placeholder="Rechercher un membre…" aria-label="Rechercher un membre">
            </label>
            <form id="attendance-form" method="POST" action="{{ route('attendance.update', $outing) }}">
                @csrf
                @method('PUT')
                <div class="card mt-4 divide-y divide-slate-100 overflow-hidden">
                    @include('attendance._rows')
                </div>
            </form>
            <p class="text-xs muted text-center mt-3" data-save-state>Chaque clic est enregistré immédiatement.</p>
        </div>
        <div class="flex justify-center mt-3">
            <a href="#equipages" class="btn-primary btn-sm">Appel terminé · composer les équipages <x-icon name="right" class="w-4 h-4 rotate-90" /></a>
        </div>
    </section>

    {{-- 2. Équipages --}}
    <section id="equipages" class="mt-8 scroll-mt-24">
        <div class="mb-3">
            <h2 class="font-extrabold text-lg">2. Équipages</h2>
            @if ($isRace)
                <p class="text-xs muted">Un équipage par manche : il peut changer d’une manche à l’autre.</p>
            @endif
        </div>
        @if ($outing->crewPlans->count() > 1)
            {{-- Several boats: compose them together (tabs, shared pool of rowers) and see them side by side. --}}
            <div class="card p-3 mb-4 flex flex-wrap items-center gap-2">
                <a href="{{ route('crew-plans.edit', [$outing, $outing->crewPlans->first()]) }}" class="btn-primary btn-sm"><x-icon name="edit" class="w-4 h-4" />Composer les {{ $outing->crewPlans->where('race_number', $outing->crewPlans->first()->race_number)->count() }} équipages ensemble</a>
                <a href="{{ route('crew-plans.index', $outing) }}" class="btn-ghost btn-sm"><x-icon name="boat" class="w-4 h-4" />Voir tous les plans</a>
                <form method="POST" action="{{ route('outings.share-crew.update', $outing) }}" class="sm:ml-auto"
                      data-offline-form="Cadenas des coursiers : {{ $outing->share_crew ? 'fermé' : 'ouvert' }}" data-offline-redirect="{{ route('outings.show', $outing) }}#equipages">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="share_crew" value="{{ $outing->share_crew ? 0 : 1 }}">
                    <button @class(['btn btn-sm', 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' => $outing->share_crew, 'bg-slate-100 text-slate-700 hover:bg-slate-200' => ! $outing->share_crew])
                            title="{{ $outing->share_crew ? 'Fermer : chaque coursier sur une seule yole' : 'Ouvrir : un coursier peut être placé sur plusieurs yoles' }}">
                        <x-icon :name="$outing->share_crew ? 'unlock' : 'lock'" class="w-4 h-4" />{{ $outing->share_crew ? 'Coursiers réutilisables' : 'Un coursier = une yole' }}
                    </button>
                </form>
            </div>
        @endif
        <div class="space-y-5" data-outing-plans data-outing-uuid="{{ $outing->uuid }}">
            <div class="hidden space-y-3" data-offline-plans></div>

            @foreach ($plansByRace as $raceNumber => $plans)
                <div>
                    @if ($isRace)
                        <p class="text-[11px] font-bold uppercase muted mb-2">Manche {{ $raceNumber }}</p>
                    @endif
                    <div class="grid gap-3 lg:grid-cols-2">
                        @foreach ($plans as $plan)
                            @include('outings._plan-card')
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($nextRaces->isNotEmpty())
                <div class="grid gap-3 lg:grid-cols-2">
                    @foreach ($nextRaces as $last)
                        <form method="POST" action="{{ route('crew-plans.store', $outing) }}" data-plan-create data-template="{{ $last->boat_id }}:{{ $last->race_number + 1 }}"
                              class="card p-4 border-dashed border-2 border-slate-300 bg-slate-50/50 flex flex-wrap items-center gap-3">
                            @csrf
                            <input type="hidden" name="boat_id" value="{{ $last->boat_id }}">
                            <input type="hidden" name="race_number" value="{{ $last->race_number + 1 }}">
                            <input type="hidden" name="copy_from" value="{{ $last->id }}">
                            <div class="flex-1 min-w-[10rem]">
                                <p class="font-bold flex items-center gap-2"><x-icon name="plus" class="w-4 h-4" />{{ $last->boat->name }} · manche {{ $last->race_number + 1 }}</p>
                                <p class="text-xs muted">Reprend l’équipage de la manche {{ $last->race_number }}, à ajuster.</p>
                            </div>
                            <button class="btn-primary btn-sm" data-submit-once>Préparer la manche {{ $last->race_number + 1 }}</button>
                        </form>
                    @endforeach
                </div>
            @endif

            @if ($availableBoats->isNotEmpty())
                <form method="POST" action="{{ route('crew-plans.store', $outing) }}" data-plan-create class="card p-4 border-dashed border-2 border-slate-300 bg-slate-50/50 flex flex-col gap-3">
                    @csrf
                    <p class="font-bold flex items-center gap-2"><x-icon name="plus" class="w-4 h-4" />Engager une yole</p>
                    <div class="flex flex-wrap gap-2">
                        <select name="boat_id" class="input flex-1 min-w-40" aria-label="Yole">
                            @foreach ($availableBoats as $boat)
                                <option value="{{ $boat->id }}">{{ $boat->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn-primary" data-submit-once>Créer le plan</button>
                    </div>
                    @error('boat_id')<p class="text-xs text-red-600 font-semibold">{{ $message }}</p>@enderror
                    <p class="text-xs muted">La configuration par défaut de la yole est utilisée ; vous pourrez passer de 1 à 2 voiles dans l’éditeur.</p>
                </form>
            @elseif ($outing->crewPlans->isEmpty())
                <x-empty-state icon="boat" title="Aucune yole disponible" text="Ajoutez une yole ou remettez-en une en service pour composer un équipage." />
            @endif
        </div>
    </section>

    {{-- 3. Navigation --}}
    <div class="mt-8">
        @include('outings._navigation', ['number' => 3])
    </div>

    {{-- 4. Résultats (courses et TDY) --}}
    @if ($outing->type->hasResults())
        @include('outings._results-card', ['number' => 4])
    @endif

    {{-- Fin : valider la sortie (reste modifiable). --}}
    <section id="validation" class="mt-8 scroll-mt-24">
        @if ($completed)
            <div class="card p-5 flex flex-wrap items-center gap-4 bg-emerald-50 border-emerald-200">
                <span class="w-11 h-11 rounded-2xl bg-emerald-500 text-white grid place-items-center"><x-icon name="check" /></span>
                <div class="flex-1 min-w-[12rem]">
                    <p class="font-extrabold">Sortie validée</p>
                    <p class="text-sm text-emerald-900">Tout reste modifiable : appel, équipages, navigation{{ $outing->type->hasResults() ? ', résultats' : '' }}.</p>
                </div>
                <form method="POST" action="{{ route('outings.reopen', $outing->uuid) }}" data-offline-form="Sortie rouverte : {{ $outing->title }}" data-offline-redirect="{{ route('outings.show', $outing) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn-ghost btn-sm">Rouvrir</button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('outings.complete', $outing->uuid) }}" class="card p-5 flex flex-wrap items-center gap-4"
                  data-offline-form="Sortie validée : {{ $outing->title }}" data-offline-redirect="{{ route('outings.show', $outing) }}">
                @csrf
                <div class="flex-1 min-w-[12rem]">
                    <p class="font-extrabold">Valider la sortie</p>
                    <p class="text-sm muted">Quand l’appel, les équipages et la navigation sont faits. Vous pourrez encore tout modifier ensuite.</p>
                </div>
                <button class="btn-sun w-full sm:w-auto" data-submit-once><x-icon name="check" class="w-4 h-4" />Valider la sortie</button>
            </form>
        @endif
    </section>

    <x-delete-zone :action="route('outings.destroy', $outing)" label="Supprimer la sortie"
                   :confirm="'Supprimer la sortie « '.$outing->title.' », son appel et ses plans d’équipage ?'"
                   hint="L’appel et les plans d’équipage de cette sortie seront supprimés. Pour garder l’historique, passez-la plutôt en « Annulée »." />

    @if ($planTemplates->isNotEmpty())
        {{-- Offline: "Créer le plan" opens the editor right here, the plan is created on the server at sync. --}}
        <script type="application/json" data-plan-templates>@json($planTemplates, JSON_UNESCAPED_UNICODE)</script>
        <template data-offline-editor>
            @include('crew-plans._editor', ['editor' => null, 'plan' => null, 'inline' => true])
        </template>
        <div class="hidden" data-offline-editor-host></div>
    @endif
</x-layouts.app>
