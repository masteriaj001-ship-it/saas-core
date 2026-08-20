<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\InvoiceTicketController;
use App\Http\Controllers\PosPrintController;
use App\Http\Controllers\QuoteApprovalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'showForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:register');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'showForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')
    ->get('/invoices/{invoice}/pdf', InvoicePdfController::class)
    ->name('invoices.pdf');

Route::middleware('auth')
    ->get('/invoices/{invoice}/ticket', InvoiceTicketController::class)
    ->name('invoices.ticket');

Route::middleware('auth')
    ->post('/pos/print', PosPrintController::class)
    ->name('pos.print');

Route::name('quote.approval.')
    ->group(function () {
        Route::get('/presupuesto/{workOrder}', [QuoteApprovalController::class, 'show'])
            ->name('show')
            ->middleware('signed');
        Route::post('/presupuesto/{workOrder}/approve', [QuoteApprovalController::class, 'approve'])
            ->name('approve')
            ->middleware('signed');
        Route::post('/presupuesto/{workOrder}/reject', [QuoteApprovalController::class, 'reject'])
            ->name('reject')
            ->middleware('signed');
        Route::get('/presupuesto/{workOrder}/aprobado', [QuoteApprovalController::class, 'approved'])
            ->name('approved');
        Route::get('/presupuesto/{workOrder}/rechazado', [QuoteApprovalController::class, 'rejected'])
            ->name('rejected');
    });
