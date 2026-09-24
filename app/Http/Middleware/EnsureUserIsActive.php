<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out an account disabled by a super admin on its next request.
 */
class EnsureUserIsActive
{
    public const MESSAGE = 'Ce compte est désactivé.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isDisabled()) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE], 401);
        }

        return redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
    }
}
