<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UserRequest;
use App\Models\Association;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $associationId = $request->integer('association') ?: null;
        $role = UserRole::tryFrom((string) $request->query('role'));
        $status = in_array($request->query('status'), ['active', 'disabled'], true) ? $request->query('status') : null;

        $users = User::query()
            ->with('association')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%")
            ))
            ->when($associationId, fn ($query) => $query->where('association_id', $associationId))
            ->when($role, fn ($query) => $query->where('role', $role))
            ->when($status === 'active', fn ($query) => $query->whereNull('disabled_at'))
            ->when($status === 'disabled', fn ($query) => $query->whereNotNull('disabled_at'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('super-admin.users.index', [
            'users' => $users,
            'associations' => Association::query()->orderBy('name')->pluck('name', 'id'),
            'filters' => ['q' => $search, 'association' => $associationId, 'role' => $role?->value, 'status' => $status],
        ]);
    }

    public function create(Request $request): View
    {
        return view('super-admin.users.create', [
            'user' => new User(['role' => UserRole::Patron, 'association_id' => $request->integer('association') ?: null]),
            'associations' => Association::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->userAttributes());

        return redirect()->route('super-admin.users.index')->with('status', "Compte de {$user->name} créé");
    }

    public function edit(User $user): View
    {
        return view('super-admin.users.edit', [
            'user' => $user,
            'associations' => Association::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->userAttributes());

        return redirect()->route('super-admin.users.index')->with('status', 'Compte enregistré');
    }

    /**
     * Disables the account (signed out on its next request) or enables it again.
     */
    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'Vous ne pouvez pas désactiver votre propre compte.');

        $user->forceFill(['disabled_at' => $user->isDisabled() ? null : now()])->save();

        return back()->with('status', $user->isDisabled() ? "Compte de {$user->name} désactivé" : "Compte de {$user->name} réactivé");
    }

    public function sendResetLink(User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['reset' => __($status)]);
        }

        return back()->with('status', "Lien de réinitialisation envoyé à {$user->email}");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'Vous ne pouvez pas supprimer votre propre compte.');

        $user->delete();

        return redirect()->route('super-admin.users.index')->with('status', 'Compte supprimé');
    }
}
