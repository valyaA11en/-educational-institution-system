<?php

use App\Http\Controllers\Api\Admin\Directory\GroupsController as AdminGroupsController;
use App\Http\Controllers\Api\Admin\Directory\RoomsController as AdminRoomsController;
use App\Http\Controllers\Api\Admin\Directory\SubjectsController as AdminSubjectsController;
use App\Http\Controllers\Api\Admin\Directory\SubgroupsController as AdminSubgroupsController;
use App\Http\Controllers\Api\Admin\Directory\TimeSlotsController as AdminTimeSlotsController;
use App\Http\Controllers\Api\Admin\ImportController;
use App\Http\Controllers\Api\Admin\ExportController;
use App\Http\Controllers\Api\Admin\RolesController;
use App\Http\Controllers\Api\Admin\RulesController as AdminRulesController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\TicketsController as AdminTicketsController;
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
// use App\Http\Controllers\Api\V1\RealtimeController; // Temporarily disabled - missing controller
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\RuleController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Realtime replay endpoint (outside of versioned prefix): /api/realtime/replay
// Route::middleware('auth:api')->get('realtime/replay', [RealtimeController::class, 'replay']); // Temporarily disabled

// Metrics and health (public for monitoring)
// Route::get('metrics', [\App\Http\Controllers\Api\V1\MetricsController::class, 'prometheus']); // Temporarily disabled
// Route::get('health', [\App\Http\Controllers\Api\V1\MetricsController::class, 'health']); // Temporarily disabled

