<?php

namespace App\Http\Controllers;

use App\Enums\CrewPlanStatus;
use App\Models\CrewPlan;
use App\Models\Outing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Validating a crew plan freezes it for the outing; reopening turns it back into a new draft version.
 */
class CrewPlanValidationController extends Controller
{
    public function store(Request $request, Outing $outing, CrewPlan $crewPlan): RedirectResponse
    {
        if (! $crewPlan->assignments()->exists()) {
            return redirect()->route('crew-plans.edit', [$outing, $crewPlan])->with('status', 'Placez au moins un membre avant de valider');
        }

        $crewPlan->validate($request->user());

        return redirect()->route('crew-plans.show', [$outing, $crewPlan])->with('status', 'Plan d’équipage validé');
    }

    public function destroy(Outing $outing, CrewPlan $crewPlan): RedirectResponse
    {
        if ($crewPlan->isValidated()) {
            $crewPlan->forceFill([
                'status' => CrewPlanStatus::Brouillon,
                'validated_at' => null,
                'validated_by' => null,
                'version' => $crewPlan->version + 1,
            ])->save();
        }

        return redirect()->route('crew-plans.edit', [$outing, $crewPlan]);
    }
}
