<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Middleware\ValidateApiKey;
use Illuminate\Support\Facades\Route;

// Internal API routes (protected by API key)
Route::middleware(ValidateApiKey::class)->group(function () {
    Route::post('/payments', [PaymentController::class, 'initiate']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{paymentReference}', [PaymentController::class, 'status']);
});

// Mock payment completion (no API key needed - simulates user browser action)
Route::post('/pay/{paymentReference}/complete', [PaymentController::class, 'mockComplete']);
