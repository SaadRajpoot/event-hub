# EventHub — AI Agent Master Guide (CLAUDE.md)

**Version:** 1.0  
**Date:** 2026-05-07  
**Purpose:** This file is the single source of truth for any AI coding agent (Claude, Copilot, etc.) working on this project. Read this file first before touching any code.

---

## 🗺️ Project Overview

EventHub is a multi-service event ticketing platform built as a Proof of Concept. It consists of 4 services:

| Service | Stack | Port | Directory |
|---------|-------|------|-----------|
| Main API | Laravel 11 / PHP 8.2+ | 8000 | `main-api/` |
| Payment Service | Laravel 11 / PHP 8.2+ | 8001 | `payment-service/` |
| Notification Service | Node.js 20 / Express | 8002 | `notification-service/` |
| Frontend | Next.js 14 / TypeScript | 3000 | `frontend/` |

**Infrastructure:** Docker Compose, MySQL 8.0, Redis 7, RabbitMQ 3.12

---

## 📁 Documentation Map

Before writing any code, read the relevant spec:

| What you're building | Read this file |
|---------------------|----------------|
| Any feature overview | `docs/requirement-analysis.md` |
| How services connect | `docs/system-architecture.md` |
| Why decisions were made | `docs/technical-decision-log.md` |
| Main API endpoints, services, models | `docs/services/main-api.md` |
| Payment processing, gateway simulators | `docs/services/payment-service.md` |
| RabbitMQ consumers, retry logic | `docs/services/notification-service.md` |
| Frontend pages, components, routing | `docs/services/frontend.md` |

---

## 🚦 Build Order (Critical — Follow This Sequence)

Build services in this order. Do NOT start a later phase before the earlier one is working.

```
Phase 1: Infrastructure
  └── docker-compose.yml (MySQL, Redis, RabbitMQ, all 4 services)

Phase 2: Main API
  └── Follow phases in docs/services/main-api.md

Phase 3: Payment Service
  └── Follow phases in docs/services/payment-service.md

Phase 4: Notification Service
  └── Follow phases in docs/services/notification-service.md

Phase 5: Frontend
  └── Follow phases in docs/services/frontend.md
```

---

## ⚠️ Critical Rules — Never Violate These

### 1. Inventory Safety (Main API)
- **ALWAYS** use Redis distributed locks when decrementing ticket inventory
- **ALWAYS** use `SELECT FOR UPDATE` inside a DB transaction when checking + decrementing inventory
- **NEVER** check inventory and then decrement in two separate queries without a lock
- See: `docs/services/main-api.md` → Section 5.1 (DistributedLockService) and 5.2 (OrderService)

### 2. Idempotency
- **ALWAYS** check idempotency keys before processing payments, refunds, or orders
- **NEVER** process the same `idempotency_key` twice
- Applies to: `OrderService::createOrder()`, `PaymentProcessorService::processPayment()`, `RefundProcessorService::processRefund()`

### 3. Commission Snapshot
- **ALWAYS** snapshot the commission rate at order creation time into `orders.platform_commission_rate`
- **NEVER** recalculate commission from current platform settings when processing payouts
- The rate at time of sale is what matters, not the current rate

### 4. Payout Double-Payment Prevention
- **ALWAYS** check `payout_order_items` before including an order in a payout batch
- **NEVER** pay out the same order twice
- See: `docs/services/main-api.md` → Section 5.3 (PayoutService)

### 5. Payment Service Isolation
- The payment service **MUST NOT** be accessible from the public internet
- All communication is internal Docker network only
- Main API → Payment Service: `X-Service-Token` header required
- Payment Service → Main API: HMAC-SHA256 webhook signature required

### 6. Architecture Pattern (Main API)
- **ALWAYS** follow: Controller → Service → Repository → Model
- **NEVER** put business logic in controllers
- **NEVER** put Eloquent queries directly in controllers or services (use repositories)
- See: `docs/services/main-api.md` → Section 3

---

## 🗄️ Database Schema Quick Reference

### Main API Database (`eventhub`)

**Core tables and their key columns:**

