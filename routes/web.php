<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ApprovalCenterController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CallLogController;
use App\Http\Controllers\ClientAdvanceController;
use App\Http\Controllers\ClientRadarController;
use App\Http\Controllers\ContentPieceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardWidgetSettingsController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FestivalController;
use App\Http\Controllers\GoogleConnectionController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\IncentiveController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadMergeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\ManagerActionCenterController;
use App\Http\Controllers\MyDayController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PartnerUploadController;
use App\Http\Controllers\Portal\ForgotPasswordController;
use App\Http\Controllers\Portal\HomeController;
use App\Http\Controllers\Portal\InvoicePaymentController;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Controllers\Portal\SetPasswordController;
use App\Http\Controllers\Portal\SsoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectHealthController;
use App\Http\Controllers\QuarterlyAwardController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\RevenueAtRiskController;
use App\Http\Controllers\SalesDashboardController;
use App\Http\Controllers\SalesTargetController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamNudgeController;
use App\Http\Controllers\TeamResourceController;
use App\Http\Controllers\TeamWorkloadController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TwoFactorSetupController;
use App\Http\Controllers\UserController;
use App\Livewire\ClientImport;
use App\Livewire\ContractRenewalDashboard;
use App\Livewire\DealsBoard;
use App\Livewire\HitechAttendanceImport;
use App\Livewire\ManagerCalendar;
use App\Livewire\MenuManager;
use App\Livewire\QuotationBuilder;
use App\Livewire\RecurringInvoiceBuilder;
use Illuminate\Support\Facades\Route;

/*
 * Partner upload — token-based, no login required. Partner receives a link
 * and uploads content files directly; the CRM marks the piece as received.
 */
