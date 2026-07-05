<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PayoutController;
use App\Http\Controllers\Api\V1\TicketTypeController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/auth/register/attendee', [AuthController::class, 'registerAttendee']);
    Route::post('/auth/register/vendor', [AuthController::class, 'registerVendor']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Public event browsing
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{slug}', [EventController::class, 'show']);

    // Payment service webhook (HMAC verified internally)
    Route::post('/webhooks/payment', [WebhookController::class, 'handlePaymentWebhook']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Attendee routes
        Route::middleware('role:attendee')->group(function () {
            Route::get('/orders', [OrderController::class, 'index']);
            Route::post('/orders', [OrderController::class, 'store']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        });

        // Vendor routes
        Route::middleware(['role:vendor', 'vendor.active'])->group(function () {
            Route::get('/vendor/events', [EventController::class, 'vendorIndex']);
            Route::post('/vendor/events', [EventController::class, 'store']);
            Route::put('/vendor/events/{event}', [EventController::class, 'update']);
            Route::delete('/vendor/events/{event}', [EventController::class, 'destroy']);

            Route::post('/vendor/events/{event}/ticket-types', [TicketTypeController::class, 'store']);
            Route::put('/vendor/events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'update']);
            Route::delete('/vendor/events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy']);

            Route::get('/vendor/payouts', [PayoutController::class, 'index']);
            Route::get('/vendor/payouts/{payoutBatch}', [PayoutController::class, 'show']);

            // Check-in
            Route::post('/vendor/checkin/lookup', [OrderController::class, 'findByTicketCode']);
            Route::post('/vendor/checkin/{orderItem}', [OrderController::class, 'checkIn']);
        });

        // Admin routes
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/vendors', [AdminController::class, 'listVendors']);
            Route::post('/vendors/{vendor}/approve', [AdminController::class, 'approveVendor']);
            Route::post('/vendors/{vendor}/suspend', [AdminController::class, 'suspendVendor']);
            Route::get('/events', [AdminController::class, 'listEvents']);
            Route::post('/events/{event}/feature', [AdminController::class, 'featureEvent']);
            Route::get('/settings', [AdminController::class, 'getSettings']);
            Route::post('/settings', [AdminController::class, 'updateSetting']);
            Route::post('/payouts/batch', [PayoutController::class, 'createBatch']);
        });
    });
});
