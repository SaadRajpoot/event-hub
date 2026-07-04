# EventHub — Main API Service Specification

**Service:** Main API  
**Stack:** Laravel 11 / PHP 8.2+  
**Port:** 8000  
**Database:** MySQL 8.0 (`eventhub`)  
**Version:** 1.0  
**Date:** 2026-05-07

---

## Build Progress Checklist

> Use this checklist to track implementation progress. Update as each item is completed.

### Phase 1: Project Scaffold
- [ ] Laravel 11 project created (`composer create-project laravel/laravel main-api`)
- [ ] PHP 8.2+ confirmed
- [ ] Required packages installed (Sanctum, Redis, RabbitMQ, etc.)
- [ ] `.env` configured for Docker environment
- [ ] `CLAUDE.md` agent skill registered
- [ ] Docker container running and accessible at port 8000

### Phase 2: Database & Models
- [ ] Migration: `users` table
- [ ] Migration: `vendors` table
- [ ] Migration: `attendees` table
- [ ] Migration: `platform_settings` table
- [ ] Migration: `events` table
- [ ] Migration: `ticket_types` table
- [ ] Migration: `orders` table
- [ ] Migration: `order_items` table
- [ ] Migration: `payments` table
- [ ] Migration: `refunds` table
- [ ] Migration: `payouts` table
- [ ] Migration: `payout_batches` table
- [ ] Migration: `payout_order_items` table
- [ ] Migration: `notifications` table
- [ ] Migration: `vendor_webhook_deliveries` table
- [ ] Migration: `waitlists` table
- [ ] Migration: `audit_logs` table
- [ ] All Eloquent models created with relationships
- [ ] Database seeders created (vendors, events, tickets, orders, payouts)

### Phase 3: Authentication
- [ ] Laravel Sanctum installed and configured
- [ ] `POST /api/v1/auth/register` (attendee)
- [ ] `POST /api/v1/auth/register/vendor` (vendor)
- [ ] `POST /api/v1/auth/login`
- [ ] `POST /api/v1/auth/logout`
- [ ] `GET /api/v1/auth/me`
- [ ] Role middleware (`CheckRole`) created
- [ ] Route groups with role middleware applied

### Phase 4: Vendor Management
- [ ] `GET /api/v1/vendors/profile`
- [ ] `PUT /api/v1/vendors/profile`
- [ ] `PUT /api/v1/vendors/bank-details`
- [ ] `PUT /api/v1/vendors/webhook`

### Phase 5: Event Management
- [ ] `GET /api/v1/events` (public)
- [ ] `GET /api/v1/events/{id}` (public)
- [ ] `POST /api/v1/events` (vendor)
- [ ] `PUT /api/v1/events/{id}` (vendor)
- [ ] `DELETE /api/v1/events/{id}` (vendor — soft delete)
- [ ] `POST /api/v1/events/{id}/publish` (vendor)
- [ ] `POST /api/v1/events/{id}/cancel` (vendor)
- [ ] `GET /api/v1/vendors/events` (vendor — own events)
- [ ] EventPolicy authorization

### Phase 6: Ticket Types
- [ ] `GET /api/v1/events/{id}/ticket-types` (public)
- [ ] `POST /api/v1/events/{id}/ticket-types` (vendor)
- [ ] `PUT /api/v1/events/{id}/ticket-types/{typeId}` (vendor)
- [ ] `DELETE /api/v1/events/{id}/ticket-types/{typeId}` (vendor)

### Phase 7: Order Processing ⚠️ CRITICAL
- [ ] Redis distributed lock service implemented
- [ ] `POST /api/v1/orders` (attendee — checkout with locking)
- [ ] `GET /api/v1/orders` (attendee — own orders)
- [ ] `GET /api/v1/orders/{id}` (attendee — own order detail)
- [ ] `POST /api/v1/payments/initiate` (attendee)
- [ ] `POST /api/v1/webhooks/payment` (internal — payment service callback)
- [ ] `POST /api/v1/webhooks/refund` (internal — refund callback)
- [ ] Order expiry cron job (`ExpireOrderReservationsJob`)
- [ ] Unit tests: concurrent checkout, inventory limits, expiry

