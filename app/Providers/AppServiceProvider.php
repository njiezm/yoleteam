<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Route::resourceVerbs(['create' => 'ajouter', 'edit' => 'modifier']);

        // Integer keys only: a non-numeric id would otherwise reach PostgreSQL and fail with a 500 instead of a 404.
        foreach (['outing', 'crewPlan', 'member', 'boat', 'configuration', 'race', 'stage', 'user', 'association'] as $parameter) {
            Route::pattern($parameter, '[0-9]+');
        }
        Route::pattern('syncOperation', '[0-9a-fA-F-]{36}');

        // Admin / bureau: members, boats, regattas, users and settings. Patrons run outings, attendance and crew plans.
        Gate::define('manage', fn (User $user): bool => $user->isAdmin() || $user->isSuperAdmin());

        // Platform operator: every association and every account (/super-admin).
        Gate::define('super-admin', fn (User $user): bool => $user->isSuperAdmin());

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip())
        ));
    }
}
