<?php

namespace App\Providers;

use App\Models\Assignment;
use App\Models\ChatThread;
use App\Models\CurriculumPlan;
use App\Models\Document;
use App\Models\DocTemplate;
use App\Models\File;
use App\Models\Grade;
use App\Models\Material;
use App\Models\Contest;
use App\Models\Exam;
use App\Models\Risk;
use App\Models\ScheduleItem;
use App\Models\Ticket;
use App\Policies\AssignmentPolicy;
use App\Policies\ChatThreadPolicy;
use App\Policies\ContestPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\DocTemplatePolicy;
use App\Policies\ExamPolicy;
use App\Policies\FilePolicy;
use App\Policies\GradePolicy;
use App\Policies\KtpPlanPolicy;
use App\Policies\MaterialPolicy;
use App\Policies\RiskPolicy;
use App\Policies\ScheduleItemPolicy;
use App\Policies\TicketPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\Exam::class => \App\Policies\ExamPolicy::class,
        Assignment::class => AssignmentPolicy::class,
        ScheduleItem::class => ScheduleItemPolicy::class,
        Material::class => MaterialPolicy::class,
        Document::class => DocumentPolicy::class,
        DocTemplate::class => DocTemplatePolicy::class,
        File::class => FilePolicy::class,
        ChatThread::class => ChatThreadPolicy::class,
        Ticket::class => TicketPolicy::class,
        Grade::class => GradePolicy::class,
        Risk::class => RiskPolicy::class,
        CurriculumPlan::class => KtpPlanPolicy::class,
        Exam::class => ExamPolicy::class,
        Contest::class => ContestPolicy::class,
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

        // Register timeline observers
        Grade::observe(\App\Observers\GradeObserver::class);
        Risk::observe(\App\Observers\RiskObserver::class);

        // Register HasTenant trait for models
        $models = [
            \App\Models\Group::class,
            \App\Models\Subject::class,
            \App\Models\Room::class,
            \App\Models\ScheduleVersion::class,
            \App\Models\ScheduleItem::class,
            \App\Models\Assignment::class,
            \App\Models\Material::class,
            \App\Models\Document::class,
            \App\Models\Exam::class,
            \App\Models\Contest::class,
        ];

        foreach ($models as $model) {
            if (method_exists($model, 'bootHasTenant')) {
                // Trait will auto-boot
            }
        }
    }
}
