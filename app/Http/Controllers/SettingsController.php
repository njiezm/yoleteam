<?php

namespace App\Http\Controllers;

use App\Models\CrewRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** Palette offered for the association's primary colour. */
    public const COLORS = ['#0B2545', '#E11D48', '#0EA5E9', '#16A34A', '#7C3AED'];

    public function edit(Request $request): View
    {
        $association = $request->user()->association;

        return view('settings.edit', [
            'association' => $association,
            'users' => $association->users()->orderBy('name')->get(),
            'roles' => CrewRole::query()->orderBy('sort_order')->get(),
            'colors' => self::COLORS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $request->user()->association->update([...$data, 'primary_color' => strtoupper($data['primary_color'])]);

        return redirect()->route('settings.edit')->with('status', 'Paramètres enregistrés');
    }
}
