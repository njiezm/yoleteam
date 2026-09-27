<?php

namespace App\Http\Controllers;

use App\Models\Outing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Padlock of an outing with several boats: open = a rower seated on one boat can also be seated on another,
 * closed = each rower on one boat only (per race on championship days).
 */
class CrewShareController extends Controller
{
    public function update(Request $request, Outing $outing): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['share_crew' => ['required', 'boolean']]);

        $outing->update(['share_crew' => (bool) $data['share_crew']]);

        if ($request->expectsJson()) {
            return response()->json(['share_crew' => $outing->share_crew]);
        }

        return back()->with('status', $outing->share_crew
            ? 'Cadenas ouvert : les coursiers peuvent être placés sur plusieurs yoles'
            : 'Cadenas fermé : chaque coursier sur une seule yole');
    }
}