Route::prefix('v1')->group(function (): void {
    // Public routes
    Route::get('meta', [\App\Http\Controllers\Api\V1\MetaController::class, 'index']);
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:login');
    Route::post('auth/2fa/verify', [AuthController::class, 'verify2FA']);

        // Print routes (protected)
        Route::middleware('auth:api')->prefix('print')->group(function (): void {
            Route::get('schedule', [\App\Http\Controllers\Api\V1\PrintController::class, 'schedule']);
            Route::get('journal', [\App\Http\Controllers\Api\V1\PrintController::class, 'journal']);
            Route::get('attendance', [\App\Http\Controllers\Api\V1\PrintController::class, 'attendance']);
        });

        // Student timeline
        Route::middleware('auth:api')->prefix('students')->group(function (): void {
            Route::get('{id}/timeline', [\App\Http\Controllers\Api\V1\StudentTimelineController::class, 'index']);
            Route::get('{id}/timeline/export', [\App\Http\Controllers\Api\V1\StudentTimelineController::class, 'export']);
            
            // Student portfolio
            Route::get('{id}/portfolio', [\App\Http\Controllers\Api\V1\StudentPortfolioController::class, 'index']);
            Route::get('{id}/portfolio/export', [\App\Http\Controllers\Api\V1\StudentPortfolioController::class, 'export']);
            Route::patch('{id}/portfolio/{itemId}', [\App\Http\Controllers\Api\V1\StudentPortfolioController::class, 'update']);
            Route::post('{id}/portfolio/manual', [\App\Http\Controllers\Api\V1\StudentPortfolioController::class, 'storeManual']);
        });

        // Personal assistant
        Route::middleware('auth:api')->prefix('assistant')->group(function (): void {
            Route::get('today', [\App\Http\Controllers\Api\V1\PersonalAssistantController::class, 'today']);
        });

        // Portfolio
        Route::middleware('auth:api')->prefix('portfolio')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\V1\PortfolioController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\PortfolioController::class, 'store']);
            Route::put('{id}', [\App\Http\Controllers\Api\V1\PortfolioController::class, 'update']);
            Route::delete('{id}', [\App\Http\Controllers\Api\V1\PortfolioController::class, 'destroy']);
        });

        // Failed topics analysis
        Route::middleware('auth:api')->prefix('analysis')->group(function (): void {
            Route::get('failed-topics/{studentId}', [\App\Http\Controllers\Api\V1\FailedTopicsController::class, 'student']);
            Route::get('complex-topics', [\App\Http\Controllers\Api\V1\FailedTopicsController::class, 'complexTopics']);
        });

        // Student export
        Route::middleware('auth:api')->prefix('students')->group(function (): void {
            Route::get('{id}/export', [\App\Http\Controllers\Api\V1\StudentExportController::class, 'export']);
        });

        // Protected routes
        Route::middleware('auth:api')->group(function (): void {
        // Tenant context
        Route::prefix('tenant')->group(function (): void {
            Route::get('current', [\App\Http\Controllers\Api\V1\TenantContextController::class, 'current']);
            Route::post('switch', [\App\Http\Controllers\Api\V1\TenantContextController::class, 'switch'])->middleware('role:admin');
            Route::get('list', [\App\Http\Controllers\Api\V1\TenantContextController::class, 'list'])->middleware('role:admin');
        });

        // Auth
        Route::prefix('auth')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('2fa/status', [\App\Http\Controllers\Api\V1\TwoFactorStatusController::class, 'status']);
            Route::post('2fa/setup', [\App\Http\Controllers\Api\V1\TwoFactorController::class, 'setup']);
            Route::post('2fa/enable', [\App\Http\Controllers\Api\V1\TwoFactorController::class, 'enable']);
            Route::post('2fa/disable', [\App\Http\Controllers\Api\V1\TwoFactorController::class, 'disable']);
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
            Route::post('versions/{id}/publish', [ScheduleController::class, 'publishVersion'])->middleware('can:schedule.create');
            Route::post('versions/{id}/archive', [ScheduleController::class, 'archiveVersion'])->middleware('can:schedule.create');
            Route::get('versions/{id}/changelog', [ScheduleController::class, 'changelog']);
            Route::post('versions/compare', [ScheduleController::class, 'compareVersions']);
            Route::get('changes', [ScheduleController::class, 'changes']);
            Route::get('diff', [ScheduleController::class, 'diff']);
            Route::get('items', [ScheduleController::class, 'items']);
            Route::post('items', [ScheduleController::class, 'createItem'])->middleware('can:schedule.create');
            Route::put('items/{id}', [ScheduleController::class, 'updateItem'])->middleware('can:schedule.create');
            Route::delete('items/{id}', [ScheduleController::class, 'deleteItem'])->middleware('can:schedule.create');
            Route::get('replacements', [ScheduleController::class, 'replacements']);
            Route::post('replacements', [ScheduleController::class, 'createReplacement'])->middleware('can:schedule.replace');
            Route::post('replacements/{id}/approve', [ScheduleController::class, 'approveReplacement'])->middleware('can:schedule.replace');
            Route::post('replacements/{id}/apply', [ScheduleController::class, 'applyReplacement'])->middleware('can:schedule.replace');
            Route::post('suggest-room', [ScheduleController::class, 'suggestRoom'])->middleware('can:schedule.create');
            Route::post('suggest-teacher', [ScheduleController::class, 'suggestTeacher'])->middleware('can:schedule.create');
        });

        // KTP Plans (Календарно-тематическое планирование)
        Route::prefix('ktp')->group(function (): void {
            Route::get('plans', [\App\Http\Controllers\Api\V1\KtpController::class, 'index']);
            Route::post('plans', [\App\Http\Controllers\Api\V1\KtpController::class, 'store']);
            Route::get('plans/{id}', [\App\Http\Controllers\Api\V1\KtpController::class, 'show']);
            Route::patch('plans/{id}', [\App\Http\Controllers\Api\V1\KtpController::class, 'update']);
            Route::post('plans/{id}/topics', [\App\Http\Controllers\Api\V1\KtpController::class, 'addTopics']);
            Route::get('plans/{id}/progress', [\App\Http\Controllers\Api\V1\KtpController::class, 'progress']);
            Route::post('plans/{id}/apply-template', [\App\Http\Controllers\Api\V1\KtpController::class, 'applyTemplate']);
            Route::post('plans/{id}/copy-from', [\App\Http\Controllers\Api\V1\KtpController::class, 'copyFrom']);

            Route::patch('topics/{id}', [\App\Http\Controllers\Api\V1\KtpController::class, 'updateTopic']);
            Route::delete('topics/{id}', [\App\Http\Controllers\Api\V1\KtpController::class, 'deleteTopic']);
            Route::post('topics/{id}/link', [\App\Http\Controllers\Api\V1\KtpController::class, 'linkTopic']);

            Route::get('templates', [\App\Http\Controllers\Api\V1\KtpTemplateController::class, 'index']);
            Route::post('templates', [\App\Http\Controllers\Api\V1\KtpTemplateController::class, 'store']);
        });

        // Curriculum Plans (КТП) - Legacy routes (keep for backward compatibility)
        Route::prefix('curriculum')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\V1\CurriculumController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\CurriculumController::class, 'store']);
            Route::get('{id}', [\App\Http\Controllers\Api\V1\CurriculumController::class, 'show']);
            Route::put('{id}', [\App\Http\Controllers\Api\V1\CurriculumController::class, 'update']);
            Route::delete('{id}', [\App\Http\Controllers\Api\V1\CurriculumController::class, 'destroy']);
            Route::post('{id}/copy', [\App\Http\Controllers\Api\V1\CurriculumController::class, 'copy']);
            Route::post('{id}/topics', [\App\Http\Controllers\Api\V1\CurriculumController::class, 'addTopic']);
            Route::put('{id}/topics/{topicId}', [\App\Http\Controllers\Api\V1\CurriculumController::class, 'updateTopic']);
            Route::delete('{id}/topics/{topicId}', [\App\Http\Controllers\Api\V1\CurriculumController::class, 'deleteTopic']);
            Route::post('{id}/topics/{topicId}/lessons', [\App\Http\Controllers\Api\V1\CurriculumController::class, 'linkLesson']);
            Route::post('{id}/topics/{topicId}/assignments', [\App\Http\Controllers\Api\V1\CurriculumController::class, 'linkAssignment']);
        });

        // Journal
        Route::prefix('journal')->middleware('can:journal.read')->group(function (): void {
            Route::get('reports', [JournalController::class, 'reports']);
            Route::get('reports/grade-changes/export', [JournalController::class, 'exportGradeChanges'])->middleware('can:journal.read');
            Route::get('lessons', [JournalController::class, 'lessons']);
            Route::post('lessons', [JournalController::class, 'createLesson'])->middleware('can:journal.create');
            Route::put('lessons/{id}', [JournalController::class, 'updateLesson'])->middleware('can:journal.create');
            Route::delete('lessons/{id}', [JournalController::class, 'destroyLesson'])->middleware('can:journal.create');
            Route::get('grades', [JournalController::class, 'grades']);
            Route::post('grades', [JournalController::class, 'createGrade'])->middleware('can:journal.grade');
            Route::put('grades/{id}', [JournalController::class, 'updateGrade'])->middleware('can:journal.grade');
            Route::delete('grades/{id}', [JournalController::class, 'destroyGrade'])->middleware('can:journal.grade');
            Route::get('attendance', [JournalController::class, 'attendance']);
            Route::post('attendance', [JournalController::class, 'createAttendance'])->middleware('can:journal.attendance');
            Route::put('attendance/{id}', [JournalController::class, 'updateAttendance'])->middleware('can:journal.attendance');
            Route::delete('attendance/{id}', [JournalController::class, 'destroyAttendance'])->middleware('can:journal.attendance');
        });

        // Assignments
        Route::prefix('assignments')->group(function (): void {
            Route::get('/', [AssignmentController::class, 'index']);
            Route::post('/', [AssignmentController::class, 'store'])->middleware('can:assignments.create');
            Route::get('{id}', [AssignmentController::class, 'show']);
            Route::put('{id}', [AssignmentController::class, 'update'])->middleware('can:assignments.update');
            Route::delete('{id}', [AssignmentController::class, 'destroy'])->middleware('can:assignments.delete');
            Route::post('{id}/submit', [AssignmentController::class, 'submit'])->middleware(['can:assignments.submit', 'throttle:assignment-submit']);
            Route::post('{id}/submissions/{submissionId}/grade', [AssignmentController::class, 'grade'])->middleware('can:assignments.grade');
        });

        // Materials
        Route::prefix('materials')->group(function (): void {
            Route::get('/', [MaterialController::class, 'index']);
            Route::post('/', [MaterialController::class, 'store'])->middleware('can:materials.create');
            Route::get('{id}/download', [MaterialController::class, 'download']);
            Route::get('{id}', [MaterialController::class, 'show']);
            Route::put('{id}', [MaterialController::class, 'update'])->middleware('can:materials.update');
            Route::delete('{id}', [MaterialController::class, 'destroy'])->middleware('can:materials.delete');
            Route::post('{id}/read', [MaterialController::class, 'read']);
        });

        // Files
        Route::prefix('files')->group(function (): void {
            Route::post('presigned-upload', [FileController::class, 'getPresignedUploadUrl'])->middleware(['can:assignments.submit', 'throttle:file-presign']);
            Route::post('upload', [FileController::class, 'upload'])->middleware('can:assignments.submit');
            Route::post('{id}/confirm', [FileController::class, 'confirmUpload'])->middleware('can:assignments.submit');
            Route::get('{id}/download', [FileController::class, 'download']);
            Route::delete('{id}', [FileController::class, 'delete']);
        });

        // Documents
        Route::prefix('documents')->group(function (): void {
            Route::get('templates', [DocumentController::class, 'templates']);
            Route::get('/', [DocumentController::class, 'index']);
            Route::post('/', [DocumentController::class, 'store'])->middleware('can:documents.create');
            Route::get('{id}', [DocumentController::class, 'show']);
            Route::patch('{id}', [DocumentController::class, 'update'])->middleware('can:documents.update');
            Route::get('{id}/download', [DocumentController::class, 'download']);
            Route::get('{id}/print', [DocumentController::class, 'print']);
            Route::post('{id}/send-to-approval', [DocumentController::class, 'sendToApproval'])->middleware('can:documents.approve');
            Route::post('{id}/approve', [DocumentController::class, 'approve'])->middleware('can:documents.approve');
            Route::post('{id}/reject', [DocumentController::class, 'reject'])->middleware('can:documents.approve');
            Route::post('{id}/sign', [DocumentController::class, 'sign'])->middleware('can:documents.sign');
            Route::post('{id}/ack/targets', [DocumentController::class, 'setAckTargets']);
            Route::post('{id}/ack/confirm', [DocumentController::class, 'confirmAck']);
            Route::get('{id}/ack', [DocumentController::class, 'getAck']);
            Route::post('{id}/register-number', [DocumentController::class, 'registerNumber'])->middleware('can:documents.registry');
            Route::get('verify/{hash}', [DocumentController::class, 'verify']);
            Route::post('{id}/gost/validate', [\App\Http\Controllers\Api\V1\GostDocumentController::class, 'validateDocument'])->middleware('auth:api');
        });

        // Document Templates (Admin)
        Route::prefix('admin/documents/templates')->middleware('can:documents.registry')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\Admin\DocumentTemplateController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Admin\DocumentTemplateController::class, 'store']);
            Route::patch('{id}', [\App\Http\Controllers\Api\Admin\DocumentTemplateController::class, 'update']);
            Route::delete('{id}', [\App\Http\Controllers\Api\Admin\DocumentTemplateController::class, 'destroy']);
        });

        // Notifications
        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
            Route::get('settings', [NotificationController::class, 'settings']);
            Route::put('settings', [NotificationController::class, 'updateSettings']);
            Route::post('{id}/read', [NotificationController::class, 'markAsRead']);
            Route::delete('{id}', [NotificationController::class, 'delete']);
        });

        // Push notifications
        Route::prefix('push')->group(function (): void {
            Route::post('subscribe', [\App\Http\Controllers\Api\V1\PushController::class, 'subscribe']);
            Route::delete('unsubscribe', [\App\Http\Controllers\Api\V1\PushController::class, 'unsubscribe']);
        });

        // Exams
        Route::prefix('exams')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\V1\ExamController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\ExamController::class, 'store']);
            Route::get('{id}', [\App\Http\Controllers\Api\V1\ExamController::class, 'show']);
            Route::patch('{id}', [\App\Http\Controllers\Api\V1\ExamController::class, 'update']);
            Route::delete('{id}', [\App\Http\Controllers\Api\V1\ExamController::class, 'destroy']);
            Route::post('{id}/commission', [\App\Http\Controllers\Api\V1\ExamController::class, 'setCommission']);
            Route::get('{id}/commission', [\App\Http\Controllers\Api\V1\ExamController::class, 'getCommission']);
            Route::post('{id}/registrations/seed', [\App\Http\Controllers\Api\V1\ExamController::class, 'seedRegistrations']);
            Route::post('{id}/registrations/{studentId}/evaluate-admission', [\App\Http\Controllers\Api\V1\ExamController::class, 'evaluateAdmission']);
            Route::get('{id}/admission-report', [\App\Http\Controllers\Api\V1\ExamController::class, 'admissionReport']);
            Route::post('{id}/results', [\App\Http\Controllers\Api\V1\ExamController::class, 'setResult']);
            Route::get('{id}/results', [\App\Http\Controllers\Api\V1\ExamController::class, 'getResults']);
            Route::post('{id}/generate-sheet', [\App\Http\Controllers\Api\V1\ExamController::class, 'generateSheet']);
            Route::get('rules/{termId}', [\App\Http\Controllers\Api\V1\ExamController::class, 'getRules']);
            Route::post('rules/{termId}', [\App\Http\Controllers\Api\V1\ExamController::class, 'setRules']);
        });

        // Contests
        Route::prefix('contests')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\V1\ContestController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\ContestController::class, 'store']);
            Route::get('{id}', [\App\Http\Controllers\Api\V1\ContestController::class, 'show']);
            Route::patch('{id}', [\App\Http\Controllers\Api\V1\ContestController::class, 'update']);
            Route::delete('{id}', [\App\Http\Controllers\Api\V1\ContestController::class, 'destroy']);
            Route::post('{id}/targets', [\App\Http\Controllers\Api\V1\ContestController::class, 'setTargets']);
            Route::post('{id}/jury', [\App\Http\Controllers\Api\V1\ContestController::class, 'addJury']);
            Route::post('{id}/rubric', [\App\Http\Controllers\Api\V1\ContestController::class, 'addRubric']);
            Route::post('{id}/submit', [\App\Http\Controllers\Api\V1\ContestController::class, 'submit']);
            Route::get('{id}/submissions', [\App\Http\Controllers\Api\V1\ContestController::class, 'getSubmissions']);
            Route::get('{id}/submissions/{sid}', [\App\Http\Controllers\Api\V1\ContestController::class, 'getSubmission']);
            Route::post('{id}/submissions/{sid}/score', [\App\Http\Controllers\Api\V1\ContestController::class, 'score']);
            Route::get('{id}/submissions/{sid}/scores', [\App\Http\Controllers\Api\V1\ContestController::class, 'getScores']);
            Route::post('{id}/publish-results', [\App\Http\Controllers\Api\V1\ContestController::class, 'publishResults']);
            Route::get('{id}/results', [\App\Http\Controllers\Api\V1\ContestController::class, 'getResults']);
            Route::post('{id}/generate-certificates', [\App\Http\Controllers\Api\V1\ContestController::class, 'generateCertificates']);
        });

        // Import/Export
        Route::prefix('import-export')->group(function (): void {
            Route::get('users/export', [\App\Http\Controllers\Api\V1\ImportExportController::class, 'exportUsers']);
            Route::get('groups/export', [\App\Http\Controllers\Api\V1\ImportExportController::class, 'exportGroups']);
            Route::get('schedule/export', [\App\Http\Controllers\Api\V1\ImportExportController::class, 'exportSchedule']);
            Route::get('journal/export', [\App\Http\Controllers\Api\V1\ImportExportController::class, 'exportJournal']);
            Route::post('users/import', [\App\Http\Controllers\Api\V1\ImportExportController::class, 'importUsers']);
        });

        // Webhooks
        Route::prefix('webhooks')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\V1\WebhookController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\WebhookController::class, 'store']);
            Route::patch('{id}', [\App\Http\Controllers\Api\V1\WebhookController::class, 'update']);
            Route::delete('{id}', [\App\Http\Controllers\Api\V1\WebhookController::class, 'destroy']);
        });

        // Chats
        Route::prefix('chats')->group(function (): void {
            Route::get('threads', [ChatController::class, 'threads']);
            Route::post('threads', [ChatController::class, 'createThread']);
            Route::delete('threads/{threadId}', [ChatController::class, 'deleteThread']);
            Route::get('threads/{threadId}/messages', [ChatController::class, 'messages']);
            Route::post('threads/{threadId}/messages', [ChatController::class, 'sendMessage'])->middleware('throttle:chat-messages');
            Route::delete('threads/{threadId}/messages/{messageId}', [ChatController::class, 'deleteMessage']);
            Route::post('{id}/report-message', [ChatController::class, 'reportMessage']);
            Route::get('{id}/settings', [ChatController::class, 'getSettings']);
            Route::patch('{id}/settings', [ChatController::class, 'updateSettings'])->middleware('can:chat.moderate');
            Route::get('complaints', [ChatController::class, 'complaints'])->middleware('can:chat.moderate');
            Route::post('complaints/{id}/review', [ChatController::class, 'reviewComplaint'])->middleware('can:chat.moderate');
        });

        // Tickets
        Route::prefix('tickets')->group(function (): void {
            Route::get('/', [TicketController::class, 'index']);
            Route::post('/', [TicketController::class, 'store'])->middleware('can:tickets.create');
            Route::get('sla/compliance', [TicketController::class, 'slaCompliance']);
            Route::get('sla/report', [TicketController::class, 'slaReport'])->middleware('can:tickets.manage');
            Route::get('{id}', [TicketController::class, 'show']);
            Route::put('{id}', [TicketController::class, 'update'])->middleware('can:tickets.manage');
            Route::post('{id}/assign', [TicketController::class, 'assign'])->middleware('can:tickets.manage');
            Route::post('{id}/priority', [TicketController::class, 'changePriority'])->middleware('can:tickets.manage');
            Route::get('{id}/sla', [TicketController::class, 'ticketSlaReport']);
            Route::get('{id}/messages', [TicketController::class, 'messages']);
            Route::post('{id}/messages', [TicketController::class, 'sendMessage']);
        });

        // Rules
        Route::prefix('rules')->middleware('can:rules.manage')->group(function (): void {
            Route::get('/', [RuleController::class, 'index']);
            Route::post('/', [RuleController::class, 'store']);
            Route::post('validate', [RuleController::class, 'validate']);
            Route::get('{id}', [RuleController::class, 'show']);
            Route::put('{id}', [RuleController::class, 'update']);
            Route::delete('{id}', [RuleController::class, 'destroy']);
            Route::post('{id}/toggle', [RuleController::class, 'toggle']);
            Route::post('{id}/test', [RuleController::class, 'test']);
        });

        // Analytics
        Route::prefix('analytics')->middleware('can:analytics.view')->group(function (): void {
            Route::get('/', [AnalyticsController::class, 'index']);
            Route::get('risks', [AnalyticsController::class, 'risks']);
            Route::get('risks/my', [AnalyticsController::class, 'myRisks']);
            Route::get('risks/student/{studentId}', [AnalyticsController::class, 'studentRisk']);
            Route::get('risks/group/{groupId}', [AnalyticsController::class, 'groupRisk']);
            Route::get('risks/report', [AnalyticsController::class, 'risksReport']);
            Route::get('risks/trends', [AnalyticsController::class, 'riskTrends']);
            Route::get('risks/distribution', [AnalyticsController::class, 'riskDistribution']);
            Route::get('risks/entity/{entityType}/{entityId}', [AnalyticsController::class, 'entityRisks']);
            Route::post('risks/{riskId}/resolve', [AnalyticsController::class, 'resolveRisk'])->middleware('can:analytics.manage');
            Route::get('risks/export', [AnalyticsController::class, 'exportRisksReport']);
            Route::get('topic-performance', [AnalyticsController::class, 'topicPerformance']);
            Route::get('topics', [AnalyticsController::class, 'topics']);
            Route::get('topics/{statId}/students', [AnalyticsController::class, 'topicStudents']);
        });

        // Admin Roles
        Route::get('admin/roles', [RolesController::class, 'index'])->middleware('can:users.read');

        // Admin Terms (references)
        Route::get('admin/terms', [\App\Http\Controllers\Api\Admin\TermsController::class, 'index']);

        // Admin Users CRUD (only users.read permission)
        Route::prefix('admin/users')->middleware('can:users.read')->group(function (): void {
            Route::get('/', [UsersController::class, 'index']);
            Route::post('/', [UsersController::class, 'store'])->middleware('can:users.create');
            Route::get('{id}', [UsersController::class, 'show']);
            Route::patch('{id}', [UsersController::class, 'update'])->middleware('can:users.update');
            Route::delete('{id}', [UsersController::class, 'destroy'])->middleware('can:users.delete');
            Route::post('{id}/roles', [UsersController::class, 'assignRoles'])->middleware('can:roles.assign');
        });

        // Admin Import/Export
        Route::prefix('admin/import')->middleware('role:admin')->group(function (): void {
            Route::post('users-xlsx', [ImportController::class, 'importUsers']);
            Route::post('schedule-xlsx', [ImportController::class, 'importSchedule']);
            Route::post('grades-xlsx', [ImportController::class, 'importGrades']);
            Route::post('attendance-xlsx', [ImportController::class, 'importAttendance']);
            Route::post('ktp-xlsx', [ImportController::class, 'importKtp']);
        });

        Route::prefix('admin/export')->middleware('role:admin')->group(function (): void {
            Route::get('users-xlsx', [ExportController::class, 'exportUsers']);
            Route::get('schedule-xlsx', [ExportController::class, 'exportSchedule']);
        });

        // Admin Audit
        Route::prefix('admin/audit')->middleware('role:admin')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\Admin\AdminAuditController::class, 'index']);
            Route::get('export', [\App\Http\Controllers\Api\Admin\AdminAuditController::class, 'export']);
            Route::get('{id}', [\App\Http\Controllers\Api\Admin\AdminAuditController::class, 'show']);
        });

        // Admin Webhooks
        Route::prefix('admin/webhooks')->middleware('role:admin')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\Admin\WebhookController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Admin\WebhookController::class, 'store']);
            Route::get('{id}', [\App\Http\Controllers\Api\Admin\WebhookController::class, 'show']);
            Route::patch('{id}', [\App\Http\Controllers\Api\Admin\WebhookController::class, 'update']);
            Route::delete('{id}', [\App\Http\Controllers\Api\Admin\WebhookController::class, 'destroy']);
        });

        // Reports (PDF generation)
        Route::prefix('reports')->middleware('can:reports.view')->group(function (): void {
            Route::get('journal', [\App\Http\Controllers\Api\V1\ReportController::class, 'journal']);
            Route::get('schedule', [\App\Http\Controllers\Api\V1\ReportController::class, 'schedule']);
            Route::get('grade-sheet', [\App\Http\Controllers\Api\V1\ReportController::class, 'gradeSheet']);
            Route::get('order', [\App\Http\Controllers\Api\V1\ReportController::class, 'order']);
        });

        // Admin Tenants
        Route::prefix('admin/tenants')->middleware('role:admin')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\Admin\TenantController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Admin\TenantController::class, 'store']);
            Route::get('{id}', [\App\Http\Controllers\Api\Admin\TenantController::class, 'show']);
            Route::patch('{id}', [\App\Http\Controllers\Api\Admin\TenantController::class, 'update']);
            Route::delete('{id}', [\App\Http\Controllers\Api\Admin\TenantController::class, 'destroy']);
            Route::post('{id}/switch', [\App\Http\Controllers\Api\Admin\TenantSwitchController::class, 'switch']);
        });

        // Admin Settings
        Route::prefix('admin/settings')->middleware('can:analytics.view')->group(function (): void {
            Route::get('/', [SettingsController::class, 'index']);
            Route::post('/', [SettingsController::class, 'store'])->middleware('can:directory.manage');
        });

        // Admin Rules CRUD (only rules.manage permission)
        Route::prefix('admin/rules')->middleware('can:rules.manage')->group(function (): void {
            Route::get('/', [AdminRulesController::class, 'index']);
            Route::post('/', [AdminRulesController::class, 'store']);
            Route::patch('{id}', [AdminRulesController::class, 'update']);
            Route::delete('{id}', [AdminRulesController::class, 'destroy']);
            Route::post('{id}/toggle', [AdminRulesController::class, 'toggle']);
        });

        // Admin Analytics
        Route::prefix('admin/analytics')->middleware('can:analytics.manage')->group(function (): void {
            Route::post('recalc', [AnalyticsController::class, 'recalc']);
        });

        // Control Panels
        Route::prefix('panels')->middleware('auth:api')->group(function (): void {
            Route::get('curator', [\App\Http\Controllers\Api\V1\ControlPanelController::class, 'curator']);
            Route::get('methodist', [\App\Http\Controllers\Api\V1\ControlPanelController::class, 'methodist']);
            Route::get('principal', [\App\Http\Controllers\Api\V1\ControlPanelController::class, 'principal']);
        });

        // Journal Bulk
        Route::prefix('journal')->middleware('auth:api')->group(function (): void {
            Route::post('bulk/grades', [\App\Http\Controllers\Api\V1\JournalBulkController::class, 'bulkUpdateGrades']);
            Route::post('bulk/attendance', [\App\Http\Controllers\Api\V1\JournalBulkController::class, 'bulkUpdateAttendance']);
        });

        // Journal Grid
        Route::prefix('journal')->middleware('auth:api')->group(function (): void {
            Route::get('grid', [\App\Http\Controllers\Api\V1\JournalGridController::class, 'index']);
            Route::post('grid/save', [\App\Http\Controllers\Api\V1\JournalGridController::class, 'save']);
        });

        // Data Import
        Route::prefix('import')->middleware('auth:api')->group(function (): void {
            Route::post('users', [\App\Http\Controllers\Api\V1\DataImportController::class, 'importUsers']);
            Route::post('groups', [\App\Http\Controllers\Api\V1\DataImportController::class, 'importGroups']);
            Route::post('schedule', [\App\Http\Controllers\Api\V1\DataImportController::class, 'importSchedule']);
            Route::post('grades', [\App\Http\Controllers\Api\V1\DataImportController::class, 'importGrades']);
        });

        // Audit
        Route::prefix('audit')->middleware('auth:api')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\V1\AuditController::class, 'index']);
            Route::get('/export', [\App\Http\Controllers\Api\V1\AuditController::class, 'export']);
        });

        // GOST Documents
        Route::prefix('gost-documents')->middleware('auth:api')->group(function (): void {
            Route::get('templates', [\App\Http\Controllers\Api\V1\GostDocumentController::class, 'templates']);
            Route::post('orders', [\App\Http\Controllers\Api\V1\GostDocumentController::class, 'generateOrder']);
            Route::post('decrees', [\App\Http\Controllers\Api\V1\GostDocumentController::class, 'generateDecree']);
            Route::get('{id}', [\App\Http\Controllers\Api\V1\GostDocumentController::class, 'show']);
        });

        // WebSocket ACK & Replay
        Route::prefix('websocket')->middleware('auth:api')->group(function (): void {
            Route::post('ack', [\App\Http\Controllers\Api\V1\WebSocketController::class, 'acknowledge']);
            Route::post('ack-batch', [\App\Http\Controllers\Api\V1\WebSocketController::class, 'acknowledgeBatch']);
            Route::get('replay', [\App\Http\Controllers\Api\V1\WebSocketController::class, 'replay']);
            Route::post('replay-trigger', [\App\Http\Controllers\Api\V1\WebSocketController::class, 'triggerReplay']);
            
            // New WS event delivery endpoints
            Route::post('ws-ack', [\App\Http\Controllers\Api\V1\WebSocketController::class, 'wsAcknowledge']);
            Route::get('ws-replay', [\App\Http\Controllers\Api\V1\WebSocketController::class, 'wsReplay']);
        });

        // Admin Tickets
        Route::prefix('admin/tickets')->middleware('can:tickets.manage')->group(function (): void {
            Route::get('overdue', [AdminTicketsController::class, 'overdue']);
        });

        // Admin Chat Moderation
        Route::prefix('admin/chats')->middleware('can:chat.moderate')->group(function (): void {
            Route::get('reports', [\App\Http\Controllers\Api\Admin\ChatController::class, 'reports']);
            Route::patch('reports/{id}', [\App\Http\Controllers\Api\Admin\ChatController::class, 'updateReport']);
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
