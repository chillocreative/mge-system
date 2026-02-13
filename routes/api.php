<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EnvironmentalController;

use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DesignationController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InternalEmailController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectDocumentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SafetyController;
use App\Http\Controllers\Api\SiteLogController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (stateful session but no auth required)
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Auth — available to all authenticated users
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Broadcasting auth endpoint for private/presence channels
    Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
        return \Illuminate\Support\Facades\Broadcast::auth($request);
    });

    // Dashboard — all roles can view, stats scoped by permission in controller
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view');

    /*
    |----------------------------------------------------------------------
    | Projects — full CRUD for "Projects" and "Admin & HR" roles
    |            read-only for "Finances & HR"
    |----------------------------------------------------------------------
    */
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])
            ->middleware('permission:projects.view');
        Route::post('/', [ProjectController::class, 'store'])
            ->middleware('permission:projects.create');
        Route::get('/{project}', [ProjectController::class, 'show'])
            ->middleware('permission:projects.view');
        Route::put('/{project}', [ProjectController::class, 'update'])
            ->middleware('permission:projects.edit');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])
            ->middleware('permission:projects.delete');

        // Milestones — nested under projects
        Route::prefix('{project}/milestones')->middleware('permission:projects.view')->group(function () {
            Route::get('/', [MilestoneController::class, 'index']);
            Route::post('/', [MilestoneController::class, 'store'])
                ->middleware('permission:projects.edit');
            Route::get('/{milestone}', [MilestoneController::class, 'show']);
            Route::put('/{milestone}', [MilestoneController::class, 'update'])
                ->middleware('permission:projects.edit');
            Route::delete('/{milestone}', [MilestoneController::class, 'destroy'])
                ->middleware('permission:projects.edit');
        });

        // Site Logs — nested under projects
        Route::prefix('{project}/site-logs')->middleware('permission:projects.view')->group(function () {
            Route::get('/', [SiteLogController::class, 'index']);
            Route::post('/', [SiteLogController::class, 'store'])
                ->middleware('permission:projects.edit');
            Route::get('/{siteLog}', [SiteLogController::class, 'show']);
            Route::put('/{siteLog}', [SiteLogController::class, 'update'])
                ->middleware('permission:projects.edit');
            Route::delete('/{siteLog}', [SiteLogController::class, 'destroy'])
                ->middleware('permission:projects.edit');
        });

        // Documents — nested under projects
        Route::prefix('{project}/documents')->middleware('permission:projects.view')->group(function () {
            Route::get('/', [ProjectDocumentController::class, 'index']);
            Route::post('/', [ProjectDocumentController::class, 'store'])
                ->middleware('permission:projects.edit');
            Route::get('/{document}', [ProjectDocumentController::class, 'show']);
            Route::get('/{document}/download', [ProjectDocumentController::class, 'download']);
            Route::delete('/{document}', [ProjectDocumentController::class, 'destroy'])
                ->middleware('permission:projects.edit');
        });

        // Calendar Events — nested under projects
        Route::prefix('{project}/events')->middleware('permission:projects.view')->group(function () {
            Route::get('/', [CalendarEventController::class, 'index']);
            Route::post('/', [CalendarEventController::class, 'store'])
                ->middleware('permission:projects.edit');
            Route::get('/{event}', [CalendarEventController::class, 'show']);
            Route::put('/{event}', [CalendarEventController::class, 'update'])
                ->middleware('permission:projects.edit');
            Route::delete('/{event}', [CalendarEventController::class, 'destroy'])
                ->middleware('permission:projects.edit');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Tasks — full CRUD for "Projects" and "Admin & HR"
    |          read-only for "Finances & HR"
    |----------------------------------------------------------------------
    */
    Route::prefix('tasks')->group(function () {
        Route::get('/', [TaskController::class, 'index'])
            ->middleware('permission:tasks.view');
        Route::post('/', [TaskController::class, 'store'])
            ->middleware('permission:tasks.create');
        Route::get('/{task}', [TaskController::class, 'show'])
            ->middleware('permission:tasks.view');
        Route::put('/{task}', [TaskController::class, 'update'])
            ->middleware('permission:tasks.edit');
        Route::delete('/{task}', [TaskController::class, 'destroy'])
            ->middleware('permission:tasks.delete');
    });

    /*
    |----------------------------------------------------------------------
    | Clients — full CRUD for "Projects" and "Admin & HR"
    |            read-only for "Finances & HR"
    |----------------------------------------------------------------------
    */
    Route::prefix('clients')->group(function () {
        Route::get('/', [ClientController::class, 'index'])
            ->middleware('permission:clients.view');
        Route::post('/', [ClientController::class, 'store'])
            ->middleware('permission:clients.create');
        Route::get('/{client}', [ClientController::class, 'show'])
            ->middleware('permission:clients.view');
        Route::put('/{client}', [ClientController::class, 'update'])
            ->middleware('permission:clients.edit');
        Route::delete('/{client}', [ClientController::class, 'destroy'])
            ->middleware('permission:clients.delete');
    });

    /*
    |----------------------------------------------------------------------
    | Users — full CRUD for "Admin & HR" and "Finances & HR"
    |          read-only for "Projects"
    |----------------------------------------------------------------------
    */
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission:users.view');
        Route::post('/', [UserController::class, 'store'])
            ->middleware('permission:users.create');
        Route::get('/{user}', [UserController::class, 'show'])
            ->middleware('permission:users.view');
        Route::put('/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.edit');
        Route::delete('/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete');
    });

    /*
    |----------------------------------------------------------------------
    | Departments — full CRUD for "Admin & HR" and "Finances & HR"
    |                read-only for "Projects"
    |----------------------------------------------------------------------
    */
    Route::prefix('departments')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])
            ->middleware('permission:departments.view');
        Route::post('/', [DepartmentController::class, 'store'])
            ->middleware('permission:departments.create');
        Route::get('/{department}', [DepartmentController::class, 'show'])
            ->middleware('permission:departments.view');
        Route::put('/{department}', [DepartmentController::class, 'update'])
            ->middleware('permission:departments.edit');
        Route::delete('/{department}', [DepartmentController::class, 'destroy'])
            ->middleware('permission:departments.delete');
    });

    /*
    |----------------------------------------------------------------------
    | Designations — full CRUD for "Admin & HR" and "Finances & HR"
    |                  read-only for "Projects"
    |----------------------------------------------------------------------
    */
    Route::prefix('designations')->group(function () {
        Route::get('/', [DesignationController::class, 'index'])
            ->middleware('permission:designations.view');
        Route::post('/', [DesignationController::class, 'store'])
            ->middleware('permission:designations.create');
        Route::get('/{designation}', [DesignationController::class, 'show'])
            ->middleware('permission:designations.view');
        Route::put('/{designation}', [DesignationController::class, 'update'])
            ->middleware('permission:designations.edit');
        Route::delete('/{designation}', [DesignationController::class, 'destroy'])
            ->middleware('permission:designations.delete');
    });

    /*
    |----------------------------------------------------------------------
    | Roles & Permissions — "Admin & HR" only
    |----------------------------------------------------------------------
    */
    Route::prefix('roles')->middleware('permission:roles.view')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store'])
            ->middleware('permission:roles.create');
        Route::get('/{role}', [RoleController::class, 'show']);
        Route::put('/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.edit');
        Route::delete('/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles.delete');
    });

    Route::get('/permissions', [RoleController::class, 'permissions'])
        ->middleware('permission:roles.view');

    /*
    |----------------------------------------------------------------------
    | Attendance — upload, view, manage attendance records
    |              "Admin & HR" and "Finances & HR" can upload/manage
    |----------------------------------------------------------------------
    */
    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])
            ->middleware('permission:attendance.view');
        Route::post('/upload', [AttendanceController::class, 'upload'])
            ->middleware('permission:attendance.upload');
        Route::get('/summary', [AttendanceController::class, 'summary'])
            ->middleware('permission:attendance.view');
        Route::get('/uploads', [AttendanceController::class, 'uploadHistory'])
            ->middleware('permission:attendance.view');
        Route::get('/{attendance}', [AttendanceController::class, 'show'])
            ->middleware('permission:attendance.view');
        Route::delete('/batch/{batch}', [AttendanceController::class, 'deleteBatch'])
            ->middleware('permission:attendance.delete');
    });

    /*
    |----------------------------------------------------------------------
    | Payroll — generate, approve, manage payroll records
    |            "Admin & HR" and "Finances & HR" can manage
    |----------------------------------------------------------------------
    */
    Route::prefix('payroll')->group(function () {
        Route::get('/', [AttendanceController::class, 'payrollIndex'])
            ->middleware('permission:payroll.view');
        Route::post('/generate', [AttendanceController::class, 'generatePayroll'])
            ->middleware('permission:payroll.generate');
        Route::get('/summary', [AttendanceController::class, 'payrollSummary'])
            ->middleware('permission:payroll.view');
        Route::get('/config', [AttendanceController::class, 'payrollConfig'])
            ->middleware('permission:payroll.view');
        Route::get('/{payroll}', [AttendanceController::class, 'payrollShow'])
            ->middleware('permission:payroll.view');
        Route::patch('/{payroll}/approve', [AttendanceController::class, 'payrollApprove'])
            ->middleware('permission:payroll.approve');
        Route::patch('/{payroll}/mark-paid', [AttendanceController::class, 'payrollMarkPaid'])
            ->middleware('permission:payroll.approve');
    });

    /*
    |----------------------------------------------------------------------
    | Finance — Invoices, Expenses, Financial Reports
    |            "Admin & HR" and "Finances & HR" have full access
    |            "Projects" has view-only access
    |----------------------------------------------------------------------
    */

    // Finance overview & reports
    Route::prefix('finance')->middleware('permission:finance.view')->group(function () {
        Route::get('/overview', [FinanceController::class, 'overview']);
        Route::get('/monthly-summary', [FinanceController::class, 'monthlySummary']);
        Route::get('/budget-vs-actual', [FinanceController::class, 'budgetVsActual']);
    });

    // Invoices
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])
            ->middleware('permission:finance.view');
        Route::post('/', [InvoiceController::class, 'store'])
            ->middleware('permission:finance.manage-budgets');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])
            ->middleware('permission:finance.view');
        Route::put('/{invoice}', [InvoiceController::class, 'update'])
            ->middleware('permission:finance.manage-budgets');
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])
            ->middleware('permission:finance.manage-budgets');
        Route::post('/{invoice}/payments', [InvoiceController::class, 'recordPayment'])
            ->middleware('permission:finance.manage-budgets');
        Route::patch('/{invoice}/mark-sent', [InvoiceController::class, 'markAsSent'])
            ->middleware('permission:finance.manage-budgets');
        Route::get('/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])
            ->middleware('permission:finance.view');
        Route::get('/{invoice}/pdf/preview', [InvoiceController::class, 'previewPdf'])
            ->middleware('permission:finance.view');
    });

    // Expenses
    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])
            ->middleware('permission:finance.view');
        Route::post('/', [ExpenseController::class, 'store'])
            ->middleware('permission:finance.manage-budgets');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])
            ->middleware('permission:finance.manage-budgets');
        Route::patch('/{expense}/approve', [ExpenseController::class, 'approve'])
            ->middleware('permission:finance.approve-expenses');
        Route::patch('/{expense}/reject', [ExpenseController::class, 'reject'])
            ->middleware('permission:finance.approve-expenses');
    });

    /*
    |----------------------------------------------------------------------
    | Safety (OSHA) — Incidents, Hazards, Meetings, Checklists
    |                 "Admin & HR" and "Projects" have full access
    |                 "Finances & HR" has view-only access
    |----------------------------------------------------------------------
    */

    // Safety overview
    Route::get('/safety/overview', [SafetyController::class, 'overview'])
        ->middleware('permission:safety.view');

    // Incidents
    Route::prefix('safety/incidents')->group(function () {
        Route::get('/', [SafetyController::class, 'incidents'])
            ->middleware('permission:safety.view');
        Route::post('/', [SafetyController::class, 'storeIncident'])
            ->middleware('permission:safety.create');
        Route::get('/{id}', [SafetyController::class, 'showIncident'])
            ->middleware('permission:safety.view');
        Route::put('/{id}', [SafetyController::class, 'updateIncident'])
            ->middleware('permission:safety.manage');
        Route::get('/{id}/pdf', [SafetyController::class, 'incidentPdf'])
            ->middleware('permission:safety.view');
    });

    // Hazards
    Route::prefix('safety/hazards')->group(function () {
        Route::get('/', [SafetyController::class, 'hazards'])
            ->middleware('permission:safety.view');
        Route::post('/', [SafetyController::class, 'storeHazard'])
            ->middleware('permission:safety.create');
        Route::put('/{id}', [SafetyController::class, 'updateHazard'])
            ->middleware('permission:safety.manage');
        Route::get('/{id}/pdf', [SafetyController::class, 'hazardPdf'])
            ->middleware('permission:safety.view');
    });

    // Toolbox Meetings
    Route::prefix('safety/meetings')->group(function () {
        Route::get('/', [SafetyController::class, 'meetings'])
            ->middleware('permission:safety.view');
        Route::post('/', [SafetyController::class, 'storeMeeting'])
            ->middleware('permission:safety.create');
        Route::get('/{id}', [SafetyController::class, 'showMeeting'])
            ->middleware('permission:safety.view');
        Route::get('/{id}/pdf', [SafetyController::class, 'meetingPdf'])
            ->middleware('permission:safety.view');
    });

    // Compliance Checklists
    Route::prefix('safety/checklists')->group(function () {
        Route::get('/', [SafetyController::class, 'checklists'])
            ->middleware('permission:safety.view');
        Route::post('/', [SafetyController::class, 'storeChecklist'])
            ->middleware('permission:safety.create');
        Route::get('/{id}', [SafetyController::class, 'showChecklist'])
            ->middleware('permission:safety.view');
        Route::get('/{id}/pdf', [SafetyController::class, 'checklistPdf'])
            ->middleware('permission:safety.view');
    });

    // Safety photo uploads
    Route::post('/safety/{type}/{id}/photos', [SafetyController::class, 'uploadPhotos'])
        ->middleware('permission:safety.create');

    /*
    |----------------------------------------------------------------------
    | Environmental — Waste Tracking, Site Inspections, Audits
    |                 "Admin & HR" and "Projects" have full access
    |                 "Finances & HR" has view-only access
    |----------------------------------------------------------------------
    */

    // Environmental overview
    Route::get('/environmental/overview', [EnvironmentalController::class, 'overview'])
        ->middleware('permission:environmental.view');

    // Waste Records
    Route::prefix('environmental/waste')->group(function () {
        Route::get('/', [EnvironmentalController::class, 'wasteRecords'])
            ->middleware('permission:environmental.view');
        Route::post('/', [EnvironmentalController::class, 'storeWaste'])
            ->middleware('permission:environmental.create');
        Route::put('/{id}', [EnvironmentalController::class, 'updateWaste'])
            ->middleware('permission:environmental.manage');
    });

    // Site Inspections
    Route::prefix('environmental/inspections')->group(function () {
        Route::get('/', [EnvironmentalController::class, 'inspections'])
            ->middleware('permission:environmental.view');
        Route::post('/', [EnvironmentalController::class, 'storeInspection'])
            ->middleware('permission:environmental.create');
        Route::get('/{id}', [EnvironmentalController::class, 'showInspection'])
            ->middleware('permission:environmental.view');
        Route::put('/{id}', [EnvironmentalController::class, 'updateInspection'])
            ->middleware('permission:environmental.manage');
        Route::get('/{id}/pdf', [EnvironmentalController::class, 'inspectionPdf'])
            ->middleware('permission:environmental.view');
    });

    // Environmental Audits
    Route::prefix('environmental/audits')->group(function () {
        Route::get('/', [EnvironmentalController::class, 'audits'])
            ->middleware('permission:environmental.view');
        Route::post('/', [EnvironmentalController::class, 'storeAudit'])
            ->middleware('permission:environmental.create');
        Route::get('/{id}', [EnvironmentalController::class, 'showAudit'])
            ->middleware('permission:environmental.view');
        Route::put('/{id}', [EnvironmentalController::class, 'updateAudit'])
            ->middleware('permission:environmental.manage');
        Route::get('/{id}/pdf', [EnvironmentalController::class, 'auditPdf'])
            ->middleware('permission:environmental.view');
    });

    // Environmental photo uploads
    Route::post('/environmental/{type}/{id}/photos', [EnvironmentalController::class, 'uploadPhotos'])
        ->middleware('permission:environmental.create');

    /*
    |----------------------------------------------------------------------
    | Chat — real-time messaging (all authenticated users)
    |----------------------------------------------------------------------
    */
    Route::prefix('chat')->group(function () {
        Route::get('/rooms', [ChatController::class, 'rooms']);
        Route::post('/rooms/private', [ChatController::class, 'privateRoom']);
        Route::post('/rooms/group', [ChatController::class, 'createGroup']);
        Route::get('/rooms/{roomId}/messages', [ChatController::class, 'messages']);
        Route::post('/rooms/{roomId}/messages', [ChatController::class, 'sendMessage']);
        Route::post('/rooms/{roomId}/files', [ChatController::class, 'sendFile']);
        Route::post('/rooms/{roomId}/read', [ChatController::class, 'markRead']);
        Route::get('/unread-count', [ChatController::class, 'unreadCount']);
    });

    /*
    |----------------------------------------------------------------------
    | Internal Email — threaded email with attachments
    |----------------------------------------------------------------------
    */
    Route::prefix('emails')->group(function () {
        Route::get('/', [InternalEmailController::class, 'index']);
        Route::get('/unread-count', [InternalEmailController::class, 'unreadCount']);
        Route::get('/{email}', [InternalEmailController::class, 'show']);
        Route::post('/send', [InternalEmailController::class, 'send']);
        Route::post('/{email}/reply', [InternalEmailController::class, 'reply']);
        Route::post('/drafts', [InternalEmailController::class, 'saveDraft']);
        Route::delete('/drafts/{draft}', [InternalEmailController::class, 'deleteDraft']);
        Route::patch('/{email}/star', [InternalEmailController::class, 'toggleStar']);
        Route::patch('/{email}/trash', [InternalEmailController::class, 'trash']);
        Route::patch('/{email}/restore', [InternalEmailController::class, 'restore']);
        Route::get('/attachments/{attachment}/download', [InternalEmailController::class, 'downloadAttachment']);
    });

    /*
    |----------------------------------------------------------------------
    | Notifications — available to all authenticated users
    |----------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    });
});
