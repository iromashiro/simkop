<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define Gates for multi-tenant access
        Gate::define('access-cooperative', function ($user, $cooperativeId) {
            // Super admin can access all cooperatives
            if ($user->hasRole('super_admin')) {
                return true;
            }

            // Users can only access their own cooperative
            return $user->cooperative_id === $cooperativeId;
        });

        Gate::define('manage-users', function ($user) {
            return $user->hasAnyRole(['super_admin', 'cooperative_admin']);
        });

        Gate::define('approve-loans', function ($user) {
            return $user->hasAnyRole(['super_admin', 'cooperative_admin']);
        });

        Gate::define('view-all-cooperatives', function ($user) {
            return $user->hasRole('super_admin');
        });
    }
}
