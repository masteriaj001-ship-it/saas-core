<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ApiTokenController;
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
