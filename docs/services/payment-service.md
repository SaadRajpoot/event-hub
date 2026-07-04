# EventHub — Payment Service Specification

**Service:** Payment Microservice  
**Stack:** Laravel 11 / PHP 8.2+  
**Port:** 8001  
**Database:** MySQL 8.0 (`eventhub_payments`)  
**Version:** 1.0  
**Date:** 2026-05-07

---

## Build Progress Checklist

> Use this checklist to track implementation progress. Update as each item is completed.

### Phase 1: Project Scaffold
- [ ] Laravel 11 project created (`composer create-project laravel/laravel payment-service`)
- [ ] `.env` configured for Docker environment
- [ ] Service token middleware created (`VerifyServiceToken`)
- [ ] Docker container running and accessible at port 8001
- [ ] Health check endpoint: `GET /health`

### Phase 2: Database & Models
- [ ] Migration: `payments` table
- [ ] Migration: `refunds` table
- [ ] Migration: `payout_batches` table
- [ ] Migration: `payout_items` table
- [ ] All Eloquent models created

### Phase 3: Payment Processing
- [ ] `StripeSimulatorService` implemented (configurable success rate)
- [ ] `PayPalSimulatorService` implemented (configurable success rate)
- [ ] `GatewayFactory` to select gateway by name
- [ ] `POST /api/payments` endpoint
- [ ] Idempotency check on payment creation
- [ ] Async webhook callback simulation (delayed HTTP call back to main API)
- [ ] `GET /api/payments/{id}` endpoint

### Phase 4: Refund Processing
- [ ] `POST /api/refunds` endpoint
- [ ] Full refund logic
- [ ] Partial refund logic
- [ ] Refund idempotency check
- [ ] Webhook callback to main API on refund completion

### Phase 5: Payout Processing
- [ ] `POST /api/payouts/batch` endpoint
- [ ] Individual payout processing per vendor
- [ ] Partial batch failure handling
- [ ] Webhook callback to main API with batch results

### Phase 6: Testing
- [ ] Unit tests: gateway simulators (success/failure rates)
- [ ] Unit tests: idempotency (duplicate payment requests)
- [ ] Unit tests: refund processing (full, partial)
- [ ] Unit tests: payout batch (partial failure)

---

## Table of Contents

