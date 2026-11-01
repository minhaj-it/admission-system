<?php

namespace App\Providers;

use App\Models\Institution;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Institution::class, fn () => Institution::query()->orderBy('id')->firstOrFail());
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        if ($this->app->isProduction()) {
            URL::forceHttps();
        }

        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Super Admin passes every permission check, but NOT record-level policy rules
        // (locked role, own account, last Super Admin) - those abilities are not permission keys.
        Gate::before(function ($user, string $ability) {
            return $user instanceof User && Permissions::isValid($ability) && $user->isSuperAdmin() ? true : null;
        });

        foreach (Permissions::all() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }
}
