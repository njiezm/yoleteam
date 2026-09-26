<?php

namespace App\Http\Controllers;

use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Models\Outing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatisticsController extends Controller
{
    /**
     * Results of the season: race days (manches, combi, rankings) and TDY stages.
     */
    public function index(Request $request): View
    {
        $associationId = $request->user()->association_id;
        $resultTypes = [OutingType::Regate, OutingType::Tdy];

        $seasons = Outing::query()
            ->forAssociation($associationId)
            ->whereIn('type', $resultTypes)
            ->pluck('date')
            ->map(fn ($date) => $date->year)
            ->push(today()->year)
            ->unique()
            ->sortDesc()
            ->values();

        $season = $seasons->contains((int) $request->query('saison')) ? (int) $request->query('saison') : today()->year;
        $type = OutingType::tryFrom((string) $request->query('type'));
        $types = $type && $type->hasResults() ? [$type] : $resultTypes;

        $outings = Outing::query()
            ->forAssociation($associationId)
            ->whereIn('type', $types)
            ->where('status', '!=', OutingStatus::Annulee)
            ->whereYear('date', $season)
            ->with('races')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $raceDays = $outings->where('type', OutingType::Regate)->values();
        $tdyStages = $outings->where('type', OutingType::Tdy)->values();
        $combis = $raceDays->map(fn (Outing $outing) => $outing->combiPoints())->filter(fn (?int $combi) => $combi !== null);

        return view('statistics.index', [
            'seasons' => $seasons,
            'filters' => ['saison' => $season, 'type' => $type && $type->hasResults() ? $type->value : ''],
            'raceDays' => $raceDays,
            'tdyStages' => $tdyStages,
            'kpis' => [
                'races' => $raceDays->sum(fn (Outing $outing) => $outing->races->count()),
                'raceDaysWithResults' => $raceDays->filter(fn (Outing $outing) => $outing->races->isNotEmpty())->count(),
                'bestDayRank' => $raceDays->whereNotNull('day_rank')->min('day_rank'),
                'averageCombi' => $combis->isEmpty() ? null : round($combis->avg(), 1),
                'bestGeneralRank' => $outings->whereNotNull('general_rank')->min('general_rank'),
            ],
        ]);
    }
}