1. [Project Structure](#1-project-structure)
2. [Required Packages](#2-required-packages)
3. [Security Model](#3-security-model)
4. [All API Endpoints](#4-all-api-endpoints)
5. [Gateway Simulators](#5-gateway-simulators)
6. [Service Layer Specifications](#6-service-layer-specifications)
7. [Webhook Callback Design](#7-webhook-callback-design)
8. [Idempotency Implementation](#8-idempotency-implementation)
9. [Coding Conventions](#9-coding-conventions)

---

## 1. Project Structure

```
payment-service/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── PaymentController.php
│   │   │   ├── RefundController.php
│   │   │   └── PayoutController.php
│   │   ├── Middleware/
│   │   │   └── VerifyServiceToken.php
│   │   └── Requests/
│   │       ├── CreatePaymentRequest.php
│   │       ├── CreateRefundRequest.php
│   │       └── CreatePayoutBatchRequest.php
│   ├── Models/
│   │   ├── Payment.php
│   │   ├── Refund.php
│   │   ├── PayoutBatch.php
│   │   └── PayoutItem.php
│   ├── Services/
│   │   ├── PaymentProcessorService.php   # Orchestrates gateway selection
│   │   ├── RefundProcessorService.php
│   │   ├── PayoutProcessorService.php
│   │   ├── WebhookCallbackService.php    # Sends callbacks to main API
│   │   └── Gateways/
│   │       ├── GatewayInterface.php
│   │       ├── GatewayFactory.php
│   │       ├── StripeSimulatorGateway.php
│   │       └── PayPalSimulatorGateway.php
│   └── Jobs/
│       ├── ProcessPaymentCallbackJob.php  # Async webhook delivery
│       └── ProcessRefundCallbackJob.php
├── database/
│   └── migrations/
├── routes/
│   └── api.php
└── tests/
    └── Unit/
        ├── Gateways/
        │   ├── StripeSimulatorTest.php
        │   └── PayPalSimulatorTest.php
        └── Services/
            ├── PaymentProcessorTest.php
            └── RefundProcessorTest.php
```

---

## 2. Required Packages

```bash
composer require guzzlehttp/guzzle   # HTTP client for webhook callbacks
composer require predis/predis       # Redis (optional — for idempotency cache)

composer require --dev phpunit/phpunit
composer require --dev fakerphp/faker
```

---

## 3. Security Model

### 3.1 Inbound Authentication (Main API → Payment Service)

All requests from the main API must include:

```
X-Service-Token: {PAYMENT_SERVICE_SECRET}
```

**Middleware: `VerifyServiceToken`**

```php
class VerifyServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Service-Token');

        if (!$token || !hash_equals(config('services.main_api_secret'), $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
```

**Applied to:** All routes in `routes/api.php`

### 3.2 Outbound Authentication (Payment Service → Main API)

Webhook callbacks to the main API include an HMAC signature:

```
X-Webhook-Signature: HMAC-SHA256(json_payload, WEBHOOK_SECRET)
X-Webhook-Timestamp: {unix_timestamp}
```

The main API verifies this signature before processing any webhook.

### 3.3 No Public Access

The payment service must **never** be exposed to the public internet. In Docker Compose, it is on an internal network only. The only service that can reach it is the main API.

---

## 4. All API Endpoints

All endpoints require `X-Service-Token` header.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Health check (no auth required) |
| POST | `/api/payments` | Create and process a payment |
| GET | `/api/payments/{id}` | Get payment status |
| POST | `/api/refunds` | Process a refund |
| GET | `/api/refunds/{id}` | Get refund status |
| POST | `/api/payouts/batch` | Process a payout batch |
| GET | `/api/payouts/batch/{id}` | Get payout batch status |

### 4.1 POST /api/payments

**Request:**
```json
{
  "order_id": 42,
  "amount": 450.00,
  "currency": "MYR",
  "gateway": "stripe_sim",
  "idempotency_key": "uuid-v4-from-main-api",
  "callback_url": "http://main-api:8000/api/v1/webhooks/payment",
  "metadata": {
    "attendee_id": 10,
    "event_id": 1
  }
}
```

**Response (201):**
```json
{
  "payment_id": "pay_abc123def456",
  "status": "pending",
  "gateway": "stripe_sim",
  "amount": 450.00,
  "currency": "MYR",
  "estimated_callback_delay_ms": 2000
}
```

**Response (409 — Duplicate idempotency key):**
```json
{
  "payment_id": "pay_abc123def456",
  "status": "completed",
  "message": "Payment already processed with this idempotency key"
}
```

**Validation:**
- `order_id`: required, integer
- `amount`: required, numeric, min:0.01
- `currency`: required, string, size:3
- `gateway`: required, in:stripe_sim,paypal_sim
- `idempotency_key`: required, string, max:255
- `callback_url`: required, url

### 4.2 POST /api/refunds

**Request:**
```json
{
  "payment_id": "pay_abc123def456",
  "refund_amount": 450.00,
  "idempotency_key": "refund-uuid-v4",
  "callback_url": "http://main-api:8000/api/v1/webhooks/refund",
  "metadata": {
    "refund_id": 7,
    "order_id": 42
  }
}
```

**Response (201):**
```json
{
  "refund_id": "ref_xyz789",
  "payment_id": "pay_abc123def456",
  "status": "processing",
  "refund_amount": 450.00,
  "estimated_callback_delay_ms": 1500
}
```

### 4.3 POST /api/payouts/batch

**Request:**
```json
{
  "batch_id": "payout_batch_2026-05-07",
  "callback_url": "http://main-api:8000/api/v1/webhooks/payout",
  "payouts": [
    {
      "vendor_id": 5,
      "payout_id": 12,
      "amount": 4500.00,
      "currency": "MYR",
      "bank_details": {
        "account_name": "Tech Events Sdn Bhd",
        "account_number": "1234567890",
        "bank_name": "Maybank",
        "swift_code": "MBBEMYKL"
      }
    }
  ]
}
```

**Response (202 — Accepted for processing):**
```json
{
  "batch_id": "payout_batch_2026-05-07",
  "status": "processing",
  "total_payouts": 1,
  "message": "Batch accepted for processing"
}
```

---

## 5. Gateway Simulators

### 5.1 GatewayInterface

```php
interface GatewayInterface
{
    /**
     * Process a payment. Returns result with success/failure.
     */
    public function processPayment(array $paymentData): array;

    /**
     * Process a refund.
     */
    public function processRefund(array $refundData): array;

    /**
     * Process a payout to a vendor.
     */
    public function processPayout(array $payoutData): array;

    /**
     * Get the gateway name identifier.
     */
    public function getName(): string;
}
```

### 5.2 StripeSimulatorGateway

```php
class StripeSimulatorGateway implements GatewayInterface
{
    private float $successRate;

    public function __construct()
    {
        // Configurable via .env: STRIPE_SIM_SUCCESS_RATE=0.9
        $this->successRate = (float) config('gateways.stripe_sim.success_rate', 0.9);
    }

    public function processPayment(array $paymentData): array
    {
        $success = (mt_rand(1, 100) / 100) <= $this->successRate;

        return [
            'external_id'    => 'stripe_' . uniqid(),
            'status'         => $success ? 'completed' : 'failed',
            'gateway'        => 'stripe_sim',
            'amount'         => $paymentData['amount'],
            'currency'       => $paymentData['currency'],
            'processed_at'   => now()->toIso8601String(),
            'failure_reason' => $success ? null : 'Card declined (simulated)',
            'raw_response'   => [
                'id'     => 'stripe_' . uniqid(),
                'object' => 'charge',
                'status' => $success ? 'succeeded' : 'failed',
            ],
        ];
    }

    public function processRefund(array $refundData): array
    {
        // Refunds always succeed in simulation (realistic — refunds rarely fail)
        return [
            'external_refund_id' => 'stripe_ref_' . uniqid(),
            'status'             => 'completed',
            'amount'             => $refundData['refund_amount'],
            'processed_at'       => now()->toIso8601String(),
        ];
    }

    public function processPayout(array $payoutData): array
    {
        $success = (mt_rand(1, 100) / 100) <= $this->successRate;

        return [
            'external_payout_id' => 'stripe_po_' . uniqid(),
            'status'             => $success ? 'completed' : 'failed',
            'amount'             => $payoutData['amount'],
            'processed_at'       => now()->toIso8601String(),
            'failure_reason'     => $success ? null : 'Bank transfer failed (simulated)',
        ];
    }

    public function getName(): string { return 'stripe_sim'; }
}
```

### 5.3 PayPalSimulatorGateway

```php
class PayPalSimulatorGateway implements GatewayInterface
{
    private float $successRate;

    public function __construct()
    {
        // Configurable via .env: PAYPAL_SIM_SUCCESS_RATE=0.85
        $this->successRate = (float) config('gateways.paypal_sim.success_rate', 0.85);
    }

    public function processPayment(array $paymentData): array
    {
        $success = (mt_rand(1, 100) / 100) <= $this->successRate;

        return [
            'external_id'    => 'paypal_' . uniqid(),
            'status'         => $success ? 'completed' : 'failed',
            'gateway'        => 'paypal_sim',
            'amount'         => $paymentData['amount'],
            'currency'       => $paymentData['currency'],
            'processed_at'   => now()->toIso8601String(),
            'failure_reason' => $success ? null : 'PayPal account restricted (simulated)',
            'raw_response'   => [
                'id'     => 'paypal_' . uniqid(),
                'intent' => 'CAPTURE',
                'status' => $success ? 'COMPLETED' : 'FAILED',
            ],
        ];
    }

    // processRefund() and processPayout() follow same pattern as Stripe
    public function getName(): string { return 'paypal_sim'; }
}
```

### 5.4 GatewayFactory

```php
class GatewayFactory
{
    public static function make(string $gateway): GatewayInterface
    {
        return match($gateway) {
            'stripe_sim'  => new StripeSimulatorGateway(),
            'paypal_sim'  => new PayPalSimulatorGateway(),
            default       => throw new \InvalidArgumentException("Unknown gateway: {$gateway}"),
        };
    }
}
```

---

## 6. Service Layer Specifications

### 6.1 PaymentProcessorService

```php
class PaymentProcessorService
{
    public function __construct(
        private WebhookCallbackService $webhookService,
    ) {}

    /**
     * Process a payment request.
     * 1. Check idempotency
     * 2. Create payment record (status: processing)
     * 3. Dispatch async job to simulate processing delay + callback
     */
    public function processPayment(array $data): Payment
    {
        // 1. Idempotency check
        $existing = Payment::where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            return $existing;
        }

        // 2. Create payment record
        $payment = Payment::create([
            'order_id'        => $data['order_id'],
            'gateway'         => $data['gateway'],
            'amount'          => $data['amount'],
            'currency'        => $data['currency'],
            'status'          => 'processing',
            'idempotency_key' => $data['idempotency_key'],
            'callback_url'    => $data['callback_url'],
            'metadata'        => $data['metadata'] ?? [],
        ]);

        // 3. Dispatch async job (simulates processing delay)
        ProcessPaymentCallbackJob::dispatch($payment)->delay(
            now()->addMilliseconds(config('gateways.callback_delay_ms', 2000))
        );

        return $payment;
    }
}
```

### 6.2 ProcessPaymentCallbackJob

```php
class ProcessPaymentCallbackJob implements ShouldQueue
{
    public function __construct(private Payment $payment) {}

    public function handle(WebhookCallbackService $webhookService): void
    {
        // Process through gateway
        $gateway = GatewayFactory::make($this->payment->gateway);
        $result = $gateway->processPayment($this->payment->toArray());

        // Update payment record
        $this->payment->update([
            'status'           => $result['status'],
            'external_id'      => $result['external_id'],
            'gateway_response' => $result['raw_response'] ?? [],
            'processed_at'     => now(),
        ]);

        // Send webhook callback to main API
        $webhookService->sendPaymentCallback($this->payment, $result);
    }
}
```

### 6.3 WebhookCallbackService

```php
class WebhookCallbackService
{
    /**
     * Send payment result callback to main API.
     * Includes HMAC signature for verification.
     */
    public function sendPaymentCallback(Payment $payment, array $result): void
    {
        $payload = [
            'payment_id'      => $payment->id,
            'order_id'        => $payment->order_id,
            'status'          => $result['status'],
            'amount'          => $payment->amount,
            'currency'        => $payment->currency,
            'gateway'         => $payment->gateway,
            'external_id'     => $result['external_id'],
            'processed_at'    => $result['processed_at'],
            'idempotency_key' => $payment->idempotency_key,
            'failure_reason'  => $result['failure_reason'] ?? null,
        ];

        $this->sendWithSignature($payment->callback_url, $payload);
    }

    public function sendRefundCallback(Refund $refund, array $result): void
    {
        $payload = [
            'refund_id'          => $refund->metadata['refund_id'] ?? null,
            'payment_id'         => $refund->payment_id,
            'order_id'           => $refund->metadata['order_id'] ?? null,
            'status'             => $result['status'],
            'refund_amount'      => $refund->refund_amount,
            'external_refund_id' => $result['external_refund_id'],
            'processed_at'       => $result['processed_at'],
            'idempotency_key'    => $refund->idempotency_key,
        ];

        $this->sendWithSignature($refund->callback_url, $payload);
    }

    private function sendWithSignature(string $url, array $payload): void
    {
        $body      = json_encode($payload);
        $timestamp = time();
        $signature = hash_hmac('sha256', $body, config('services.webhook_secret'));

        Http::withHeaders([
            'Content-Type'         => 'application/json',
            'X-Webhook-Signature'  => $signature,
            'X-Webhook-Timestamp'  => $timestamp,
        ])->post($url, $payload);
    }
}
```

### 6.4 PayoutProcessorService

```php
class PayoutProcessorService
{
    /**
     * Process a payout batch.
     * Each payout is processed individually.
     * Partial failures are recorded; batch continues.
     */
    public function processBatch(array $batchData): array
    {
        $results = [];

        foreach ($batchData['payouts'] as $payoutData) {
            try {
                // Use vendor's preferred gateway (default to stripe_sim)
                $gateway = GatewayFactory::make('stripe_sim');
                $result  = $gateway->processPayout($payoutData);

                $results[] = [
                    'vendor_id'          => $payoutData['vendor_id'],
                    'payout_id'          => $payoutData['payout_id'],
                    'status'             => $result['status'],
                    'external_payout_id' => $result['external_payout_id'],
                    'failure_reason'     => $result['failure_reason'] ?? null,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'vendor_id'      => $payoutData['vendor_id'],
                    'payout_id'      => $payoutData['payout_id'],
                    'status'         => 'failed',
                    'failure_reason' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
```

---

## 7. Webhook Callback Design

### 7.1 Callback Flow

```
Payment Service
    │
    │ [After processing delay]
    │
    ├─► Determine result (success/failure based on success rate)
    ├─► Update payment record in payment DB
    │
    │ POST {callback_url}
    │ Headers:
    │   X-Webhook-Signature: HMAC-SHA256(body, WEBHOOK_SECRET)
    │   X-Webhook-Timestamp: {unix_timestamp}
    │ Body: { payment_id, order_id, status, amount, ... }
    ▼
Main API
    │
    ├─► Verify signature: HMAC-SHA256(body, WEBHOOK_SECRET) == header value
    ├─► Check timestamp: reject if > 5 minutes old (replay attack prevention)
    ├─► Check idempotency: has this payment_id been processed?
    └─► Process order state change
```

### 7.2 Callback Payload Schemas

**Payment Callback:**
```json
{
  "payment_id": "pay_abc123",
  "order_id": 42,
  "status": "completed",
  "amount": 450.00,
  "currency": "MYR",
  "gateway": "stripe_sim",
  "external_id": "stripe_abc123",
  "processed_at": "2026-05-07T12:05:00Z",
  "idempotency_key": "uuid-v4",
  "failure_reason": null
}
```

**Refund Callback:**
```json
{
  "refund_id": 7,
  "payment_id": "pay_abc123",
  "order_id": 42,
  "status": "completed",
  "refund_amount": 450.00,
  "external_refund_id": "stripe_ref_xyz",
  "processed_at": "2026-05-07T14:00:00Z",
  "idempotency_key": "refund-uuid-v4"
}
```

**Payout Batch Callback:**
```json
{
  "batch_id": "payout_batch_2026-05-07",
  "status": "completed",
  "results": [
    {
      "vendor_id": 5,
      "payout_id": 12,
      "status": "completed",
      "external_payout_id": "stripe_po_abc",
      "failure_reason": null
    },
    {
      "vendor_id": 6,
      "payout_id": 13,
      "status": "failed",
      "external_payout_id": null,
      "failure_reason": "Bank transfer failed (simulated)"
    }
  ]
}
```

---

## 8. Idempotency Implementation

### 8.1 Payment Idempotency

```php
// In PaymentProcessorService::processPayment()
$existing = Payment::where('idempotency_key', $data['idempotency_key'])->first();

if ($existing) {
    // Return existing payment — do NOT process again
    Log::info('Duplicate payment request', [
        'idempotency_key' => $data['idempotency_key'],
        'existing_payment_id' => $existing->id,
    ]);
    return $existing;
}
```

### 8.2 Refund Idempotency

```php
// In RefundProcessorService::processRefund()
$existing = Refund::where('idempotency_key', $data['idempotency_key'])->first();

if ($existing) {
    return $existing;
}
```

### 8.3 Database Schema for Idempotency

```sql
-- payments table
ALTER TABLE payments ADD UNIQUE INDEX idx_payments_idempotency (idempotency_key);

-- refunds table
ALTER TABLE refunds ADD UNIQUE INDEX idx_refunds_idempotency (idempotency_key);
```

---

## 9. Coding Conventions

### 9.1 Environment Variables

```env
# .env
APP_NAME=EventHub-PaymentService
APP_ENV=local
APP_PORT=8001

DB_HOST=mysql
DB_DATABASE=eventhub_payments
DB_USERNAME=root
DB_PASSWORD=secret

# Security
MAIN_API_SECRET=your-shared-secret-here
WEBHOOK_SECRET=your-webhook-secret-here

# Gateway Configuration
STRIPE_SIM_SUCCESS_RATE=0.9
PAYPAL_SIM_SUCCESS_RATE=0.85
GATEWAY_CALLBACK_DELAY_MS=2000

# Main API
MAIN_API_URL=http://main-api:8000
```

### 9.2 Config File (config/gateways.php)

```php
return [
    'stripe_sim' => [
        'success_rate' => env('STRIPE_SIM_SUCCESS_RATE', 0.9),
    ],
    'paypal_sim' => [
        'success_rate' => env('PAYPAL_SIM_SUCCESS_RATE', 0.85),
    ],
    'callback_delay_ms' => env('GATEWAY_CALLBACK_DELAY_MS', 2000),
];
```

### 9.3 Database Models

**Payment Model:**
```php
class Payment extends Model
{
    protected $fillable = [
        'order_id', 'gateway', 'amount', 'currency', 'status',
        'idempotency_key', 'external_id', 'callback_url',
        'gateway_response', 'metadata', 'processed_at',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'gateway_response' => 'array',
        'metadata'         => 'array',
        'processed_at'     => 'datetime',
    ];
}
```

### 9.4 Running Tests

```bash
cd payment-service
php artisan test --filter=StripeSimulatorTest
php artisan test --filter=PaymentProcessorTest
php artisan test  # Run all tests
```

### 9.5 Key Test Cases

```php
class PaymentProcessorTest extends TestCase
{
    /** @test */
    public function it_returns_existing_payment_for_duplicate_idempotency_key()

    /** @test */
    public function it_creates_payment_record_with_processing_status()

    /** @test */
    public function it_dispatches_callback_job_after_payment_creation()
}

class StripeSimulatorTest extends TestCase
{
    /** @test */
    public function it_succeeds_at_configured_success_rate()

    /** @test */
    public function it_fails_at_configured_failure_rate()

    /** @test */
    public function it_always_succeeds_on_refunds()
}
```