### Phase 8: Refunds
- [ ] `POST /api/v1/refunds` (attendee)
- [ ] `GET /api/v1/refunds/{id}` (attendee)
- [ ] Refund policy calculation service
- [ ] Unit tests: refund policy (>48h, 24-48h, <24h, event cancelled)

### Phase 9: Payout Management ⚠️ CRITICAL
- [ ] `GET /api/v1/vendors/payouts` (vendor)
- [ ] `GET /api/v1/vendors/payouts/{id}` (vendor)
- [ ] Payout calculation service
- [ ] Payout batch cron job (`ProcessPayoutBatchJob`)
- [ ] Unit tests: commission calculation, minimum threshold, double-payout prevention

### Phase 10: Admin Endpoints
- [ ] `GET /api/v1/admin/vendors` (admin)
- [ ] `POST /api/v1/admin/vendors/{id}/approve` (admin)
- [ ] `POST /api/v1/admin/vendors/{id}/reject` (admin)
- [ ] `GET /api/v1/admin/refunds` (admin)
- [ ] `POST /api/v1/admin/refunds/{id}/approve` (admin)
- [ ] `POST /api/v1/admin/refunds/{id}/reject` (admin)
- [ ] `GET /api/v1/admin/payouts` (admin)
- [ ] `POST /api/v1/admin/payouts/{id}/approve` (admin)
- [ ] `GET /api/v1/admin/analytics` (admin)
- [ ] `PUT /api/v1/admin/settings` (admin — commission rate, etc.)

### Phase 11: Notifications & Background Jobs
- [ ] RabbitMQ publisher service
- [ ] `SendEventRemindersJob` cron (hourly)
- [ ] `GenerateSalesReportsJob` cron (daily)
- [ ] `ProcessWaitlistJob` (event-driven)
- [ ] Waitlist endpoints: `POST /api/v1/events/{id}/waitlist`, `DELETE /api/v1/events/{id}/waitlist`

### Phase 12: QR Code & Check-In
- [ ] QR code token generation on order confirmation
- [ ] `POST /api/v1/checkin` (vendor — scan QR code)
- [ ] HMAC signature validation

### Phase 13: Testing & Documentation
- [ ] All unit tests passing
- [ ] API documentation (Postman collection or OpenAPI spec)
- [ ] Seed data covers all scenarios

---

## Table of Contents

