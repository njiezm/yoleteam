<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoatRequest;
use App\Models\Boat;
use App\Models\BoatConfiguration;
use App\Models\CrewAssignment;
use App\Models\CrewPlan;
use App\Services\BoatLayoutGenerator;
use App\Services\CrewPlanPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BoatController extends Controller
{
    /** Configurations created with every new boat. */
    public const DEFAULT_CONFIGURATIONS = [
        ['name' => '1 voile (misaine)', 'sail_count' => 1, 'bwa_count' => 9, 'cordes_count' => 0, 'ecoute_count' => 2, 'pagaie_count' => 2, 'is_default' => false],
        ['name' => '2 voiles', 'sail_count' => 2, 'bwa_count' => 8, 'cordes_count' => 2, 'ecoute_count' => 4, 'pagaie_count' => 2, 'is_default' => true],
    ];

    public function index(Request $request, CrewPlanPresenter $presenter): View
    {
        $boats = Boat::query()
            ->forAssociation($request->user()->association_id)
            ->with(['configurations' => $this->orderedConfigurations(...), 'configurations.positions.crewRole'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $drawings = $boats->mapWithKeys(function (Boat $boat) use ($presenter) {
            $configuration = $boat->primaryConfiguration();

            return [$boat->id => $configuration
                ? $presenter->drawing($boat, $configuration, null, ['labels' => false, 'compact' => true, 'wind' => false])
                : null];
        });

        return view('boats.index', ['boats' => $boats, 'drawings' => $drawings]);
    }

    public function create(): View
    {
        return view('boats.create');
    }

    public function store(BoatRequest $request, BoatLayoutGenerator $generator): RedirectResponse
    {
        $boat = DB::transaction(function () use ($request, $generator) {
            $boat = Boat::create([...$request->boatData(), 'association_id' => $request->user()->association_id]);

            foreach (self::DEFAULT_CONFIGURATIONS as $configuration) {
                $generator->generate($boat->configurations()->create($configuration));
            }

            return $boat;
        });

        return redirect()->route('boats.show', $boat)->with('status', 'Yole enregistrée');
    }

    public function show(Request $request, Boat $boat, CrewPlanPresenter $presenter): View
    {
        $boat->load(['configurations' => fn (HasMany $query) => $this->orderedConfigurations($query)
            ->withCount(['crewPlans' => fn (Builder $plans) => $plans->withTrashed()])]);

        $configuration = $boat->configurations->firstWhere('id', $request->integer('configuration'))
            ?? $boat->primaryConfiguration();

        $configuration?->load('positions.crewRole');

        return view('boats.show', [
            'boat' => $boat,
            'configuration' => $configuration,
            'drawing' => $configuration ? $presenter->drawing($boat, $configuration, null, ['wind' => false]) : null,
            'historyCount' => collect([
                ($plans = $boat->crewPlans()->withTrashed()->count()) ? "{$plans} plan(s) d’équipage" : null,
                ($results = $boat->raceResults()->count()) ? "{$results} résultat(s) de régate" : null,
            ])->filter()->join(' et '),
        ]);
    }

    public function update(BoatRequest $request, Boat $boat): RedirectResponse
    {
        $boat->update($request->boatData());

        return redirect()->route('boats.show', [$boat, 'configuration' => $request->integer('configuration') ?: null])
            ->with('status', 'Yole enregistrée');
    }

    /**
     * A yole with history (crew plans, race results) is only deleted when the admin explicitly
     * confirms that this history goes with it; otherwise "Indisponible" keeps everything.
     */
    public function destroy(Request $request, Boat $boat): RedirectResponse
    {
        $plans = $boat->crewPlans()->withTrashed()->count();
        $results = $boat->raceResults()->count();

        if (($plans > 0 || $results > 0) && ! $request->boolean('with_history')) {
            return redirect()->route('boats.show', $boat)->withErrors([
                'delete' => 'Cette yole figure dans '
                    .collect([$plans ? "{$plans} plan(s) d’équipage" : null, $results ? "{$results} résultat(s) de régate" : null])->filter()->join(' et ')
                    .'. Cochez la case pour les supprimer avec elle, ou passez-la plutôt en « Indisponible ».',
            ]);
        }

        DB::transaction(function () use ($boat) {
            $planIds = $boat->crewPlans()->withTrashed()->pluck('id');
            CrewAssignment::withTrashed()->whereIn('crew_plan_id', $planIds)->forceDelete();
            CrewPlan::withTrashed()->whereIn('id', $planIds)->forceDelete();
            $boat->raceResults()->delete();
            $boat->delete();
        });

        return redirect()->route('boats.index')->with('status', 'Yole supprimée');
    }

    /**
     * Default configuration first, then by name.
     *
     * @param  HasMany<BoatConfiguration, Boat>  $query
     * @return HasMany<BoatConfiguration, Boat>
     */
    private function orderedConfigurations(HasMany $query): HasMany
    {
        return $query->orderByDesc('is_default')->orderBy('name');
    }
}
