@php
    $rank = fn (?int $value) => $value === null ? '—' : ($value === 1 ? '1ᵉʳ' : $value.'ᵉ');
    $rankBadge = fn (?int $value) => $value !== null && $value <= 3 ? 'bg-sun-400 text-navy-950' : 'bg-slate-100 text-navy-950';
    $showRaces = $filters['type'] !== \App\Enums\OutingType::Tdy->value;
    $showTdy = $filters['type'] !== \App\Enums\OutingType::Regate->value;
@endphp
<x-layouts.app title="Statistiques" :crumb="'Résultats · saison '.$filters['saison']">
    <form method="GET" action="{{ route('statistics.index') }}" class="flex flex-wrap gap-2">
        <select name="saison" class="input w-36" aria-label="Saison" onchange="this.form.submit()">
            @foreach ($seasons as $season)
                <option value="{{ $season }}" @selected($filters['saison'] === $season)>Saison {{ $season }}</option>
            @endforeach
        </select>
        <select name="type" class="input w-48" aria-label="Type de sortie" onchange="this.form.submit()">
            <option value="">Courses et TDY</option>
            <option value="{{ \App\Enums\OutingType::Regate->value }}" @selected($filters['type'] === \App\Enums\OutingType::Regate->value)>Courses</option>
            <option value="{{ \App\Enums\OutingType::Tdy->value }}" @selected($filters['type'] === \App\Enums\OutingType::Tdy->value)>TDY</option>
        </select>
        <button class="btn-ghost" title="Filtrer"><x-icon name="filter" class="w-4 h-4" /><span class="sr-only">Filtrer</span></button>
    </form>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-5 mt-5">
        <x-kpi label="Courses disputées" :value="$kpis['races']" :sub="$kpis['raceDaysWithResults'].' journée'.($kpis['raceDaysWithResults'] > 1 ? 's' : '').' de course'" icon="boat" />
        <x-kpi label="Meilleure place de journée" :value="$rank($kpis['bestDayRank'])" sub="sur la saison" icon="star" tone="sun" />
        <x-kpi label="Moyenne combi" :value="$kpis['averageCombi'] !== null ? number_format($kpis['averageCombi'], 1, ',', ' ') : '—'" sub="points par journée (le plus bas l’emporte)" icon="chart" tone="sky" />
        <x-kpi label="Meilleur classement général" :value="$rank($kpis['bestGeneralRank'])" sub="courses et TDY" icon="trophy" tone="green" />
    </div>

    @if ($showRaces)
        <section class="card mt-5 overflow-hidden">
            <div class="p-5 pb-3"><x-section-title title="Courses" class="mb-0" /></div>
            @if ($raceDays->isEmpty())
                <p class="px-5 pb-5 text-sm muted">Aucune sortie de type Course sur la saison {{ $filters['saison'] }}.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px]">
                        <thead class="bg-slate-50">
                            <tr><th class="th">Date</th><th class="th">Sortie</th><th class="th">Manches</th><th class="th text-center">Combi</th><th class="th text-center">Journée</th><th class="th text-center">Général</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($raceDays as $outing)
                                <tr class="hover:bg-slate-50">
                                    <td class="td whitespace-nowrap">{{ $outing->date->translatedFormat('j M') }}</td>
                                    <td class="td"><a href="{{ route('outings.show', $outing) }}#resultats" class="font-semibold text-navy-700 hover:underline">{{ $outing->title }}</a></td>
                                    <td class="td">
                                        @forelse ($outing->races as $race)
                                            <span title="Course {{ $race->number }} · {{ $race->result->label() }} · {{ $race->points ?? '—' }} pt(s)" @class([
                                                'chip py-0.5 mr-1 mb-1',
                                                'bg-slate-100 text-slate-700' => $race->result === \App\Enums\RaceOutcome::Classe,
                                                'bg-red-100 text-red-700' => $race->result !== \App\Enums\RaceOutcome::Classe,
                                            ])>{{ $race->summary() }}</span>
                                        @empty
                                            <span class="text-slate-300">—</span>
                                        @endforelse
                                    </td>
                                    <td class="td text-center font-extrabold tabular-nums">{{ $outing->combiPoints() ?? '—' }}</td>
                                    <td class="td text-center"><span class="inline-grid place-items-center min-w-8 h-8 px-1 rounded-full font-extrabold text-sm {{ $rankBadge($outing->day_rank) }}">{{ $rank($outing->day_rank) }}</span></td>
                                    <td class="td text-center font-bold">{{ $rank($outing->general_rank) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if ($showTdy)
        <section class="card mt-5 overflow-hidden">
            <div class="p-5 pb-3"><x-section-title title="Tour des yoles (TDY)" class="mb-0" /></div>
            @if ($tdyStages->isEmpty())
                <p class="px-5 pb-5 text-sm muted">Aucune étape du TDY sur la saison {{ $filters['saison'] }}.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px]">
                        <thead class="bg-slate-50">
                            <tr><th class="th">Date</th><th class="th">Étape</th><th class="th text-center">Classement étape</th><th class="th text-center">Général</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($tdyStages as $outing)
                                <tr class="hover:bg-slate-50">
                                    <td class="td whitespace-nowrap">{{ $outing->date->translatedFormat('j M') }}</td>
                                    <td class="td"><a href="{{ route('outings.show', $outing) }}#resultats" class="font-semibold text-navy-700 hover:underline">{{ $outing->title }}</a></td>
                                    <td class="td text-center"><span class="inline-grid place-items-center min-w-8 h-8 px-1 rounded-full font-extrabold text-sm {{ $rankBadge($outing->stage_rank) }}">{{ $rank($outing->stage_rank) }}</span></td>
                                    <td class="td text-center font-bold">{{ $rank($outing->general_rank) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if ($raceDays->isEmpty() && $tdyStages->isEmpty())
        <x-empty-state class="mt-5" icon="trophy" title="Aucun résultat pour cette saison" text="Créez une sortie de type Course ou TDY, puis saisissez les places et classements depuis la page de la sortie.">
            <a href="{{ route('outings.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle sortie</a>
        </x-empty-state>
    @endif
</x-layouts.app>