Route::get('/partner/upload/{token}', [PartnerUploadController::class, 'show'])->name('partner-upload.show');
Route::post('/partner/upload/{token}', [PartnerUploadController::class, 'store'])->name('partner-upload.store');

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

    // Dashboard Customization — self-service, no sidebar item, same
    // "settings page reachable from where it's used, not menu-listed"
    // precedent as Profile → Google Account.
    Route::get('/dashboard/widgets', [DashboardWidgetSettingsController::class, 'edit'])->name('dashboard-widget-settings.edit');
    Route::put('/dashboard/widgets', [DashboardWidgetSettingsController::class, 'update'])->name('dashboard-widget-settings.update');

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
     * Google Meet Notes (Phase 1) — self-service "Connect Google Account"
     * from the profile page. Redirect URI is registered in Google Cloud
     * Console as https://crm.niranjanenterprises.co.in/settings/google/callback
     * — the /settings prefix here is deliberate (a new account-integrations
     * area, distinct from /profile) rather than reconfiguring the already-
     * registered OAuth client.
     */
    Route::get('settings/google/redirect', [GoogleConnectionController::class, 'redirect'])->name('google.redirect');
    Route::get('settings/google/callback', [GoogleConnectionController::class, 'callback'])->name('google.callback');
    Route::delete('settings/google/disconnect', [GoogleConnectionController::class, 'destroy'])->name('google.disconnect');

    /*
     * Clients (Customers) — Milestone 1. Gated by menu.access:customer (the
     * menu key stays "customer"; the URL/route names use "clients" per the
     * team's UI terminology). The import route is declared before the resource
     * so /clients/import isn't captured by the {client} wildcard.
     */
    Route::middleware('menu.access:customer')->group(function () {
        Route::get('clients/import', ClientImport::class)->name('clients.import');
        Route::get('clients/import/template', function () {
            $headers = ['company_name', 'email', 'phone', 'gstin', 'website', 'address_line1', 'address_line2', 'city', 'state_code', 'pincode', 'status', 'owner', 'tags'];
            $sample = ['Acme Pvt Ltd', 'billing@acme.in', '9876543210', '27ABCDE1234F1Z5', 'https://acme.in', '123 MG Road', 'Unit 4', 'Pune', '27', '411001', 'active', 'Kiran Katte', 'seo, retainer'];

            return response()->streamDownload(function () use ($headers, $sample) {
                $f = fopen('php://output', 'w');
                fputcsv($f, $headers);
                fputcsv($f, $sample);
                fclose($f);
            }, 'clients-import-template.csv', ['Content-Type' => 'text/csv']);
        })->name('clients.import.template');
        Route::resource('clients', CustomerController::class)
            ->parameters(['clients' => 'client']);

        // Client advances — money received with no quotation/invoice yet.
        // Policy-gated to accounts team regardless of who can reach this page.
        Route::post('clients/{client}/advances', [ClientAdvanceController::class, 'store'])->name('advances.store');
        Route::post('advances/{advance}/cancel', [ClientAdvanceController::class, 'cancel'])->name('advances.cancel');
    });

    /*
     * Leads — Milestone 2. Gated by menu.access:lead-generation.
     */
    Route::middleware('menu.access:lead-generation')->group(function () {
        // Declared before the resource below so the literal "merge" segment
        // isn't swallowed by the resource's leads/{lead} wildcard.
        Route::get('leads/merge', [LeadMergeController::class, 'show'])->name('leads.merge.show');
        Route::post('leads/merge', [LeadMergeController::class, 'store'])->name('leads.merge.store');
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
        Route::post('leads/{lead}/quotation', [LeadController::class, 'quotation'])->name('leads.quotation');
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
     * Sales Dashboard — owner/rep KPI view built on top of SalesPipelineMetrics
     * (shared with the Kanban board above). Gated by menu.access:sales-dashboard;
     * the rep-leaderboard section and target form inside are additionally
     * restricted to Admin/Manager in the controller.
     */
    Route::middleware('menu.access:sales-dashboard')->group(function () {
        Route::get('sales-dashboard', [SalesDashboardController::class, 'index'])->name('sales-dashboard.index');
        Route::post('sales-dashboard/targets', [SalesTargetController::class, 'store'])->name('sales-dashboard.targets.store');
    });

    /*
     * Sales Incentive — monthly tiered-slab incentive on Won deal value
     * (IncentiveCalculator) + team-pool bonus tied to the existing
     * SalesTarget. Gated by menu.access:incentives; the settings form is
     * additionally restricted to Admin/Manager in the controller.
     */
    Route::middleware('menu.access:incentives')->group(function () {
        Route::get('incentives', [IncentiveController::class, 'index'])->name('incentives.index');
        Route::post('incentives/settings', [IncentiveController::class, 'updateSettings'])->name('incentives.settings.update');
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
        Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
        Route::post('quotations/{quotation}/status', [QuotationController::class, 'transition'])->name('quotations.status');
        Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');
        Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
        Route::post('quotations/{quotation}/request-changes', [QuotationController::class, 'requestChanges'])->name('quotations.request-changes');
        Route::post('quotations/{quotation}/resubmit', [QuotationController::class, 'resubmitForApproval'])->name('quotations.resubmit');
        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');
    });

    /*
     * Contract & Renewal Dashboard — own menu item (contract-renewals),
     * separate from the invoices/recurring-invoices menu.access gate since
     * it's a Manager-panel view: viewing follows InvoicePolicy::viewAny
     * (checked in ContractRenewalDashboard::mount()), same as every other
     * recurring-invoice screen.
     */
    Route::middleware('menu.access:contract-renewals')->group(function () {
        Route::get('contract-renewals', ContractRenewalDashboard::class)->name('contract-renewals.index');
    });

    /*
     * Invoices & payments — Milestone 3. Gated by menu.access:invoices.
     */
    Route::middleware('menu.access:invoices')->group(function () {
        // Recurring invoice templates (declared before invoices/{invoice}).
        Route::get('recurring-invoices', [RecurringInvoiceController::class, 'index'])->name('recurring-invoices.index');
        Route::get('recurring-invoices/create', RecurringInvoiceBuilder::class)->name('recurring-invoices.create');
        Route::get('recurring-invoices/{recurring}', [RecurringInvoiceController::class, 'show'])->name('recurring-invoices.show');
        Route::post('recurring-invoices/{recurring}/generate-now', [RecurringInvoiceController::class, 'generateNow'])->name('recurring-invoices.generate-now');
        Route::get('recurring-invoices/{recurring}/edit', RecurringInvoiceBuilder::class)->name('recurring-invoices.edit');
        Route::put('recurring-invoices/{recurring}/toggle', [RecurringInvoiceController::class, 'toggle'])->name('recurring-invoices.toggle');
        Route::delete('recurring-invoices/{recurring}', [RecurringInvoiceController::class, 'destroy'])->name('recurring-invoices.destroy');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/import', [InvoiceController::class, 'import'])->name('invoices.import');
        Route::post('invoices/import', [InvoiceController::class, 'importStore'])->name('invoices.import.store');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/assign-number', [InvoiceController::class, 'assignNumber'])->name('invoices.assign-number');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
        Route::patch('invoices/{invoice}/payments/{payment}', [InvoiceController::class, 'updatePayment'])->name('invoices.payments.update');
        Route::post('invoices/{invoice}/advances/{advance}/apply', [ClientAdvanceController::class, 'apply'])->name('invoices.advances.apply');
        Route::post('invoices/{invoice}/payment-promise', [InvoiceController::class, 'updatePaymentPromise'])->name('invoices.payment-promise.update');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    });

    /*
     * Accounts landing — outstanding receivables report. Gated by menu.access:account.
     */
    Route::middleware('menu.access:account')->group(function () {
        Route::get('account/receivables', [InvoiceController::class, 'receivables'])->name('reports.receivables');
        Route::get('account/collected', [InvoiceController::class, 'collectedThisMonth'])->name('reports.collected');
        Route::get('account/advances', [ClientAdvanceController::class, 'index'])->name('reports.advances');
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
    Route::get('reports/lead-sources', [ReportController::class, 'leadSources'])->name('reports.lead-sources');
    Route::get('reports/lead-sources/export', [ReportController::class, 'exportLeadSources'])->name('reports.lead-sources.export');
    Route::get('reports/ai-usage', [ReportController::class, 'aiUsage'])->name('reports.ai-usage');
    Route::get('reports/ai-usage/export', [ReportController::class, 'exportAiUsage'])->name('reports.ai-usage.export');
    Route::post('reports/ai-usage/settings', [ReportController::class, 'updateAiUsageSettings'])->name('reports.ai-usage.settings.update');
    Route::get('reports/ask', [ReportController::class, 'askTheCrm'])->name('reports.ask');
    Route::get('reports/business-overview', [ReportController::class, 'businessOverview'])->name('reports.business-overview');
    Route::get('reports/business-overview/export', [ReportController::class, 'exportBusinessOverview'])->name('reports.business-overview.export');
    Route::get('reports/cash-forecast', [ReportController::class, 'cashForecast'])->name('reports.cash-forecast');
    Route::get('reports/weekly-digests', [ReportController::class, 'weeklyDigests'])->name('reports.weekly-digests');

    /*
     * Collections & delivery tracking — partner-wise + direct-client
     * receivables and milestone billing readiness. Gated by menu.access:collections.
     */
    Route::middleware('menu.access:collections')->group(function () {
        Route::get('reports/collections', [ReportController::class, 'collections'])->name('reports.collections');
    });

    /*
     * Partners — content agency collaborators. Admin/manager only (menu.access:partners).
     */
    Route::middleware('menu.access:partners')->group(function () {
        Route::resource('partners', PartnerController::class);
        Route::post('partners/{partner}/invite', [PartnerController::class, 'invite'])->name('partners.invite');
        Route::post('partners/{partner}/revoke', [PartnerController::class, 'revoke'])->name('partners.revoke');
        Route::post('partners/{partner}/commission-statements/{statement}/mark-paid', [PartnerController::class, 'markCommissionPaid'])->name('partners.commission-statements.mark-paid');
    });

    /*
     * Notice Board — time-bound staff/client announcements. Admin/manager
     * only (menu.access:announcements). Display on the Dashboard/Portal home
     * is unrestricted (audience-filtered), only authoring is gated here.
     */
    Route::middleware('menu.access:announcements')->group(function () {
        Route::resource('announcements', AnnouncementController::class)->except('show');
    });

    /*
     * Team Nudges — reusable, targeted reminders shown on each staff member's
     * own Dashboard. Admin/manager only (menu.access:team-nudges) for
     * authoring + the completion overview; every user acts on their own
     * status rows via App\Livewire\MyTeamNudges, embedded on the Dashboard
     * regardless of this gate.
     */
    Route::middleware('menu.access:team-nudges')->group(function () {
        Route::resource('team-nudges', TeamNudgeController::class)->except('show');
    });

    /*
     * Best Employee of the Quarter — Admin/Manager get the full review
     * queue on this same index page (App\Livewire\QuarterlyAwardReview);
     * everyone else sees only their own approved awards.
     */
    Route::middleware('menu.access:quarterly-awards')->group(function () {
        Route::get('quarterly-awards', [QuarterlyAwardController::class, 'index'])->name('quarterly-awards.index');
        Route::post('quarterly-awards/regenerate', [QuarterlyAwardController::class, 'regenerate'])->name('quarterly-awards.regenerate');
        Route::get('quarterly-awards/{award}/certificate', [QuarterlyAwardController::class, 'certificate'])->name('quarterly-awards.certificate');
    });

    /*
     * Subscriptions — internal tool/vendor renewal tracker. Admin-only
     * (menu.access:subscriptions), narrower than most admin-ish modules.
     */
    Route::middleware('menu.access:subscriptions')->group(function () {
        Route::resource('subscriptions', SubscriptionController::class)->except('show');
    });

    /*
     * Expenses — daily office expense tracker (tea, travel, stationery,
     * internet, fuel, ...). Admin/Manager/Accounts (menu.access:expenses).
     */
    Route::middleware('menu.access:expenses')->group(function () {
        Route::resource('expenses', ExpenseController::class)->except('show');
    });

    /*
     * Projects — Milestone 4. Gated by menu.access:project-updates.
     */
    Route::middleware('menu.access:project-updates')->group(function () {
        Route::post('projects/from-deal/{deal}', [ProjectController::class, 'storeFromDeal'])->name('projects.from-deal');
        Route::resource('projects', ProjectController::class);

        /*
         * Content pieces nested under projects (shallow). Custom actions declared
         * before the resource so they aren't captured by {content_piece} wildcard.
         */
        Route::post('projects/{project}/content/{content_piece}/upload-link', [ContentPieceController::class, 'generateUploadLink'])
            ->name('projects.content.upload-link');
        Route::patch('projects/{project}/content/{content_piece}/advance', [ContentPieceController::class, 'advance'])
            ->name('projects.content.advance');
        Route::resource('projects.content', ContentPieceController::class)
            ->shallow()
            ->parameters(['content' => 'content_piece']);
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
        Route::post('tickets/{ticket}/escalate', [TicketController::class, 'escalate'])->name('tickets.escalate');
        Route::delete('tickets/{ticket}/escalate', [TicketController::class, 'clearEscalation'])->name('tickets.escalate.clear');
        Route::post('tickets/{ticket}/attachments', [TicketController::class, 'storeAttachment'])->name('tickets.attachments.store');
    });

    // Attachment download/remove (authorized against the parent record).
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    /*
     * My Day — consolidated personal worklist (tasks, follow-ups, calls,
     * SLA-breached tickets). Gated by menu.access:my-day. Always scoped to
     * the viewer themselves, regardless of role.
     */
    Route::middleware('menu.access:my-day')->group(function () {
        Route::get('my-day', [MyDayController::class, 'index'])->name('my-day.index');
    });

    /*
     * Attendance — Milestone 4b. Gated by menu.access:attendance.
     */
    Route::middleware('menu.access:attendance')->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
        Route::get('attendance/corrections', [AttendanceController::class, 'corrections'])->name('attendance.corrections');
        Route::post('attendance/corrections', [AttendanceController::class, 'storeCorrection'])->name('attendance.corrections.store');
        Route::get('attendance/import', HitechAttendanceImport::class)->name('attendance.import');
        Route::post('attendance/biometric-sync', [AttendanceController::class, 'requestSync'])->name('attendance.biometric-sync');
    });

    /*
     * Leave requests — employee self-service + admin/manager approval queue.
     */
    Route::middleware('menu.access:leave-requests')->group(function () {
        Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
        Route::delete('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->name('leave-requests.destroy');
        Route::get('leave-requests/approvals', [LeaveRequestController::class, 'approvals'])->name('leave-requests.approvals');
        Route::post('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::post('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
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
     * Notifications — task assignments and other in-app alerts.
     */
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    /*
     * Milestone 0 stub pages. Each is protected by menu.access:<key>, which
     * enforces role-based access regardless of whether the item shows in the
     * sidebar. Route name == menu key so the sidebar can link via route(key).
     * Real modules replace these in later milestones.
     */
    /*
     * Services (service-line taxonomy) — Milestone 7. Keeps the "categories"
     * menu key (so per-user overrides survive) but is now real Service mgmt,
     * admin/manager via menu.access:categories.
     */
    Route::middleware('menu.access:categories')->group(function () {
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });

    /*
     * Festivals — admin-owned calendar driving the dashboard reminder and
     * AI-drafted client greeting content. Admin/manager via menu.access:festivals.
     */
    Route::middleware('menu.access:festivals')->group(function () {
        Route::get('festivals', [FestivalController::class, 'index'])->name('festivals.index');
        Route::post('festivals', [FestivalController::class, 'store'])->name('festivals.store');
        Route::put('festivals/{festival}', [FestivalController::class, 'update'])->name('festivals.update');
        Route::delete('festivals/{festival}', [FestivalController::class, 'destroy'])->name('festivals.destroy');
    });

    /*
     * Client Radar — at-risk / upsell signals for active clients. Admin/manager
     * via menu.access:client-radar (no Policy class, same convention as Festivals).
     */
    Route::middleware('menu.access:client-radar')->group(function () {
        Route::get('client-radar', [ClientRadarController::class, 'index'])->name('client-radar.index');
    });

    /*
     * Resources — Files (Resource Library) + Links (Important Links) on one
     * page, two tabs. Everyone can view what their role can see (per-item
     * visibility enforced by HasRoleVisibility, not this middleware);
     * add/edit/delete is Admin/Manager only, enforced inside each Livewire
     * component/Policy. menu.access key deliberately stayed
     * "important-links" — see MenuItemsSeeder. Per-client links live on the
     * client page's own "Links" tab instead.
     */
    Route::middleware('menu.access:important-links')->group(function () {
        Route::get('resources', [ResourceController::class, 'index'])->name('resources.index');
        Route::get('resources/{teamResource}/download', [TeamResourceController::class, 'download'])->name('team-resources.download');
    });

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
     * In-app help — renders the Markdown user guides. Available to everyone.
     */
    Route::get('/help', [HelpController::class, 'index'])->name('help');
    Route::get('/help/{guide}', [HelpController::class, 'show'])->name('help.show');

    /*
     * Audit log — Milestone 7. Admin-only (enforced in the controller).
     */
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log');

    /*
     * Staff user management — admin-only (menu.access:users → admin bypass only).
     * Public registration is disabled, so this is how accounts are created.
     */
    Route::middleware('menu.access:users')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });

    /*
     * Employee 360° View — read-mostly employee profile (performance,
     * workload, tickets, attendance, manager notes). Own menu key
     * (employee-360), separate from menu.access:users above, so granting
     * it to Manager doesn't also grant full Users account-management CRUD.
     */
    Route::middleware('menu.access:employee-360')->group(function () {
        Route::get('employees', [EmployeeProfileController::class, 'index'])->name('employees.index');
        Route::get('employees/{user}', [EmployeeProfileController::class, 'show'])->name('employees.show');
    });

    /*
     * Manager Action Center — aggregates existing "needs attention" signals
     * (overdue tasks, at-risk clients, overdue invoices, SLA breaches,
     * contract renewals due soon, pending follow-ups). See
     * ManagerActionCenterMetrics for what's deliberately excluded.
     */
    Route::middleware('menu.access:manager-action-center')->group(function () {
        Route::get('manager-action-center', [ManagerActionCenterController::class, 'index'])->name('manager-action-center.index');
    });

    /*
     * Central Approval Center — aggregates every genuinely pending approval
     * workflow that already exists (leave requests, project daily updates,
     * quotations). See ApprovalCenterMetrics for what's deliberately
     * excluded (Content, Client requests) and why.
     */
    Route::middleware('menu.access:approval-center')->group(function () {
        Route::get('approval-center', [ApprovalCenterController::class, 'index'])->name('approval-center.index');
    });

    /*
     * Team Workload & Capacity — Tier 2 #02. See TaskWorkloadMetrics for
     * the confirmed "overloaded" formula.
     */
    Route::middleware('menu.access:team-workload')->group(function () {
        Route::get('team-workload', [TeamWorkloadController::class, 'index'])->name('team-workload.index');
    });

    /*
     * Project Health Dashboard — Tier 2 #03. See ProjectHealthMetrics for
     * the confirmed 🔴🟠🟡🟢 formula.
     */
    Route::middleware('menu.access:project-health')->group(function () {
        Route::get('project-health', [ProjectHealthController::class, 'index'])->name('project-health.index');
    });

    /*
     * Revenue at Risk — Tier 2 #10. See RevenueAtRiskMetrics for what this
     * aggregates (pure aggregation, no new formula decision needed).
     */
    Route::middleware('menu.access:revenue-at-risk')->group(function () {
        Route::get('revenue-at-risk', [RevenueAtRiskController::class, 'index'])->name('revenue-at-risk.index');
    });

    /*
     * Manager Calendar — Tier 3. See ManagerCalendarMetrics for what this
     * aggregates (meetings, task/project deadlines, approved leave).
     */
    Route::middleware('menu.access:manager-calendar')->group(function () {
        Route::get('manager-calendar', ManagerCalendar::class)->name('manager-calendar.index');
    });
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
        Route::get('forgot-password', [ForgotPasswordController::class, 'show'])->name('password.forgot');
        Route::post('forgot-password', [ForgotPasswordController::class, 'send'])->name('password.forgot.send');
        Route::get('reset-password/{token}', [SetPasswordController::class, 'showReset'])->name('password.reset');
        Route::post('reset-password/{token}', [SetPasswordController::class, 'store'])->name('password.reset.store');
    });

    Route::middleware('auth:portal')->group(function () {
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::get('notifications', [App\Http\Controllers\Portal\NotificationController::class, 'index'])->name('notifications.index');
        Route::delete('notifications/{id}', [App\Http\Controllers\Portal\NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::get('quotations', [App\Http\Controllers\Portal\QuotationController::class, 'index'])->name('quotations.index');
        Route::get('quotations/{quotation}', [App\Http\Controllers\Portal\QuotationController::class, 'show'])->name('quotations.show');
        Route::post('quotations/{quotation}/accept', [App\Http\Controllers\Portal\QuotationController::class, 'accept'])->name('quotations.accept');
        Route::post('quotations/{quotation}/reject', [App\Http\Controllers\Portal\QuotationController::class, 'reject'])->name('quotations.reject');
        Route::get('invoices', [App\Http\Controllers\Portal\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [App\Http\Controllers\Portal\InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [App\Http\Controllers\Portal\InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/pay/order', [InvoicePaymentController::class, 'order'])->name('invoices.pay.order');
        Route::post('invoices/{invoice}/pay/verify', [InvoicePaymentController::class, 'verify'])->name('invoices.pay.verify');
        Route::get('services', [App\Http\Controllers\Portal\ServiceController::class, 'index'])->name('services.index');
        Route::get('projects', [App\Http\Controllers\Portal\ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{project}', [App\Http\Controllers\Portal\ProjectController::class, 'show'])->name('projects.show');
        Route::post('projects/{project}/deliverables/{deliverable}/attachments', [App\Http\Controllers\Portal\ProjectController::class, 'uploadDeliverable'])->name('projects.deliverables.upload');
        Route::post('projects/{project}/request-meeting', [App\Http\Controllers\Portal\ProjectController::class, 'requestMeeting'])->name('projects.request-meeting');

        Route::get('tickets', [App\Http\Controllers\Portal\TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [App\Http\Controllers\Portal\TicketController::class, 'create'])->name('tickets.create');
        Route::post('tickets', [App\Http\Controllers\Portal\TicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [App\Http\Controllers\Portal\TicketController::class, 'show'])->name('tickets.show');
        Route::get('tickets/{ticket}/attachments/{attachment}/download', [App\Http\Controllers\Portal\TicketController::class, 'downloadAttachment'])->name('tickets.attachments.download');
        Route::post('tickets/{ticket}/reply', [App\Http\Controllers\Portal\TicketController::class, 'reply'])->name('tickets.reply');
        Route::post('tickets/{ticket}/rate', [App\Http\Controllers\Portal\TicketController::class, 'rate'])->name('tickets.rate');

        // SSO bridge — generates a short-lived signed token and redirects the
        // contact to Drishti or SMDost so they log in without a separate password.
        Route::get('sso/{app}', [SsoController::class, 'redirect'])->name('sso');
    });
});

/*
 * Partner Portal — Client Panel/Partner Panel Tier 0. Separate "partner"
 * guard (Partners). Every authed route is scoped to the logged-in partner
 * themselves (see PartnerPortalController).
 */
Route::prefix('partner-portal')->name('partner-portal.')->group(function () {
    Route::middleware('guest:partner')->group(function () {
        Route::get('login', [App\Http\Controllers\PartnerPortal\LoginController::class, 'show'])->name('login');
        Route::post('login', [App\Http\Controllers\PartnerPortal\LoginController::class, 'login']);
        Route::get('set-password/{token}', [App\Http\Controllers\PartnerPortal\SetPasswordController::class, 'show'])->name('password.setup');
        Route::post('set-password/{token}', [App\Http\Controllers\PartnerPortal\SetPasswordController::class, 'store'])->name('password.store');
        Route::get('forgot-password', [App\Http\Controllers\PartnerPortal\ForgotPasswordController::class, 'show'])->name('password.forgot');
        Route::post('forgot-password', [App\Http\Controllers\PartnerPortal\ForgotPasswordController::class, 'send'])->name('password.forgot.send');
        Route::get('reset-password/{token}', [App\Http\Controllers\PartnerPortal\SetPasswordController::class, 'showReset'])->name('password.reset');
        Route::post('reset-password/{token}', [App\Http\Controllers\PartnerPortal\SetPasswordController::class, 'store'])->name('password.reset.store');
    });

    Route::middleware('auth:partner')->group(function () {
        Route::post('logout', [App\Http\Controllers\PartnerPortal\LoginController::class, 'logout'])->name('logout');
        Route::get('/', [App\Http\Controllers\PartnerPortal\HomeController::class, 'index'])->name('home');
        Route::post('content-pieces/{contentPiece}/attachments', [App\Http\Controllers\PartnerPortal\ContentPieceController::class, 'upload'])->name('content-pieces.upload');
    });
});

require __DIR__.'/auth.php';
