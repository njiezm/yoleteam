<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            ...$request->safe()->only(['name', 'email', 'role', 'password']),
            'association_id' => $request->user()->association_id,
        ]);

        return redirect()->route('settings.edit')->with('status', 'Utilisateur ajouté');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->association_id === $request->user()->association_id, 404);
        abort_if($user->is($request->user()), 403, 'Vous ne pouvez pas supprimer votre propre compte.');
        abort_if($user->isSuperAdmin() && ! $request->user()->isSuperAdmin(), 403, 'Seul un super admin peut supprimer ce compte.');

        $user->delete();

        return redirect()->route('settings.edit')->with('status', 'Utilisateur supprimé');
    }
}
