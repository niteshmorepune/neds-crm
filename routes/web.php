<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\StubController;
use App\Livewire\ClientImport;
use App\Livewire\DealsBoard;
use App\Livewire\QuotationBuilder;
use App\Livewire\RecurringInvoiceBuilder;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

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
     * Milestone 0 stub pages. Each is protected by menu.access:<key>, which
     * enforces role-based access regardless of whether the item shows in the
     * sidebar. Route name == menu key so the sidebar can link via route(key).
     * Real modules replace these in later milestones.
     */
    $stubs = [
        'attendance' => '/attendance',
        'project-updates' => '/project-updates',
        'categories' => '/categories',
        'calling' => '/calling',
        'emptask' => '/emptask',
        'menu-controller' => '/menu-controller',
    ];

    foreach ($stubs as $key => $path) {
        Route::get($path, StubController::class)
            ->middleware("menu.access:{$key}")
            ->name($key);
    }
});

require __DIR__.'/auth.php';
