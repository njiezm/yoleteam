{{-- Outing page: results of a race (manches, combi, rankings) or of a TDY stage (stage and general rankings). --}}
@php
    $isRace = $outing->type === \App\Enums\OutingType::Regate;
    $places = collect(range(1, \App\Enums\RaceOutcome::MAX_PLACE))->mapWithKeys(fn (int $place) => [$place => $place === 1 ? '1ᵉʳ' : $place.'ᵉ'])->all();
    $raceRows = old('races') !== null
        ? collect(old('races'))->filter(fn ($row) => is_array($row))
        : $outing->races->values()->map(fn ($race) => ['place' => $race->place, 'result' => $race->result->value, 'points' => $race->result->hasTypedPoints() ? $race->points : null]);
    $blankIndex = $raceRows->keys()->filter(fn ($key) => is_numeric($key))->map(fn ($key) => (int) $key)->max() ?? -1;
    $blankIndex++;
    $combi = $outing->combiPoints();
@endphp
<section class="card p-5 mt-5 scroll-mt-24" id="resultats">
    <x-section-title title="Résultats">
        <span class="chip bg-sun-100 text-amber-800"><x-icon name="trophy" class="w-3.5 h-3.5" />{{ $outing->type->label() }}</span>
    </x-section-title>

    <form method="POST" action="{{ route('outings.results.update', $outing) }}" data-results-form
          data-offline-form="Résultats : {{ $outing->title }} · {{ $outing->date->format('d/m/Y') }}" data-offline-redirect="{{ route('outings.show', $outing) }}">
        @csrf
        @method('PUT')
        @error('races')<p class="text-sm text-red-600 font-semibold mb-3">{{ $message }}</p>@enderror

        @if ($isRace)
            <p class="text-xs muted mb-3">Classé : points = place · Coulé (C) et avarie (A) : {{ \App\Enums\RaceOutcome::PENALTY_POINTS }} points · Disqualifié (D) : points à saisir. Le combi additionne les points des courses de la journée (le plus bas l’emporte).</p>
            <div class="overflow-x-auto -mx-5">
                <table class="w-full min-w-[560px]">
                    <thead class="bg-slate-50">
                        <tr><th class="th w-12">N°</th><th class="th w-28">Place</th><th class="th">Résultat</th><th class="th w-28">Points</th><th class="th w-16 text-right"><span class="sr-only">Retirer</span></th></tr>
                    </thead>
                    <tbody data-race-rows>
                        @foreach ($raceRows as $index => $row)
                            @include('outings._race-row', ['index' => $index, 'row' => $row, 'number' => $loop->iteration])
                        @endforeach
                        @include('outings._race-row', ['index' => $blankIndex, 'row' => [], 'number' => $raceRows->count() + 1])
                    </tbody>
                    <tfoot>
                        <tr class="bg-navy-50/60">
                            <td class="td font-bold" colspan="3">Combi <span class="text-xs muted font-semibold">(total de la journée)</span></td>
                            <td class="td text-lg font-extrabold tabular-nums" data-combi>{{ $combi ?? '—' }}</td>
                            <td class="td"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <button type="button" class="btn-ghost btn-sm mt-3 hidden" data-add-race><x-icon name="plus" class="w-4 h-4" />Ajouter une course</button>
            <p class="text-[11px] muted mt-2">Une ligne sans place (et classée) est ignorée ; cochez la corbeille pour retirer une course.</p>

            <template data-race-template>
                @include('outings._race-row', ['index' => '__INDEX__', 'row' => [], 'number' => ''])
            </template>
        @endif

        <div class="grid sm:grid-cols-2 gap-4 mt-5">
            @if ($isRace)
                <x-field label="Place de la journée" name="day_rank">
                    <x-select name="day_rank" :options="$places" :value="$outing->day_rank" placeholder="—" />
                </x-field>
            @else
                <x-field label="Classement de l’étape" name="stage_rank">
                    <x-select name="stage_rank" :options="$places" :value="$outing->stage_rank" placeholder="—" />
                </x-field>
            @endif
            <x-field label="Classement général" name="general_rank">
                <x-select name="general_rank" :options="$places" :value="$outing->general_rank" placeholder="—" />
            </x-field>
        </div>

        <button class="btn-primary btn-sm mt-5"><x-icon name="check" class="w-4 h-4" />Enregistrer les résultats</button>

        @if ($isRace)
            <style>
                [data-race-row]:not(:has([data-outcome="disqualifie"]:checked)) [data-typed-points] { display: none; }
                [data-race-row]:has([data-outcome="disqualifie"]:checked) [data-computed-points] { display: none; }
                [data-race-row]:has([data-race-remove]:checked) > td:not(:last-child) { opacity: .4; }
            </style>
            <script>
                (function (form) {
                    const penalty = {{ \App\Enums\RaceOutcome::PENALTY_POINTS }};
                    const body = form.querySelector('[data-race-rows]');
                    const template = form.querySelector('[data-race-template]');
                    const addButton = form.querySelector('[data-add-race]');
                    let nextIndex = {{ $blankIndex + 1 }};

                    const refresh = () => {
                        let total = 0;
                        let counted = 0;
                        let number = 0;
                        body.querySelectorAll('[data-race-row]').forEach((row) => {
                            const outcome = row.querySelector('[data-outcome]:checked')?.value ?? 'classe';
                            const place = row.querySelector('[data-place]').value;
                            const typed = row.querySelector('[data-typed-points] input').value;
                            const removed = row.querySelector('[data-race-remove]').checked;
                            const points = outcome === 'classe' ? (place === '' ? null : Number(place))
                                : outcome === 'disqualifie' ? (typed === '' ? null : Number(typed)) : penalty;
                            row.querySelector('[data-computed-points]').textContent = points ?? '—';
                            row.querySelector('[data-race-number]').textContent = removed ? '—' : ++number;
                            if (!removed && points !== null) { total += points; counted++; }
                        });
                        form.querySelector('[data-combi]').textContent = counted ? total : '—';
                    };

                    addButton.classList.remove('hidden');
                    addButton.addEventListener('click', () => {
                        body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
                        refresh();
                    });
                    form.addEventListener('input', refresh);
                    form.addEventListener('change', refresh);
                    refresh();
                })(document.currentScript.closest('form'));
            </script>
        @endif
    </form>
</section>
