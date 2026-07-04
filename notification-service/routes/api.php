<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Middleware\ValidateApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(ValidateApiKey::class)->group(function () {
    Route::post('/notifications', [NotificationController::class, 'send']);
    Route::post('/notifications/bulk', [NotificationController::class, 'sendBulk']);
    Route::get('/notifications', [NotificationController::class, 'index']);
});
