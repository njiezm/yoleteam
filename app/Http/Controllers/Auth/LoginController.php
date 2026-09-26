<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** 400 days: the longest cookie lifetime browsers accept. */
    public const REMEMBER_MINUTES = 576000;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $disabled = false;
        $isActive = function (User $user) use (&$disabled): bool {
            $disabled = $user->isDisabled();

            return ! $disabled;
        };

        // "Rester connecté": the remember cookie lasts 400 days, the maximum browsers accept.
        Auth::guard('web')->setRememberDuration(self::REMEMBER_MINUTES);

        // The callback only runs once the password matched, so "disabled" never reveals an unknown account.
        if (! Auth::attemptWhen($credentials, $isActive, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => $disabled ? EnsureUserIsActive::MESSAGE : 'Ces identifiants ne correspondent à aucun compte.',
            ]);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
