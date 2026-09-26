<?php

namespace App\Http\Controllers;

use App\Enums\OutingStatus;
use App\Models\Outing;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutingCalendarController extends Controller
{
    private const VIEWS = ['semaine', 'mois', 'annee'];

    /**
     * Printable calendar of the outings (week, month or year) — the browser saves it as a PDF.
     */
    public function __invoke(Request $request): View
    {
        $view = in_array($request->query('vue'), self::VIEWS, true) ? $request->query('vue') : 'mois';
        $date = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('date')), CarbonImmutable::today(), false);

        [$start, $end, $previous, $next, $title] = match ($view) {
            'semaine' => [
                $date->startOfWeek(), $date->endOfWeek(), $date->subWeek(), $date->addWeek(),
                'Semaine du '.$date->startOfWeek()->translatedFormat('j F').' au '.$date->endOfWeek()->translatedFormat('j F Y'),
            ],
            'annee' => [$date->startOfYear(), $date->endOfYear(), $date->subYear(), $date->addYear(), 'Année '.$date->year],
            default => [$date->startOfMonth(), $date->endOfMonth(), $date->subMonthNoOverflow(), $date->addMonthNoOverflow(), ucfirst($date->translatedFormat('F Y'))],
        };

        $outings = Outing::query()
            ->forAssociation($request->user()->association_id)
            ->where('status', '!=', OutingStatus::Annulee)
            ->whereBetween('date', [
                $view === 'mois' ? $start->startOfWeek() : $start,
                $view === 'mois' ? $end->endOfWeek() : $end,
            ])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return view('outings.calendar', [
            'view' => $view,
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'title' => $title,
            'previous' => $previous->toDateString(),
            'next' => $next->toDateString(),
            'outingsByDay' => $outings->groupBy(fn (Outing $outing) => $outing->date->toDateString()),
            'outingsByMonth' => $outings->groupBy(fn (Outing $outing) => $outing->date->month),
            'associationName' => $request->user()->association->name,
        ]);
    }
}
