<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SettingsController;
use App\Http\Requests\SuperAdmin\AssociationRequest;
use App\Models\Association;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssociationController extends Controller
{
    public function index(): View
    {
        return view('super-admin.associations.index', [
            'associations' => Association::query()->withCount(['users', 'members', 'boats', 'outings'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('super-admin.associations.create', [
            'association' => new Association(['primary_color' => SettingsController::COLORS[0]]),
            'colors' => SettingsController::COLORS,
        ]);
    }

    public function store(AssociationRequest $request): RedirectResponse
    {
        $association = DB::transaction(function () use ($request): Association {
            $association = Association::create([
                ...$request->associationAttributes(),
                'settings' => ['timezone' => config('app.timezone'), 'locale' => 'fr'],
            ]);

            if ($admin = $request->firstAdminAttributes()) {
                $association->users()->create([...$admin, 'role' => UserRole::Admin]);
            }

            return $association;
        });

        return redirect()->route('super-admin.associations.index')->with('status', "Association « {$association->name} » créée");
    }

    public function edit(Association $association): View
    {
        return view('super-admin.associations.edit', [
            'association' => $association->loadCount(['users', 'members', 'boats', 'outings', 'races']),
            'colors' => SettingsController::COLORS,
        ]);
    }

    public function update(AssociationRequest $request, Association $association): RedirectResponse
    {
        $association->update($request->associationAttributes());

        return redirect()->route('super-admin.associations.index')->with('status', 'Association enregistrée');
    }

    /**
     * Only an empty association (no account, member, boat, outing or regatta) can be deleted.
     */
    public function destroy(Association $association): RedirectResponse
    {
        $association->loadCount(['users', 'members', 'boats', 'outings', 'races']);

        if ($association->users_count + $association->members_count + $association->boats_count + $association->outings_count + $association->races_count > 0) {
            return back()->withErrors(['association' => 'Impossible de supprimer une association qui contient des comptes, membres, yoles, sorties ou régates.']);
        }

        $association->delete();

        return redirect()->route('super-admin.associations.index')->with('status', 'Association supprimée');
    }

    /**
     * Context switch: the super admin now works inside this association.
     */
    public function switch(Request $request, Association $association): RedirectResponse
    {
        $request->user()->update(['association_id' => $association->id]);

        return redirect()->route('dashboard')->with('status', "Vous êtes maintenant dans : {$association->name}");
    }
}
