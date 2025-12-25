<?php

namespace App\Providers;

use App\Models\Assignment;
use App\Models\ChatThread;
use App\Models\Document;
use App\Models\File;
use App\Models\Grade;
use App\Models\Material;
use App\Models\ScheduleItem;
use App\Models\Ticket;
use App\Policies\AssignmentPolicy;
use App\Policies\ChatThreadPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\FilePolicy;
use App\Policies\GradePolicy;
use App\Policies\MaterialPolicy;
use App\Policies\ScheduleItemPolicy;
use App\Policies\TicketPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Assignment::class => AssignmentPolicy::class,
        ScheduleItem::class => ScheduleItemPolicy::class,
        Material::class => MaterialPolicy::class,
        Document::class => DocumentPolicy::class,
        File::class => FilePolicy::class,
        ChatThread::class => ChatThreadPolicy::class,
        Ticket::class => TicketPolicy::class,
        Grade::class => GradePolicy::class,
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

        // Register observers for cache invalidation
        \App\Models\User::observe(\App\Observers\UserObserver::class);
        \App\Models\UserLinkParentChild::observe(\App\Observers\UserLinkParentChildObserver::class);
        // Note: GroupMember, TeacherSubjectGroup are pivot tables, observers need to be registered differently
        // They will be handled via model events or direct cache invalidation in controllers
    }
}
