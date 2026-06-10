<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StubController;
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
     * Milestone 0 stub pages. Each is protected by menu.access:<key>, which
     * enforces role-based access regardless of whether the item shows in the
     * sidebar. Route name == menu key so the sidebar can link via route(key).
     * Real modules replace these in later milestones.
     */
    $stubs = [
        'attendance' => '/attendance',
        'lead-generation' => '/lead-generation',
        'sales-department' => '/sales-department',
        'account' => '/account',
        'project-updates' => '/project-updates',
        'categories' => '/categories',
        'quotations' => '/quotations',
        'customer' => '/clients',
        'invoices' => '/invoices',
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
