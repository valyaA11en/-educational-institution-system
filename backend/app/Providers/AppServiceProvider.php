<?php

namespace App\Providers;

use App\Models\Assignment;
use App\Policies\AssignmentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Assignment::class => AssignmentPolicy::class,
        // TODO: добавить остальные Policy классы
    ];

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
        // TODO: Set default string length for PostgreSQL
        Schema::defaultStringLength(191);

        $this->registerPolicies();

        // Register permission gates
        Gate::before(function ($user, $ability) {
            // TODO: check if user has permission by code
            // Example: Gate::define('users.manage', fn($user) => $user->hasPermission('users.manage'));
        });
    }
}