```
users               id, name, email, password, role (admin/vendor/attendee)
vendors             id, user_id, business_name, kyc_status, commission_rate_override, bank_details (json)
attendees           id, user_id, phone
platform_settings   id, key, value  (e.g. default_commission_rate = 0.10)

events              id, vendor_id, title, status (draft/published/ongoing/completed/cancelled)
                    start_datetime, end_datetime, timezone, max_capacity

ticket_types        id, event_id, name, type (general_admission/vip/early_bird/group_bundle)
                    price, quantity, quantity_sold, quantity_held
                    group_min_quantity, group_discount_percent

orders              id, order_number, attendee_id, event_id, status
                    subtotal, platform_commission_rate, platform_commission_amount, vendor_amount
                    idempotency_key, expires_at, paid_at

order_items         id, order_id, ticket_type_id, quantity, unit_price, line_total
                    qr_code_token, is_checked_in, checked_in_at

payments            id, order_id, gateway, amount, status, external_payment_id, idempotency_key
refunds             id, order_id, attendee_id, amount, status, policy, reason
payouts             id, vendor_id, amount, status, batch_id
payout_batches      id, batch_reference, status, total_vendors, processed_vendors
payout_order_items  id, payout_id, order_id  (junction — prevents double payout)

waitlists           id, event_id, attendee_id, ticket_type_id, status
audit_logs          id, user_id, action, entity_type, entity_id, changes (json)
```

### Payment Service Database (`eventhub_payments`)

```
payments    id, order_id, gateway, amount, currency, status, idempotency_key, external_id, callback_url
refunds     id, payment_id, refund_amount, status, idempotency_key, callback_url, metadata (json)
payout_batches  id, batch_id, status, results (json)
```

---

## 🔄 Key Business Flows

### Flow 1: Ticket Purchase

```
1. Attendee selects tickets → POST /attendee/orders
2. Main API acquires Redis lock per ticket_type
3. DB transaction: check inventory → create order → decrement quantity_held
4. Release locks → return order (status: pending_payment, expires in 15 min)
5. Attendee selects gateway → POST /attendee/payments/initiate
6. Main API calls Payment Service: POST /api/payments (with callback_url)
7. Payment Service processes async → calls back POST /api/v1/webhooks/payment
8. Main API verifies HMAC → updates order status to 'paid'
9. Main API: increment quantity_sold, decrement quantity_held
10. Main API publishes to RabbitMQ: email.order_confirmation + webhook.vendor_event
11. Notification Service consumes → logs email + delivers vendor webhook
```

### Flow 2: Refund Request

```
1. Attendee requests refund → POST /attendee/refunds
2. RefundService calculates amount based on time policy:
   - >48h before event: 100% refund
   - 24-48h before event: 50% refund
   - <24h before event: 0% refund
   - Event cancelled: 100% refund always
3. Refund created (status: pending_admin_approval)
4. Admin approves → POST /admin/refunds/{id}/approve
5. Main API calls Payment Service: POST /api/refunds
6. Payment Service processes → calls back POST /api/v1/webhooks/refund
7. Main API updates order status to 'refunded'
```

### Flow 3: Vendor Payout

```
1. ProcessPayoutBatchJob runs daily at 02:00
2. Check if batch already ran today (idempotency)
3. For each vendor with balance > minimum threshold (MYR 100):
   a. Calculate pending balance (paid orders not yet paid out)
   b. Create Payout record (status: pending)
   c. Add order IDs to payout_order_items
4. Call Payment Service: POST /api/payouts/batch
5. Payment Service processes each payout → calls back POST /api/v1/webhooks/payout
6. Main API updates each Payout status (completed/failed)
7. Publish email.payout_completed for successful payouts
```

---

## 🧪 Testing Checkpoints

After completing each service, verify these scenarios work end-to-end:

### Main API Checkpoints
- [ ] Two concurrent requests for the last ticket → only one succeeds
- [ ] Order expires after 15 minutes → inventory restored
- [ ] Duplicate `idempotency_key` → returns existing order, no duplicate
- [ ] Refund >48h before event → 100% refund amount
- [ ] Refund <24h before event → 0% refund amount
- [ ] Payout batch runs → `payout_order_items` populated → second run skips same orders

