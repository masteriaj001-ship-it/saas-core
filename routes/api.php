<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ApiTokenController;
use App\Modules\Facturacion\Http\Controllers\Api\InvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/sanctum/token', [ApiTokenController::class, 'createToken']);
Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/sanctum/token', [ApiTokenController::class, 'revokeCurrentToken']);
    Route::delete('/sanctum/tokens', [ApiTokenController::class, 'revokeAllTokens']);
    Route::get('/sanctum/tokens', [ApiTokenController::class, 'listTokens']);
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('v1')->group(function () {
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::patch('/invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
});
