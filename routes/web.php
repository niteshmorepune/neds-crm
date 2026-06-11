<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CallLogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\Portal\HomeController;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Controllers\Portal\SetPasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StubController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TwoFactorSetupController;
use App\Livewire\ClientImport;
use App\Livewire\DealsBoard;
use App\Livewire\MenuManager;
use App\Livewire\QuotationBuilder;
use App\Livewire\RecurringInvoiceBuilder;
use Illuminate\Support\Facades\Route;

// Internal CRM — no public landing page. Send visitors to the right place.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'two-factor'])->name('dashboard');

Route::middleware(['auth', 'two-factor'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
     * Two-factor (TOTP) — Milestone 7 PR C2. Enrolment is self-service from the
     * profile; the challenge gate + admin/manager enforcement live in the
     * RequireTwoFactor middleware. These routes are on its allow-list.
     */
    Route::post('two-factor/enable', [TwoFactorSetupController::class, 'enable'])->name('two-factor.enable');
    Route::post('two-factor/confirm', [TwoFactorSetupController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('two-factor', [TwoFactorSetupController::class, 'disable'])->name('two-factor.disable');
    Route::post('two-factor/recovery-codes', [TwoFactorSetupController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery');

    Route::get('two-factor/challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('two-factor/challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.challenge.store');

    /*
     * Clients (Customers) — Milestone 1. Gated by menu.access:customer (the
     * menu key stays "customer"; the URL/route names use "clients" per the
     * team's UI terminology). The import route is declared before the resource
     * so /clients/import isn't captured by the {client} wildcard.
     */
    Route::middleware('menu.access:customer')->group(function () {
        Route::get('clients/import', ClientImport::class)->name('clients.import');
        Route::resource('clients', CustomerController::class)
            ->parameters(['clients' => 'client']);
    });

    /*
     * Leads — Milestone 2. Gated by menu.access:lead-generation.
     */
    Route::middleware('menu.access:lead-generation')->group(function () {
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
        Route::resource('leads', LeadController::class)->parameters(['leads' => 'lead']);
    });

    /*
     * Deals / pipeline — Milestone 2. Gated by menu.access:sales-department.
     * The Kanban board is a full-page Livewire component.
     */
    Route::middleware('menu.access:sales-department')->group(function () {
        Route::get('deals', DealsBoard::class)->name('deals.index');
        Route::get('deals/{deal}', [DealController::class, 'show'])->name('deals.show');
        Route::put('deals/{deal}', [DealController::class, 'update'])->name('deals.update');
        Route::delete('deals/{deal}', [DealController::class, 'destroy'])->name('deals.destroy');
    });

    /*
     * Quotations — Milestone 3. Gated by menu.access:quotations. Builder is a
     * full-page Livewire component (create + edit).
     */
    Route::middleware('menu.access:quotations')->group(function () {
        Route::get('quotations', [QuotationController::class, 'index'])->name('quotations.index');
        Route::get('quotations/create', QuotationBuilder::class)->name('quotations.create');
        Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::get('quotations/{quotation}/edit', QuotationBuilder::class)->name('quotations.edit');
        Route::post('quotations/{quotation}/status', [QuotationController::class, 'transition'])->name('quotations.status');
        Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');
        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');
    });

    /*
     * Invoices & payments — Milestone 3. Gated by menu.access:invoices.
     */
    Route::middleware('menu.access:invoices')->group(function () {
        // Recurring invoice templates (declared before invoices/{invoice}).
        Route::get('recurring-invoices', [RecurringInvoiceController::class, 'index'])->name('recurring-invoices.index');
        Route::get('recurring-invoices/create', RecurringInvoiceBuilder::class)->name('recurring-invoices.create');
        Route::get('recurring-invoices/{recurring}/edit', RecurringInvoiceBuilder::class)->name('recurring-invoices.edit');
        Route::put('recurring-invoices/{recurring}/toggle', [RecurringInvoiceController::class, 'toggle'])->name('recurring-invoices.toggle');
        Route::delete('recurring-invoices/{recurring}', [RecurringInvoiceController::class, 'destroy'])->name('recurring-invoices.destroy');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
    });

    /*
     * Accounts landing — outstanding receivables report. Gated by menu.access:account.
     */
    Route::middleware('menu.access:account')->group(function () {
        Route::get('account/receivables', [InvoiceController::class, 'receivables'])->name('reports.receivables');
    });

    /*
     * Management reports — Milestone 7. Role-gated inside the controller
     * (Employee Performance: admin/manager; Revenue: admin/manager/accounts);
     * linked from the dashboard rather than the sidebar.
     */
    Route::get('reports/employee-performance', [ReportController::class, 'employeePerformance'])->name('reports.employee-performance');
    Route::get('reports/employee-performance/export', [ReportController::class, 'exportEmployeePerformance'])->name('reports.employee-performance.export');
    Route::get('reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
    Route::get('reports/revenue/export', [ReportController::class, 'exportRevenue'])->name('reports.revenue.export');

    /*
     * Projects — Milestone 4. Gated by menu.access:project-updates.
     */
    Route::middleware('menu.access:project-updates')->group(function () {
        Route::post('projects/from-deal/{deal}', [ProjectController::class, 'storeFromDeal'])->name('projects.from-deal');
        Route::resource('projects', ProjectController::class)->except(['destroy']);
    });

    /*
     * Tasks ("Emptask") — Milestone 4. Gated by menu.access:emptask.
     */
    Route::middleware('menu.access:emptask')->group(function () {
        Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
        Route::post('tasks/{task}/attachments', [TaskController::class, 'storeAttachment'])->name('tasks.attachments.store');
        Route::resource('tasks', TaskController::class);
    });

    /*
     * Tickets — Milestone 4 (PR B). Gated by menu.access:tickets.
     */
    Route::middleware('menu.access:tickets')->group(function () {
        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::patch('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
        Route::post('tickets/{ticket}/resolve', [TicketController::class, 'resolve'])->name('tickets.resolve');
        Route::post('tickets/{ticket}/attachments', [TicketController::class, 'storeAttachment'])->name('tickets.attachments.store');
    });

    // Attachment download/remove (authorized against the parent record).
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    /*
     * Attendance — Milestone 4b. Gated by menu.access:attendance.
     */
    Route::middleware('menu.access:attendance')->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
        Route::get('attendance/corrections', [AttendanceController::class, 'corrections'])->name('attendance.corrections');
        Route::post('attendance/corrections', [AttendanceController::class, 'storeCorrection'])->name('attendance.corrections.store');
    });

    /*
     * Call logs ("Calling") — Milestone 4b. Gated by menu.access:calling.
     */
    Route::middleware('menu.access:calling')->group(function () {
        Route::get('calls', [CallLogController::class, 'index'])->name('calls.index');
        Route::get('calls/create', [CallLogController::class, 'create'])->name('calls.create');
        Route::post('calls', [CallLogController::class, 'store'])->name('calls.store');
    });

    /*
     * Daily reports — Milestone 4b (PR B). Gated by menu.access:daily-reports.
     */
    Route::middleware('menu.access:daily-reports')->group(function () {
        Route::get('daily-reports', [DailyReportController::class, 'index'])->name('daily-reports.index');
        Route::post('daily-reports', [DailyReportController::class, 'store'])->name('daily-reports.store');
        Route::get('daily-reports/team', [DailyReportController::class, 'team'])->name('daily-reports.team');
    });

    /*
     * Milestone 0 stub pages. Each is protected by menu.access:<key>, which
     * enforces role-based access regardless of whether the item shows in the
     * sidebar. Route name == menu key so the sidebar can link via route(key).
     * Real modules replace these in later milestones.
     */
    $stubs = [
        'categories' => '/categories',
    ];

    foreach ($stubs as $key => $path) {
        Route::get($path, StubController::class)
            ->middleware("menu.access:{$key}")
            ->name($key);
    }

    /*
     * Menu Controller admin — Milestone 7. Admin-only (the menu-controller item
     * has no role defaults, so only admin's all-access bypass reaches it).
     */
    Route::get('/menu-controller', MenuManager::class)
        ->middleware('menu.access:menu-controller')
        ->name('menu-controller');

    /*
     * Global search across the core records, scoped to what the user may see.
     */
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    /*
     * Audit log — Milestone 7. Admin-only (enforced in the controller).
     */
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log');
});

/*
 * Customer Portal — Milestone 5. Separate "portal" guard (Contacts). Every
 * authed route is scoped to the contact's own customer (see PortalController).
 */
Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:portal')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'login']);
        Route::get('set-password/{token}', [SetPasswordController::class, 'show'])->name('password.setup');
        Route::post('set-password/{token}', [SetPasswordController::class, 'store'])->name('password.store');
    });

    Route::middleware('auth:portal')->group(function () {
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::get('invoices', [App\Http\Controllers\Portal\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [App\Http\Controllers\Portal\InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [App\Http\Controllers\Portal\InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('projects', [App\Http\Controllers\Portal\ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{project}', [App\Http\Controllers\Portal\ProjectController::class, 'show'])->name('projects.show');

        Route::get('tickets', [App\Http\Controllers\Portal\TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [App\Http\Controllers\Portal\TicketController::class, 'create'])->name('tickets.create');
        Route::post('tickets', [App\Http\Controllers\Portal\TicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [App\Http\Controllers\Portal\TicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [App\Http\Controllers\Portal\TicketController::class, 'reply'])->name('tickets.reply');
    });
});

require __DIR__.'/auth.php';
