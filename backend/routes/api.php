<?php

use App\Http\Controllers\Api\Admin\Directory\GroupsController as AdminGroupsController;
use App\Http\Controllers\Api\Admin\Directory\RoomsController as AdminRoomsController;
use App\Http\Controllers\Api\Admin\Directory\SubjectsController as AdminSubjectsController;
use App\Http\Controllers\Api\Admin\Directory\SubgroupsController as AdminSubgroupsController;
use App\Http\Controllers\Api\Admin\Directory\TimeSlotsController as AdminTimeSlotsController;
use App\Http\Controllers\Api\Admin\ImportUsersController;
use App\Http\Controllers\Api\Admin\RolesController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\UsersController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\JournalController;
use App\Http\Controllers\Api\V1\MaterialController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\RealtimeController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\RuleController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Realtime replay endpoint (outside of versioned prefix): /api/realtime/replay
Route::middleware('auth:api')->get('realtime/replay', [RealtimeController::class, 'replay']);

Route::prefix('v1')->group(function (): void {
    // Public routes
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // Protected routes
    Route::middleware('auth:api')->group(function (): void {
        // Auth
        Route::prefix('auth')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });

        // Roles
        Route::get('roles', [RoleController::class, 'index']);

        // Users (admin only - role:admin)
        Route::prefix('users')->middleware('role:admin')->group(function (): void {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::get('{id}', [UserController::class, 'show']);
            Route::put('{id}', [UserController::class, 'update']);
            Route::delete('{id}', [UserController::class, 'destroy']);
        });

        // Directory (admin only for mutations)
        Route::prefix('directory')->group(function (): void {
            // Groups
            Route::get('groups', [DirectoryController::class, 'groups']);
            Route::post('groups', [DirectoryController::class, 'storeGroup'])->middleware('role:admin');
            Route::put('groups/{id}', [DirectoryController::class, 'updateGroup'])->middleware('role:admin');
            Route::delete('groups/{id}', [DirectoryController::class, 'destroyGroup'])->middleware('role:admin');

            // Subgroups
            Route::get('subgroups', [DirectoryController::class, 'subgroups']);
            Route::post('subgroups', [DirectoryController::class, 'storeSubgroup'])->middleware('role:admin');
            Route::put('subgroups/{id}', [DirectoryController::class, 'updateSubgroup'])->middleware('role:admin');
            Route::delete('subgroups/{id}', [DirectoryController::class, 'destroySubgroup'])->middleware('role:admin');

            // Subjects
            Route::get('subjects', [DirectoryController::class, 'subjects']);
            Route::post('subjects', [DirectoryController::class, 'storeSubject'])->middleware('role:admin');
            Route::put('subjects/{id}', [DirectoryController::class, 'updateSubject'])->middleware('role:admin');
            Route::delete('subjects/{id}', [DirectoryController::class, 'destroySubject'])->middleware('role:admin');

            // Rooms
            Route::get('rooms', [DirectoryController::class, 'rooms']);
            Route::post('rooms', [DirectoryController::class, 'storeRoom'])->middleware('role:admin');
            Route::put('rooms/{id}', [DirectoryController::class, 'updateRoom'])->middleware('role:admin');
            Route::delete('rooms/{id}', [DirectoryController::class, 'destroyRoom'])->middleware('role:admin');

            // Time slots
            Route::get('time-slots', [DirectoryController::class, 'timeSlots']);
            Route::post('time-slots', [DirectoryController::class, 'storeTimeSlot'])->middleware('role:admin');
            Route::put('time-slots/{id}', [DirectoryController::class, 'updateTimeSlot'])->middleware('role:admin');
            Route::delete('time-slots/{id}', [DirectoryController::class, 'destroyTimeSlot'])->middleware('role:admin');
        });

        // Schedule
        Route::prefix('schedule')->middleware('can:schedule.read')->group(function (): void {
            Route::get('versions', [ScheduleController::class, 'versions']);
            Route::post('versions', [ScheduleController::class, 'createVersion'])->middleware('can:schedule.create');
            Route::get('items', [ScheduleController::class, 'items']);
            Route::post('items', [ScheduleController::class, 'createItem'])->middleware('can:schedule.create');
            Route::get('replacements', [ScheduleController::class, 'replacements']);
            Route::post('replacements', [ScheduleController::class, 'createReplacement'])->middleware('can:schedule.replace');
            Route::post('suggest-room', [ScheduleController::class, 'suggestRoom'])->middleware('can:schedule.create');
            Route::post('suggest-teacher', [ScheduleController::class, 'suggestTeacher'])->middleware('can:schedule.create');
        });

        // Journal
        Route::prefix('journal')->middleware('can:journal.read')->group(function (): void {
            Route::get('lessons', [JournalController::class, 'lessons']);
            Route::post('lessons', [JournalController::class, 'createLesson'])->middleware('can:journal.create');
            Route::get('grades', [JournalController::class, 'grades']);
            Route::post('grades', [JournalController::class, 'createGrade'])->middleware('can:journal.grade');
            Route::get('attendance', [JournalController::class, 'attendance']);
            Route::post('attendance', [JournalController::class, 'createAttendance'])->middleware('can:journal.attendance');
            Route::get('reports', [JournalController::class, 'reports']);
        });

        // Assignments
        Route::prefix('assignments')->group(function (): void {
            Route::get('/', [AssignmentController::class, 'index']);
            Route::post('/', [AssignmentController::class, 'store'])->middleware('can:assignments.create');
            Route::get('{id}', [AssignmentController::class, 'show']);
            Route::put('{id}', [AssignmentController::class, 'update'])->middleware('can:assignments.update');
            Route::delete('{id}', [AssignmentController::class, 'destroy'])->middleware('can:assignments.delete');
            Route::post('{id}/submit', [AssignmentController::class, 'submit'])->middleware('can:assignments.submit');
            Route::post('{id}/submissions/{submissionId}/grade', [AssignmentController::class, 'grade'])->middleware('can:assignments.grade');
        });

        // Materials
        Route::prefix('materials')->group(function (): void {
            Route::get('/', [MaterialController::class, 'index']);
            Route::post('/', [MaterialController::class, 'store'])->middleware('can:materials.create');
            Route::get('{id}', [MaterialController::class, 'show']);
            Route::put('{id}', [MaterialController::class, 'update'])->middleware('can:materials.update');
            Route::delete('{id}', [MaterialController::class, 'destroy'])->middleware('can:materials.delete');
            Route::post('{id}/read', [MaterialController::class, 'read']);
        });

        // Files
        Route::prefix('files')->group(function (): void {
            Route::post('presigned-upload', [FileController::class, 'getPresignedUploadUrl'])->middleware('can:assignments.submit');
            Route::post('{id}/confirm', [FileController::class, 'confirmUpload'])->middleware('can:assignments.submit');
            Route::get('{id}/download', [FileController::class, 'download']);
        });

        // Documents
        Route::prefix('documents')->group(function (): void {
            Route::get('templates', [DocumentController::class, 'templates']);
            Route::get('/', [DocumentController::class, 'index']);
            Route::post('/', [DocumentController::class, 'store'])->middleware('can:documents.create');
            Route::get('{id}', [DocumentController::class, 'show']);
            Route::get('{id}/download', [DocumentController::class, 'download']);
            Route::post('{id}/approve', [DocumentController::class, 'approve'])->middleware('can:documents.approve');
            Route::post('{id}/reject', [DocumentController::class, 'reject'])->middleware('can:documents.approve');
            Route::post('{id}/sign', [DocumentController::class, 'sign'])->middleware('can:documents.sign');
            Route::get('verify/{hash}', [DocumentController::class, 'verify']);
        });

        // Notifications
        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
            Route::get('settings', [NotificationController::class, 'settings']);
            Route::put('settings', [NotificationController::class, 'updateSettings']);
        });

        // Chats
        Route::prefix('chats')->group(function (): void {
            Route::get('threads', [ChatController::class, 'threads']);
            Route::post('threads', [ChatController::class, 'createThread']);
            Route::get('threads/{threadId}/messages', [ChatController::class, 'messages']);
            Route::post('threads/{threadId}/messages', [ChatController::class, 'sendMessage']);
            Route::delete('threads/{threadId}/messages/{messageId}', [ChatController::class, 'deleteMessage']);
        });

        // Tickets
        Route::prefix('tickets')->group(function (): void {
            Route::get('/', [TicketController::class, 'index']);
            Route::post('/', [TicketController::class, 'store']);
            Route::get('{id}', [TicketController::class, 'show']);
            Route::put('{id}', [TicketController::class, 'update'])->middleware('can:tickets.update');
            Route::get('{id}/messages', [TicketController::class, 'messages']);
            Route::post('{id}/messages', [TicketController::class, 'sendMessage']);
        });

        // Rules
        Route::prefix('rules')->middleware('can:rules.manage')->group(function (): void {
            Route::get('/', [RuleController::class, 'index']);
            Route::post('/', [RuleController::class, 'store']);
            Route::get('{id}', [RuleController::class, 'show']);
            Route::put('{id}', [RuleController::class, 'update']);
            Route::delete('{id}', [RuleController::class, 'destroy']);
            Route::post('{id}/toggle', [RuleController::class, 'toggle']);
        });

        // Analytics (placeholder)
        Route::prefix('analytics')->middleware('can:analytics.view')->group(function (): void {
            Route::get('/', [AnalyticsController::class, 'index']);
        });

        // Admin Roles
        Route::get('admin/roles', [RolesController::class, 'index'])->middleware('can:users.read');

        // Admin Users CRUD (only users.read permission)
        Route::prefix('admin/users')->middleware('can:users.read')->group(function (): void {
            Route::get('/', [UsersController::class, 'index']);
            Route::post('/', [UsersController::class, 'store'])->middleware('can:users.create');
            Route::get('{id}', [UsersController::class, 'show']);
            Route::patch('{id}', [UsersController::class, 'update'])->middleware('can:users.update');
            Route::delete('{id}', [UsersController::class, 'destroy'])->middleware('can:users.delete');
            Route::post('{id}/roles', [UsersController::class, 'assignRoles'])->middleware('can:roles.assign');
        });

        // Admin Import
        Route::prefix('admin/import')->middleware('can:users.create')->group(function (): void {
            Route::post('users-xlsx', [ImportUsersController::class, 'importUsersXlsx']);
        });

        // Admin Settings
        Route::prefix('admin/settings')->middleware('can:analytics.view')->group(function (): void {
            Route::get('/', [SettingsController::class, 'index']);
            Route::post('/', [SettingsController::class, 'store'])->middleware('can:directory.manage');
        });

        // Admin Directory CRUD (only directory.manage permission)
        Route::prefix('admin/directory')->middleware('can:directory.manage')->group(function (): void {
            // Groups
            Route::prefix('groups')->group(function (): void {
                Route::get('/', [AdminGroupsController::class, 'index']);
                Route::post('/', [AdminGroupsController::class, 'store']);
                Route::get('{id}', [AdminGroupsController::class, 'show']);
                Route::put('{id}', [AdminGroupsController::class, 'update']);
                Route::delete('{id}', [AdminGroupsController::class, 'destroy']);
            });

            // Subgroups
            Route::prefix('subgroups')->group(function (): void {
                Route::get('/', [AdminSubgroupsController::class, 'index']);
                Route::post('/', [AdminSubgroupsController::class, 'store']);
                Route::get('{id}', [AdminSubgroupsController::class, 'show']);
                Route::put('{id}', [AdminSubgroupsController::class, 'update']);
                Route::delete('{id}', [AdminSubgroupsController::class, 'destroy']);
            });

            // Subjects
            Route::prefix('subjects')->group(function (): void {
                Route::get('/', [AdminSubjectsController::class, 'index']);
                Route::post('/', [AdminSubjectsController::class, 'store']);
                Route::get('{id}', [AdminSubjectsController::class, 'show']);
                Route::put('{id}', [AdminSubjectsController::class, 'update']);
                Route::delete('{id}', [AdminSubjectsController::class, 'destroy']);
            });

            // Rooms
            Route::prefix('rooms')->group(function (): void {
                Route::get('/', [AdminRoomsController::class, 'index']);
                Route::post('/', [AdminRoomsController::class, 'store']);
                Route::get('{id}', [AdminRoomsController::class, 'show']);
                Route::put('{id}', [AdminRoomsController::class, 'update']);
                Route::delete('{id}', [AdminRoomsController::class, 'destroy']);
            });

            // Time Slots
            Route::prefix('time-slots')->group(function (): void {
                Route::get('/', [AdminTimeSlotsController::class, 'index']);
                Route::post('/', [AdminTimeSlotsController::class, 'store']);
                Route::get('{id}', [AdminTimeSlotsController::class, 'show']);
                Route::put('{id}', [AdminTimeSlotsController::class, 'update']);
                Route::delete('{id}', [AdminTimeSlotsController::class, 'destroy']);
            });
        });
    });
});
