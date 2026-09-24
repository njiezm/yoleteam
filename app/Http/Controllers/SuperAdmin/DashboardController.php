<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\Member;
use App\Models\Outing;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $associations = Association::query()
            ->withCount(['users', 'members', 'boats', 'outings'])
            ->withMax('outings', 'updated_at')
            ->withMax('users', 'last_login_at')
            ->orderBy('name')
            ->get();

        return view('super-admin.dashboard', [
            'associationCount' => $associations->count(),
            'activeUsers' => User::query()->whereNull('disabled_at')->count(),
            'disabledUsers' => User::query()->whereNotNull('disabled_at')->count(),
            'memberCount' => Member::query()->count(),
            'recentOutings' => Outing::query()->whereBetween('date', [today()->subDays(30)->toDateString(), today()->toDateString()])->count(),
            'associations' => $associations,
            'lastActivity' => $associations->mapWithKeys(fn (Association $association) => [
                $association->id => collect([$association->outings_max_updated_at, $association->users_max_last_login_at])
                    ->filter()
                    ->map(fn (string $date) => Carbon::parse($date))
                    ->max(),
            ]),
            'latestLogins' => User::query()->with('association')->whereNotNull('last_login_at')->latest('last_login_at')->limit(8)->get(),
        ]);
    }
}