1. [Project Structure](#1-project-structure)
2. [Required Packages](#2-required-packages)
3. [Architecture Pattern](#3-architecture-pattern)
4. [All API Endpoints](#4-all-api-endpoints)
5. [Service Layer Specifications](#5-service-layer-specifications)
6. [Repository Layer Specifications](#6-repository-layer-specifications)
7. [Background Jobs](#7-background-jobs)
8. [Coding Conventions](#8-coding-conventions)
9. [Testing Requirements](#9-testing-requirements)

---

## 1. Project Structure

```
main-api/
├── app/
│   ├── Console/
│   │   └── Commands/           # Artisan commands for cron jobs
│   ├── Exceptions/
│   │   └── Handler.php         # Global exception handler
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── V1/
│   │   │           ├── Auth/
│   │   │           │   └── AuthController.php
│   │   │           ├── Admin/
│   │   │           │   ├── VendorController.php
│   │   │           │   ├── RefundController.php
│   │   │           │   ├── PayoutController.php
│   │   │           │   └── AnalyticsController.php
│   │   │           ├── Vendor/
│   │   │           │   ├── ProfileController.php
│   │   │           │   ├── EventController.php
│   │   │           │   └── PayoutController.php
│   │   │           ├── Attendee/
│   │   │           │   ├── EventController.php
│   │   │           │   ├── OrderController.php
│   │   │           │   └── RefundController.php
│   │   │           ├── Public/
│   │   │           │   └── EventController.php
│   │   │           └── Webhook/
│   │   │               └── PaymentWebhookController.php
│   │   ├── Middleware/
│   │   │   ├── CheckRole.php
│   │   │   └── VerifyWebhookSignature.php
│   │   └── Requests/
│   │       ├── Auth/
│   │       ├── Event/
│   │       ├── Order/
│   │       ├── Refund/
│   │       └── Payout/
│   ├── Jobs/
│   │   ├── ExpireOrderReservationsJob.php
│   │   ├── SendEventRemindersJob.php
│   │   ├── ProcessPayoutBatchJob.php
│   │   ├── GenerateSalesReportsJob.php
│   │   └── ProcessWaitlistJob.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Vendor.php
│   │   ├── Attendee.php
│   │   ├── Event.php
│   │   ├── TicketType.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── Payment.php
│   │   ├── Refund.php
│   │   ├── Payout.php
│   │   ├── PayoutBatch.php
│   │   ├── PayoutOrderItem.php
│   │   ├── Notification.php
│   │   ├── VendorWebhookDelivery.php
│   │   ├── Waitlist.php
│   │   ├── AuditLog.php
│   │   └── PlatformSetting.php
│   ├── Policies/
│   │   ├── EventPolicy.php
│   │   ├── OrderPolicy.php
│   │   └── PayoutPolicy.php
│   ├── Repositories/
│   │   ├── Contracts/
│   │   │   ├── EventRepositoryInterface.php
│   │   │   ├── OrderRepositoryInterface.php
│   │   │   └── PayoutRepositoryInterface.php
│   │   ├── EventRepository.php
│   │   ├── OrderRepository.php
│   │   ├── TicketTypeRepository.php
│   │   └── PayoutRepository.php
│   └── Services/
│       ├── AuthService.php
│       ├── EventService.php
│       ├── TicketTypeService.php
│       ├── OrderService.php
│       ├── PaymentService.php          # HTTP client to payment microservice
│       ├── RefundService.php
│       ├── PayoutService.php
│       ├── NotificationPublisher.php   # RabbitMQ publisher
│       ├── DistributedLockService.php  # Redis locking
│       ├── QrCodeService.php
│       └── AuditService.php
├── database/
│   ├── migrations/
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── AdminSeeder.php
│       ├── VendorSeeder.php
│       ├── EventSeeder.php
│       └── OrderSeeder.php
├── routes/
│   └── api.php
└── tests/
    └── Unit/
        ├── Services/
        │   ├── OrderServiceTest.php
        │   ├── PayoutServiceTest.php
        │   └── RefundServiceTest.php
        └── Jobs/
            └── ExpireOrderReservationsJobTest.php
```

---

## 2. Required Packages

```bash
# Core
composer require laravel/sanctum
composer require predis/predis          # Redis client
composer require php-amqplib/php-amqplib # RabbitMQ
composer require guzzlehttp/guzzle      # HTTP client (for payment service calls)
composer require bacon/bacon-qr-code    # QR code generation
composer require endroid/qr-code        # QR code alternative

# Development
composer require --dev phpunit/phpunit
composer require --dev fakerphp/faker
```

---

## 3. Architecture Pattern

### Rule: Controller → Service → Repository → Model

```
HTTP Request
    │
    ▼
FormRequest (validation + authorization)
    │
    ▼
Controller (thin — only HTTP concerns)
    │  - Calls one service method
    │  - Returns ApiResponse
    ▼
Service (all business logic lives here)
    │  - Orchestrates repositories
    │  - Throws domain exceptions
    │  - Publishes events/notifications
    ▼
Repository (data access only)
    │  - Wraps Eloquent queries
    │  - Returns Model or Collection
    ▼
Model (Eloquent — relationships, scopes, casts)
```

### Standard API Response Helper

Every controller must use this response format:

```php
// app/Traits/ApiResponse.php
trait ApiResponse
{
    protected function success($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ], $status);
    }

    protected function error(string $message, $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data'    => null,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
```

### Exception Handling

All domain exceptions extend `App\Exceptions\DomainException`. The global handler maps them to HTTP responses:

```php
// Domain exceptions
InsufficientInventoryException  → 422
LockAcquisitionException        → 409
OrderExpiredException           → 422
UnauthorizedVendorException     → 403
RefundNotEligibleException      → 422
PayoutBelowThresholdException   → 422
```

---

## 4. All API Endpoints

### 4.1 Authentication Routes (Public)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/register` | Register as attendee |
| POST | `/api/v1/auth/register/vendor` | Register as vendor |
| POST | `/api/v1/auth/login` | Login (all roles) |

### 4.2 Authenticated Routes

| Method | Endpoint | Role | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/auth/logout` | Any | Logout (revoke token) |
| GET | `/api/v1/auth/me` | Any | Get current user |

### 4.3 Public Event Routes (No Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/events` | List published events (paginated) |
| GET | `/api/v1/events/{id}` | Get event detail with ticket types |

### 4.4 Vendor Routes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/vendor/profile` | Get vendor profile |
| PUT | `/api/v1/vendor/profile` | Update vendor profile |
| PUT | `/api/v1/vendor/bank-details` | Update bank/payout details |
| PUT | `/api/v1/vendor/webhook` | Register/update webhook URL |
| GET | `/api/v1/vendor/events` | List own events |
| POST | `/api/v1/vendor/events` | Create event |
| GET | `/api/v1/vendor/events/{id}` | Get own event detail |
| PUT | `/api/v1/vendor/events/{id}` | Update event |
| DELETE | `/api/v1/vendor/events/{id}` | Soft delete event (draft only) |
| POST | `/api/v1/vendor/events/{id}/publish` | Publish event |
| POST | `/api/v1/vendor/events/{id}/cancel` | Cancel event |
| GET | `/api/v1/vendor/events/{id}/ticket-types` | List ticket types |
| POST | `/api/v1/vendor/events/{id}/ticket-types` | Create ticket type |
| PUT | `/api/v1/vendor/events/{id}/ticket-types/{typeId}` | Update ticket type |
| DELETE | `/api/v1/vendor/events/{id}/ticket-types/{typeId}` | Deactivate ticket type |
| GET | `/api/v1/vendor/events/{id}/sales` | Sales summary for event |
| GET | `/api/v1/vendor/payouts` | List own payouts |
| GET | `/api/v1/vendor/payouts/{id}` | Get payout detail |
| GET | `/api/v1/vendor/dashboard` | Dashboard summary (sales, revenue, pending payout) |

### 4.5 Attendee Routes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/attendee/events` | Browse events (same as public, with auth) |
| GET | `/api/v1/attendee/events/{id}` | Event detail |
| POST | `/api/v1/attendee/events/{id}/waitlist` | Join waitlist |
| DELETE | `/api/v1/attendee/events/{id}/waitlist` | Leave waitlist |
| POST | `/api/v1/attendee/orders` | Create order (checkout) |
| GET | `/api/v1/attendee/orders` | List own orders |
| GET | `/api/v1/attendee/orders/{id}` | Order detail with QR codes |
| POST | `/api/v1/attendee/payments/initiate` | Initiate payment for order |
| POST | `/api/v1/attendee/refunds` | Request refund |
| GET | `/api/v1/attendee/refunds/{id}` | Get refund status |

### 4.6 Admin Routes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/vendors` | List all vendors with KYC status |
| GET | `/api/v1/admin/vendors/{id}` | Vendor detail |
| POST | `/api/v1/admin/vendors/{id}/approve` | Approve vendor KYC |
| POST | `/api/v1/admin/vendors/{id}/reject` | Reject vendor KYC |
| GET | `/api/v1/admin/events` | List all events |
| GET | `/api/v1/admin/orders` | List all orders |
| GET | `/api/v1/admin/refunds` | List all refund requests |
| POST | `/api/v1/admin/refunds/{id}/approve` | Approve refund |
| POST | `/api/v1/admin/refunds/{id}/reject` | Reject refund |
| GET | `/api/v1/admin/payouts` | List all payouts |
| POST | `/api/v1/admin/payouts/{id}/approve` | Approve payout |
| GET | `/api/v1/admin/analytics` | Platform-wide analytics |
| GET | `/api/v1/admin/settings` | Get platform settings |
| PUT | `/api/v1/admin/settings` | Update platform settings |

### 4.7 Internal Webhook Routes

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/webhooks/payment` | X-Webhook-Secret | Payment status update from payment service |
| POST | `/api/v1/webhooks/refund` | X-Webhook-Secret | Refund status update from payment service |
| POST | `/api/v1/webhooks/payout` | X-Webhook-Secret | Payout batch result from payment service |

### 4.8 Check-In Route

| Method | Endpoint | Role | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/checkin` | vendor | Validate QR code and mark ticket as checked in |

---

## 5. Service Layer Specifications

### 5.1 DistributedLockService

```php
class DistributedLockService
{
    // Acquire a Redis lock. Returns true if acquired, false if not.
    public function acquire(string $key, int $ttlSeconds = 5): bool

    // Release a Redis lock.
    public function release(string $key): void

    // Execute a callback within a lock. Throws LockAcquisitionException if lock unavailable.
    public function withLock(string $key, callable $callback, int $ttlSeconds = 5): mixed
}
```

**Implementation:**
```php
public function acquire(string $key, int $ttlSeconds = 5): bool
{
    return (bool) Redis::set(
        "lock:{$key}",
        uniqid(),
        'NX',   // Only set if not exists
        'EX',   // Expiry in seconds
        $ttlSeconds
    );
}
```

### 5.2 OrderService

```php
class OrderService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private TicketTypeRepository $ticketTypeRepository,
        private DistributedLockService $lockService,
        private PaymentService $paymentService,
        private NotificationPublisher $notificationPublisher,
    ) {}

    /**
     * Create an order with distributed locking.
     * Throws: InsufficientInventoryException, LockAcquisitionException
     */
    public function createOrder(Attendee $attendee, array $items, string $idempotencyKey): Order

    /**
     * Process payment webhook from payment service.
     * Idempotent: safe to call multiple times with same payment_id.
     */
    public function processPaymentWebhook(array $webhookData): void

    /**
     * Expire orders that have passed their expiry time.
     * Called by cron job every 5 minutes.
     */
    public function expireStaleOrders(): int  // Returns count of expired orders

    /**
     * Calculate order totals including group bundle discounts.
     */
    public function calculateOrderTotals(array $items): array
}
```

**createOrder() Logic:**
```
1. Check idempotency: if order with this key exists, return it
2. Validate all ticket_type_ids belong to the same event
3. Validate event is published
4. For each ticket type, acquire Redis lock
5. Within DB transaction (SELECT FOR UPDATE):
   a. Re-check inventory for each ticket type
   b. Calculate totals (apply group bundle discounts)
   c. Snapshot commission rate from platform settings
   d. Create Order record
   e. Create OrderItem records
   f. Decrement quantity_held for each ticket type
6. Release all locks
7. Return order
```

### 5.3 PayoutService

```php
class PayoutService
{
    /**
     * Calculate pending balance for a vendor.
     * Returns amount eligible for payout (not yet paid out).
     */
    public function calculatePendingBalance(Vendor $vendor): float

    /**
     * Run the daily payout batch.
     * Idempotent: will not create duplicate batch for same day.
     */
    public function runPayoutBatch(): PayoutBatch

    /**
     * Process payout webhook result from payment service.
     */
    public function processPayoutWebhook(array $webhookData): void
}
```

**calculatePendingBalance() Logic:**
```sql
SELECT SUM(o.vendor_amount)
FROM orders o
WHERE o.status = 'paid'
  AND o.event_id IN (SELECT id FROM events WHERE vendor_id = ?)
  AND o.id NOT IN (SELECT order_id FROM payout_order_items)
  AND o.paid_at < NOW()
```

### 5.4 RefundService

```php
class RefundService
{
    /**
     * Calculate refund amount based on time-based policy.
     * Returns: ['amount' => float, 'policy' => string]
     */
    public function calculateRefundAmount(Order $order): array

    /**
     * Create a refund request.
     */
    public function requestRefund(Order $order, Attendee $attendee, string $reason): Refund

    /**
     * Process admin approval of refund.
     */
    public function approveRefund(Refund $refund, User $admin): void

    /**
     * Handle event cancellation — trigger full refunds for all paid orders.
     */
    public function processEventCancellationRefunds(Event $event): void
}
```

**calculateRefundAmount() Logic:**
```php
$hoursUntilEvent = now()->diffInHours($order->event->start_datetime, false);

if ($order->event->status === 'cancelled') {
    return ['amount' => $order->subtotal, 'policy' => 'event_cancelled'];
}

if ($hoursUntilEvent > 48) {
    return ['amount' => $order->subtotal, 'policy' => 'full'];
}

if ($hoursUntilEvent >= 24) {
    return ['amount' => $order->subtotal * 0.5, 'policy' => 'partial_50'];
}

return ['amount' => 0, 'policy' => 'none'];
```

### 5.5 NotificationPublisher

```php
class NotificationPublisher
{
    /**
     * Publish a notification job to RabbitMQ.
     * All methods are fire-and-forget.
     */
    public function publishOrderConfirmation(Order $order): void
    public function publishEventReminder(Order $order, Event $event): void
    public function publishPayoutCompleted(Payout $payout): void
    public function publishVendorApproved(Vendor $vendor): void
    public function publishVendorRejected(Vendor $vendor, string $reason): void
    public function publishWaitlistAvailable(Waitlist $waitlist): void

    private function publish(string $routingKey, array $payload): void
    // Publishes to exchange: 'eventhub.notifications'
    // Includes idempotency_key to prevent duplicate delivery
}
```

---

## 6. Repository Layer Specifications

### 6.1 OrderRepository

```php
interface OrderRepositoryInterface
{
    public function findByIdempotencyKey(string $key): ?Order;
    public function findExpiredOrders(): Collection;  // status=pending_payment AND expires_at < now
    public function findPaidOrdersForVendor(int $vendorId, ?Carbon $before = null): Collection;
    public function findUnpaidOutOrdersForVendor(int $vendorId): Collection;
    public function createWithItems(array $orderData, array $items): Order;
}
```

### 6.2 TicketTypeRepository

```php
interface TicketTypeRepositoryInterface
{
    public function findAvailableForEvent(int $eventId): Collection;
    public function decrementHeld(int $ticketTypeId, int $quantity): void;
    public function incrementSoldDecrementHeld(int $ticketTypeId, int $quantity): void;
    public function restoreHeld(int $ticketTypeId, int $quantity): void;
    // All inventory mutations use DB transactions
}
```

---

## 7. Background Jobs

### 7.1 Scheduler Registration (routes/console.php)

```php
Schedule::job(new ExpireOrderReservationsJob)->everyFiveMinutes();
Schedule::job(new SendEventRemindersJob)->hourly();
Schedule::job(new ProcessPayoutBatchJob)->dailyAt('02:00');
Schedule::job(new GenerateSalesReportsJob)->dailyAt('03:00');
```

### 7.2 ExpireOrderReservationsJob

```php
class ExpireOrderReservationsJob implements ShouldQueue
{
    public function handle(OrderService $orderService): void
    {
        $count = $orderService->expireStaleOrders();
        Log::info("Expired {$count} stale orders");
    }
}
```

**Idempotency:** Safe to run multiple times. Orders already in `expired` status are skipped.

### 7.3 ProcessPayoutBatchJob

```php
class ProcessPayoutBatchJob implements ShouldQueue
{
    public function handle(PayoutService $payoutService): void
    {
        // Check if batch already ran today
        $today = now()->toDateString();
        $existing = PayoutBatch::where('batch_reference', "payout_batch_{$today}")->first();

        if ($existing && $existing->status !== 'failed') {
            Log::info("Payout batch already processed for {$today}");
            return;
        }

        $batch = $payoutService->runPayoutBatch();
        Log::info("Payout batch {$batch->batch_reference} completed: {$batch->processed_vendors}/{$batch->total_vendors} vendors");
    }
}
```

---

## 8. Coding Conventions

### 8.1 Naming Conventions

| Type | Convention | Example |
|------|-----------|---------|
| Controllers | PascalCase + `Controller` | `OrderController` |
| Services | PascalCase + `Service` | `OrderService` |
| Repositories | PascalCase + `Repository` | `OrderRepository` |
| Models | PascalCase singular | `Order`, `TicketType` |
| Migrations | snake_case with timestamp | `2026_05_07_create_orders_table` |
| Routes | kebab-case | `/api/v1/ticket-types` |
| DB columns | snake_case | `quantity_held`, `created_at` |
| Enums | snake_case values | `pending_payment`, `early_bird` |

### 8.2 Controller Rules

- Controllers must be thin — no business logic
- One service call per controller method
- Always use `FormRequest` for validation
- Always use `ApiResponse` trait for responses
- Use `$this->authorize()` for policy checks

```php
// CORRECT
public function store(CreateOrderRequest $request): JsonResponse
{
    $this->authorize('create', Order::class);
    $order = $this->orderService->createOrder(
        auth()->user()->attendee,
        $request->validated()['items'],
        $request->validated()['idempotency_key']
    );
    return $this->success(new OrderResource($order), 'Order created', 201);
}

// WRONG — business logic in controller
public function store(Request $request): JsonResponse
{
    $tickets = TicketType::find($request->ticket_type_id);
    if ($tickets->quantity_held >= $tickets->quantity) { ... }
    // ...
}
```

### 8.3 Model Rules

- Use `$fillable` (never `$guarded = []`)
- Define all relationships
- Use `$casts` for type casting (especially `decimal` → `float`, `json` → `array`)
- Use model scopes for common queries

```php
class Order extends Model
{
    protected $fillable = [
        'order_number', 'attendee_id', 'event_id', 'status',
        'subtotal', 'platform_commission_rate', 'platform_commission_amount',
        'vendor_amount', 'idempotency_key', 'expires_at',
    ];

    protected $casts = [
        'subtotal'                    => 'decimal:2',
        'platform_commission_rate'    => 'decimal:4',
        'platform_commission_amount'  => 'decimal:2',
        'vendor_amount'               => 'decimal:2',
        'expires_at'                  => 'datetime',
        'paid_at'                     => 'datetime',
    ];

    // Scopes
    public function scopePendingPayment($query) { return $query->where('status', 'pending_payment'); }
    public function scopeExpired($query) { return $query->where('expires_at', '<', now()); }
    public function scopePaid($query) { return $query->where('status', 'paid'); }
}
```

### 8.4 Error Handling

- Throw domain exceptions from services, never from controllers
- Log all exceptions with context
- Never expose stack traces to API consumers
- Use structured logging with context arrays

```php
// In service
throw new InsufficientInventoryException(
    "Only {$available} tickets remaining for {$ticketType->name}",
    ['ticket_type_id' => $ticketType->id, 'requested' => $quantity, 'available' => $available]
);

// In Handler.php
if ($e instanceof InsufficientInventoryException) {
    return $this->error($e->getMessage(), null, 422);
}
```

---

## 9. Testing Requirements

### 9.1 Required Unit Tests

#### OrderServiceTest

```php
class OrderServiceTest extends TestCase
{
    /** @test */
    public function it_creates_order_and_holds_inventory()

    /** @test */
    public function it_prevents_overselling_under_concurrent_requests()

    /** @test */
    public function it_returns_existing_order_for_duplicate_idempotency_key()

    /** @test */
    public function it_expires_stale_orders_and_restores_inventory()

    /** @test */
    public function it_applies_group_bundle_discount_when_minimum_quantity_met()

    /** @test */
    public function it_does_not_apply_discount_below_minimum_quantity()

    /** @test */
    public function it_rejects_checkout_when_event_is_not_published()
}
```

#### PayoutServiceTest

```php
class PayoutServiceTest extends TestCase
{
    /** @test */
    public function it_calculates_vendor_balance_correctly_after_commission()

    /** @test */
    public function it_does_not_include_orders_already_paid_out()

    /** @test */
    public function it_skips_vendors_below_minimum_payout_threshold()

    /** @test */
    public function it_does_not_run_duplicate_batch_for_same_day()

    /** @test */
    public function it_marks_batch_as_partially_failed_when_some_payouts_fail()
}
```

#### RefundServiceTest

```php
class RefundServiceTest extends TestCase
{
    /** @test */
    public function it_calculates_full_refund_when_more_than_48_hours_before_event()

    /** @test */
    public function it_calculates_50_percent_refund_between_24_and_48_hours()

    /** @test */
    public function it_calculates_zero_refund_within_24_hours()

    /** @test */
    public function it_always_gives_full_refund_when_event_is_cancelled()
}
```

### 9.2 Running Tests

```bash
cd main-api
php artisan test --filter=OrderServiceTest
php artisan test --filter=PayoutServiceTest
php artisan test --filter=RefundServiceTest
php artisan test  # Run all tests
```