### Payment Service Checkpoints
- [ ] Duplicate payment request (same `idempotency_key`) → returns existing, no double charge
- [ ] Gateway failure (set success rate to 0) → callback sent with `status: failed`
- [ ] Payout batch with one failure → other payouts still complete

### Notification Service Checkpoints
- [ ] Message consumed → appears in `logs/notifications.log`
- [ ] Handler throws error → message retried with backoff
- [ ] After 5 failures → message in dead-letter queue

### Frontend Checkpoints
- [ ] Vendor route accessed without vendor role → redirect to /login
- [ ] Payment page polls order status → updates UI when paid
- [ ] Checkout with group bundle minimum met → discount shown

---

## 🐳 Docker Compose Structure

```yaml
# Expected services in docker-compose.yml:
services:
  mysql:        # Port 3306, databases: eventhub, eventhub_payments
  redis:        # Port 6379
  rabbitmq:     # Port 5672 (AMQP), 15672 (Management UI)
  main-api:     # Port 8000, depends_on: mysql, redis, rabbitmq
  payment-service:  # Port 8001, depends_on: mysql (internal network only)
  notification-service:  # Port 8002, depends_on: rabbitmq
  frontend:     # Port 3000, depends_on: main-api
```

**Network rule:** `payment-service` must be on an internal network not exposed to the host.

---

## 📋 Environment Variables Summary

### main-api/.env
```
APP_PORT=8000
DB_DATABASE=eventhub
REDIS_HOST=redis
RABBITMQ_HOST=rabbitmq
PAYMENT_SERVICE_URL=http://payment-service:8001
PAYMENT_SERVICE_SECRET=<shared-secret>
WEBHOOK_SECRET=<webhook-hmac-secret>
SANCTUM_STATEFUL_DOMAINS=localhost:3000
```

### payment-service/.env
```
APP_PORT=8001
DB_DATABASE=eventhub_payments
MAIN_API_SECRET=<shared-secret>   # Must match PAYMENT_SERVICE_SECRET above
WEBHOOK_SECRET=<webhook-hmac-secret>  # Must match main-api WEBHOOK_SECRET
STRIPE_SIM_SUCCESS_RATE=0.9
PAYPAL_SIM_SUCCESS_RATE=0.85
GATEWAY_CALLBACK_DELAY_MS=2000
```

### notification-service/.env
```
PORT=8002
RABBITMQ_URL=amqp://guest:guest@rabbitmq:5672
LOG_FILE_PATH=./logs
```

### frontend/.env.local
```
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
```

---

## 🔑 Shared Secrets

The following secrets must be identical across services:

| Secret | Used By | Purpose |
|--------|---------|---------|
| `PAYMENT_SERVICE_SECRET` / `MAIN_API_SECRET` | main-api ↔ payment-service | Authenticate service-to-service calls |
| `WEBHOOK_SECRET` | main-api + payment-service | HMAC sign/verify webhook callbacks |

For local development, use any non-empty string. For production, use `openssl rand -hex 32`.

---

## 🚫 Common Mistakes to Avoid

1. **Don't use `$guarded = []` in Laravel models** — always use explicit `$fillable`
2. **Don't put business logic in controllers** — controllers call one service method only
3. **Don't forget to release Redis locks** — use `withLock()` wrapper, not manual acquire/release
4. **Don't recalculate commission at payout time** — use `orders.platform_commission_rate`
5. **Don't expose payment-service port to host** — internal Docker network only
6. **Don't ack RabbitMQ messages before processing** — ack only after successful handler
7. **Don't use `Math.random()` for security** — use `crypto.randomBytes()` in Node.js
8. **Don't store raw bank account numbers** — store masked version for display, full in encrypted field
9. **Don't skip idempotency checks** — every payment, refund, and order creation needs one
10. **Don't run payout batch twice on same day** — check `payout_batches.batch_reference` first
