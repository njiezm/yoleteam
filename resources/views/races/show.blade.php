@php
    $formatPoints = fn (?float $points) => $points === null ? '—' : rtrim(rtrim(number_format($points, 2, ',', ' '), '0'), ',');
    $openForm = (string) old('_form');
@endphp
<x-layouts.app :title="$race->name" :crumb="'Régates · '.$race->type->label().' '.$race->season" :back="route('races.index')">
    @can('manage')
        <x-slot:actions>
            <a href="{{ route('races.edit', $race) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="w-4 h-4" />Modifier</a>
            <a href="#gestion" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Saisir un résultat</a>
        </x-slot:actions>
        <x-slot:sticky>
            <div class="grid grid-cols-2 gap-2">
                <a href="{{ route('races.edit', $race) }}" class="btn-ghost"><x-icon name="edit" class="w-4 h-4" />Modifier</a>
                <a href="#gestion" class="btn-primary"><x-icon name="plus" class="w-4 h-4" />Résultats</a>
            </div>
        </x-slot:sticky>
    @endcan

    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm muted mb-5">
        <span class="chip bg-slate-100 text-slate-700">{{ $race->type->label() }}</span>
        <span class="inline-flex items-center gap-1.5"><x-icon name="calendar" class="w-4 h-4" />{{ $race->start_date->translatedFormat('j M Y') }}@if ($race->end_date && ! $race->end_date->isSameDay($race->start_date)) → {{ $race->end_date->translatedFormat('j M Y') }}@endif</span>
        @if ($race->location)
            <span class="inline-flex items-center gap-1.5"><x-icon name="pin" class="w-4 h-4" />{{ $race->location }}</span>
        @endif
    </div>
    @if ($race->notes)
        <p class="text-sm -mt-2 mb-5 whitespace-pre-line">{{ $race->notes }}</p>
    @endif

    @if ($boats->isNotEmpty())
        <div class="flex flex-wrap items-center gap-3 mb-5">
            <div class="seg max-w-full overflow-x-auto scrollbar-none">
                @foreach ($boats as $item)
                    <a href="{{ route('races.show', [$race, 'boat' => $item->id]) }}" @class(['whitespace-nowrap', 'on' => $boat->is($item)])>
                        <i class="w-2.5 h-2.5 rounded-full" style="background: {{ $item->color() }}"></i>{{ $item->name }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-5">
            <x-kpi label="Points cumulés" :value="$formatPoints($stats['points'])" :sub="$stats['sailed'].' étape(s) · '.$boat->name" icon="chart" />
            <x-kpi label="Meilleure étape"
                   :value="$stats['best'] ? $stats['best']->rank.'ᵉ' : '—'"
                   :sub="$stats['bestStage'] ? 'Étape '.$stats['bestStage']->number.($stats['bestStage']->end_location ? ' · '.$stats['bestStage']->end_location : '') : 'aucun classement'"
                   icon="star" tone="green" />
            <x-kpi label="Étapes courues" :value="$stats['sailed']" :sub="'sur '.$race->stages->count().' étape(s)'" icon="boat" tone="sky" />
            <x-kpi label="Abandons" :value="$stats['abandons']" :sub="$stats['disqualified'] ? $stats['disqualified'].' disqualification(s)' : 'aucune disqualification'" icon="alert" tone="sun" />
        </div>
    @endif

    <div @class(['grid gap-5 lg:grid-cols-[1fr_360px]', 'mt-5' => $boats->isNotEmpty()])>
        <section class="card overflow-hidden min-w-0">
            <div class="p-5 pb-3"><x-section-title :title="'Étapes & résultats'.($boat ? ' · '.$boat->name : '')" class="mb-0" /></div>
            @if ($race->stages->isEmpty())
                <p class="px-5 pb-5 text-sm muted">Aucune étape enregistrée pour l’instant.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px]">
                        <thead class="bg-slate-50">
                            <tr><th class="th">Étape</th><th class="th">Date</th><th class="th">Parcours</th><th class="th">Distance</th><th class="th">Temps</th><th class="th text-center">Rang</th><th class="th text-center">Pts</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as ['stage' => $stage, 'result' => $result])
                                <tr class="hover:bg-slate-50">
                                    <td class="td font-extrabold">{{ $stage->number }}</td>
                                    <td class="td text-sm whitespace-nowrap">{{ $stage->date->translatedFormat('j M') }}</td>
                                    <td class="td text-sm">
                                        <p class="font-semibold">
                                            @if ($stage->start_location || $stage->end_location)
                                                {{ $stage->start_location ?: '?' }} → {{ $stage->end_location ?: '?' }}
                                            @else
                                                {{ $stage->name }}
                                            @endif
                                        </p>
                                        @if ($result?->notes)
                                            <p class="text-xs muted">{{ $result->notes }}</p>
                                        @endif
                                    </td>
                                    <td class="td text-sm whitespace-nowrap">{{ $stage->distance_nm !== null ? $formatPoints((float) $stage->distance_nm).' M' : '—' }}</td>
                                    <td class="td text-sm tabular-nums">{{ $result?->elapsed_time ?? '—' }}</td>
                                    <td class="td text-center">
                                        @if ($result && ! $result->isClassified())
                                            <span class="chip {{ $result->status === \App\Enums\RaceResultStatus::Abandon ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700' }}">{{ $result->status->label() }}</span>
                                        @elseif ($result?->rank)
                                            <span class="inline-grid place-items-center w-8 h-8 rounded-full font-extrabold text-sm {{ $result->rank <= 3 ? 'bg-sun-400 text-navy-950' : 'bg-slate-100' }}">{{ $result->rank }}</span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="td text-center font-bold">{{ $result ? $formatPoints($result->points !== null ? (float) $result->points : null) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card p-5 self-start">
            <x-section-title title="Rang par étape" />
            @if ($chart)
                <svg viewBox="0 0 300 150" class="w-full" role="img" aria-label="Rang de {{ $boat->name }} par étape">
                    <g stroke="#E2E8F0">
                        @foreach ($chart['ticks'] as $tick)
                            <line x1="20" x2="295" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" />
                            <text x="0" y="{{ $tick['y'] + 3 }}" font-size="9" fill="#94A3B8" stroke="none">{{ $tick['rank'] }}</text>
                        @endforeach
                    </g>
                    @foreach ($chart['labels'] as $label)
                        <text x="{{ $label['x'] }}" y="145" font-size="9" fill="#94A3B8" text-anchor="middle">É{{ $label['number'] }}</text>
                    @endforeach
                    <polyline fill="none" stroke="#0B2545" stroke-width="2.5" stroke-linejoin="round" points="{{ collect($chart['points'])->map(fn ($point) => $point['x'].','.$point['y'])->join(' ') }}" />
                    @foreach ($chart['points'] as $point)
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4.5" fill="{{ $point['rank'] <= 3 ? '#F5B700' : '#0B2545' }}" stroke="#fff" stroke-width="2"><title>Étape {{ $point['number'] }} : {{ $point['rank'] }}ᵉ</title></circle>
                    @endforeach
                </svg>
                <p class="text-[11px] muted">Plus le point est haut, meilleur est le classement.</p>
            @else
                <p class="text-sm muted">Aucun classement saisi pour l’instant.</p>
            @endif
        </section>
    </div>

    @can('manage')
        <section class="mt-8 scroll-mt-24" id="gestion">
            <x-section-title title="Étapes & saisie des résultats" />
            <div class="space-y-3">
                @foreach ($race->stages as $stage)
                    @php
                        $stageKey = 'stage-'.$stage->id;
                        $resultsKey = 'results-'.$stage->id;
                        $resultsBag = $errors->getBag($resultsKey);
                        $byBoat = $stage->results->keyBy('boat_id');
                        $oldResult = fn (string $key, mixed $default = null) => $openForm === $resultsKey ? old($key, $default) : $default;
                    @endphp
                    <details class="card overflow-hidden group" @if (in_array($openForm, [$stageKey, $resultsKey], true)) open @endif>
                        <summary class="p-4 lg:px-5 flex items-center gap-3 cursor-pointer list-none">
                            <span class="w-9 h-9 rounded-xl bg-navy-50 text-navy-800 grid place-items-center font-extrabold text-sm shrink-0">{{ $stage->number }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold truncate">{{ $stage->name }}</p>
                                <p class="text-xs muted">{{ ucfirst($stage->date->translatedFormat('l j F Y')) }} · {{ $stage->results->count() }} résultat(s)</p>
                            </div>
                            <x-icon name="right" class="w-4 h-4 text-slate-400 transition group-open:rotate-90" />
                        </summary>
                        <div class="border-t border-slate-100 p-4 lg:p-5 space-y-6">
                            <form method="POST" action="{{ route('races.stages.results.update', [$race, $stage]) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_form" value="{{ $resultsKey }}">
                                <p class="font-bold mb-1">Résultats</p>
                                <p class="text-xs muted mb-3">Temps au format H:MM:SS. Une ligne laissée vide supprime le résultat de la yole.</p>
                                @if ($resultBoats->isEmpty())
                                    <p class="text-sm muted">Aucune yole active dans l’association.</p>
                                @else
                                    <div class="overflow-x-auto -mx-4 lg:-mx-5">
                                        <table class="w-full min-w-[720px]">
                                            <thead class="bg-slate-50">
                                                <tr><th class="th">Yole</th><th class="th w-24">Rang</th><th class="th w-32">Temps</th><th class="th w-24">Points</th><th class="th w-40">Statut</th><th class="th">Notes</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($resultBoats as $resultBoat)
                                                    @php
                                                        $existing = $byBoat->get($resultBoat->id);
                                                        $field = fn (string $name) => "results[{$resultBoat->id}][{$name}]";
                                                        $key = fn (string $name) => "results.{$resultBoat->id}.{$name}";
                                                        $rowErrors = collect(['rank', 'time', 'points', 'status', 'notes'])->map(fn ($name) => $resultsBag->first($key($name)))->filter();
                                                        $status = (string) $oldResult($key('status'), $existing?->status?->value ?? \App\Enums\RaceResultStatus::Classe->value);
                                                    @endphp
                                                    <tr>
                                                        <td class="td font-semibold whitespace-nowrap"><span class="inline-flex items-center gap-2"><i class="w-2.5 h-2.5 rounded-full" style="background: {{ $resultBoat->color() }}"></i>{{ $resultBoat->name }}</span></td>
                                                        <td class="td"><input name="{{ $field('rank') }}" type="number" min="1" max="999" value="{{ $oldResult($key('rank'), $existing?->rank) }}" @class(['input h-10', 'input-error' => $resultsBag->has($key('rank'))]) aria-label="Rang {{ $resultBoat->name }}"></td>
                                                        <td class="td"><input name="{{ $field('time') }}" value="{{ $oldResult($key('time'), $existing?->elapsed_time) }}" placeholder="1:04:30" inputmode="numeric" @class(['input h-10 tabular-nums', 'input-error' => $resultsBag->has($key('time'))]) aria-label="Temps {{ $resultBoat->name }}"></td>
                                                        <td class="td"><input name="{{ $field('points') }}" type="number" min="0" max="9999" step="0.01" value="{{ $oldResult($key('points'), $existing?->points !== null ? (float) $existing->points : null) }}" @class(['input h-10', 'input-error' => $resultsBag->has($key('points'))]) aria-label="Points {{ $resultBoat->name }}"></td>
                                                        <td class="td">
                                                            <select name="{{ $field('status') }}" @class(['input h-10', 'input-error' => $resultsBag->has($key('status'))]) aria-label="Statut {{ $resultBoat->name }}">
                                                                @foreach (\App\Enums\RaceResultStatus::options() as $value => $label)
                                                                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td class="td"><input name="{{ $field('notes') }}" value="{{ $oldResult($key('notes'), $existing?->notes) }}" maxlength="500" @class(['input h-10', 'input-error' => $resultsBag->has($key('notes'))]) aria-label="Notes {{ $resultBoat->name }}"></td>
                                                    </tr>
                                                    @if ($rowErrors->isNotEmpty())
                                                        <tr><td colspan="6" class="px-4 pb-3 text-xs text-red-600 font-semibold">{{ $rowErrors->join(' ') }}</td></tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <button class="btn-primary btn-sm mt-4">Enregistrer les résultats</button>
                                @endif
                            </form>

                            <form method="POST" action="{{ route('races.stages.update', [$race, $stage]) }}" class="pt-5 border-t border-slate-100">
                                @csrf
                                @method('PUT')
                                <p class="font-bold mb-3">Étape</p>
                                @include('races._stage-fields', ['stage' => $stage, 'formKey' => $stageKey, 'bag' => $errors->getBag($stageKey), 'id' => $stageKey, 'defaultNumber' => $stage->number])
                                <button class="btn-ghost btn-sm mt-4">Enregistrer l’étape</button>
                            </form>

                            <form method="POST" action="{{ route('races.stages.destroy', [$race, $stage]) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Supprimer l’étape {{ $stage->number }} et ses résultats ?">
                                @csrf
                                @method('DELETE')
                                <button class="btn-danger btn-sm"><x-icon name="trash" class="w-4 h-4" />Supprimer l’étape</button>
                            </form>
                        </div>
                    </details>
                @endforeach

                <form method="POST" action="{{ route('races.stages.store', $race) }}" class="card p-4 lg:p-5">
                    @csrf
                    <p class="font-bold mb-3">Ajouter une étape</p>
                    @include('races._stage-fields', ['stage' => null, 'formKey' => 'new-stage', 'bag' => $errors->getBag('newStage'), 'id' => 'new-stage', 'defaultNumber' => ($race->stages->max('number') ?? 0) + 1])
                    <button class="btn-primary btn-sm mt-4"><x-icon name="plus" class="w-4 h-4" />Ajouter l’étape</button>
                </form>
            </div>
        </section>
    @endcan
</x-layouts.app>
