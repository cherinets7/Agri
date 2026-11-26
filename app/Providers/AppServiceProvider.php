<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Models\User;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\NullSmsGateway;
use App\Support\Audit;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, fn () => match (config('services.sms.driver')) {
            'log' => new LogSmsGateway,
            default => new NullSmsGateway,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Permissions::flush();
        Audit::reset();

        Gate::define('view-matrix', fn (User $user) => ($user->isZone() || $user->isWoreda()) && Permissions::allows($user, 'matrix.view'));
        Gate::define('manage-plan', fn (User $user) => $user->isZone() && Permissions::allows($user, 'plan.manage'));
        Gate::define('manage-users', fn (User $user) => ($user->isZone() || $user->isWoreda()) && Permissions::allows($user, 'users.manage'));
        Gate::define('manage-locations', fn (User $user) => ($user->isZone() || $user->isWoreda()) && Permissions::allows($user, 'locations.manage'));
        Gate::define('view-audit', fn (User $user) => $user->isZone() && Permissions::allows($user, 'audit.view'));
        Gate::define('manage-permissions', fn (User $user) => $user->isZone() && Permissions::allows($user, 'permissions.manage'));
        Gate::define('enter-kebele-weekly', fn (User $user) => config('plan.kebele_weekly_entry')
            && $user->isKebele() && $user->location_id !== null && Permissions::allows($user, 'kebele_weekly.enter'));
        Gate::define('view-kebele-weekly', fn (User $user) => config('plan.kebele_weekly_entry')
            && ($user->isZone() || $user->isWoreda()) && Permissions::allows($user, 'kebele_weekly.view'));
    }
}
