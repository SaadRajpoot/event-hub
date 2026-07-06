# EventHub — System Architecture Document

**Version:** 1.0  
**Date:** 2026-05-07  
**Author:** Technical Team Lead  
**Assessment:** NEXT Ventures — Technical Team Lead Take-Home

---

## Table of Contents

1. [High-Level Architecture Overview](#1-high-level-architecture-overview)
2. [Service Communication Map](#2-service-communication-map)
3. [Authentication & Authorization Strategy](#3-authentication--authorization-strategy)
4. [Database Design & ERD](#4-database-design--erd)
5. [API Contract Design](#5-api-contract-design)
6. [Background Job Design](#6-background-job-design)
7. [Infrastructure & Deployment](#7-infrastructure--deployment)

---

## 1. High-Level Architecture Overview

EventHub is composed of **4 services** in a microservices architecture:

```
┌─────────────────────────────────────────────────────────────────────┐
│                          CLIENT LAYER                               │
│                                                                     │
│   ┌─────────────────────────────────────────────────────────────┐   │
│   │              Next.js Frontend (Port 3000)                   │   │
│   │   Vendor Dashboard | Attendee Pages | Admin Panel           │   │
│   └──────────────────────────┬──────────────────────────────────┘   │
└─────────────────────────────┼───────────────────────────────────────┘
                              │ HTTPS / REST API
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        CORE SERVICE LAYER                           │
│                                                                     │
│   ┌─────────────────────────────────────────────────────────────┐   │
│   │           Main API — Laravel 11 (Port 8000)                 │   │
│   │                                                             │   │
│   │  • Event Management      • Order Processing                 │   │
│   │  • Ticket Inventory      • Payout Management                │   │
│   │  • Vendor Onboarding     • Admin Controls                   │   │
│   │  • Auth (Sanctum)        • Background Jobs (Scheduler)      │   │
│   └──────┬──────────────────────────────────┬───────────────────┘   │
│          │                                  │                       │
│          │ HTTP (internal)                  │ Redis Pub/Sub         │
│          ▼                                  ▼                       │
│   ┌──────────────────┐          ┌───────────────────────────────┐   │
│   │  Payment Service │          │   Notification Service        │   │
│   │  Laravel (8001)  │          │   Node.js (8002)              │   │
│   │                  │          │                               │   │
│   │ • Gateway Sim    │          │ • Email (simulated)           │   │
│   │ • Webhooks       │          │ • Vendor Webhooks             │   │
│   │ • Refunds        │          │ • Queue Consumer              │   │
│   │ • Payouts        │          │ • Retry + Dead-letter         │   │
│   └──────────────────┘          └───────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────┼───────────────────────────────────────┐
│                    INFRASTRUCTURE LAYER         │                   │
│                                                 │                   │
│   ┌──────────────┐   ┌──────────────┐   ┌──────┴───────┐           │
│   │  MySQL 8.0   │   │  Redis 7.x   │   │  RabbitMQ    │           │
│   │  (Port 3306) │   │  (Port 6379) │   │  (Port 5672) │           │
│   │              │   │              │   │              │           │
│   │ Main DB      │   │ • Locks      │   │ • Notif Queue│           │
│   │ Payment DB   │   │ • Cache      │   │ • Dead-letter│           │
│   │              │   │ • Sessions   │   │              │           │
│   └──────────────┘   └──────────────┘   └──────────────┘           │
└─────────────────────────────────────────────────────────────────────┘
```

### Service Responsibilities Summary

| Service | Technology | Port | Primary Responsibility |
|---------|-----------|------|----------------------|
| Frontend | Next.js 14 | 3000 | UI for all three stakeholder types |
| Main API | Laravel 11 / PHP 8.2 | 8000 | Core business logic, orchestration |
| Payment Service | Laravel 11 / PHP 8.2 | 8001 | Payment processing simulation, refunds, payouts |
| Notification Service | Node.js 20 / Express | 8002 | Queue-driven notifications, vendor webhooks |
| MySQL | MySQL 8.0 | 3306 | Persistent data storage |
| Redis | Redis 7.x | 6379 | Distributed locks, caching, sessions |
| RabbitMQ | RabbitMQ 3.x | 5672 | Notification message queue |

---

## 2. Service Communication Map

### 2.1 Communication Protocols

| From | To | Protocol | Auth Method | Direction |
|------|----|----------|-------------|-----------|
| Frontend | Main API | HTTPS REST | Bearer token (Sanctum) | Request/Response |
| Main API | Payment Service | HTTP REST | Shared Secret (X-Service-Token header) | Request/Response |
| Payment Service | Main API | HTTP REST (webhook) | Shared Secret (X-Webhook-Secret header) | Callback |
| Main API | Notification Service | RabbitMQ | Queue credentials | Async publish |
| Notification Service | Vendor URLs | HTTP POST | HMAC signature | Outbound webhook |
| Main API | Redis | Redis protocol | Password auth | Direct |
| Notification Service | RabbitMQ | AMQP | Username/password | Consumer |

### 2.2 Data Flow: Order Purchase

```
Attendee (Browser)
    │
    │ POST /api/v1/orders
    ▼
Main API
    │
    ├─► Redis: Acquire lock on ticket_type_ids
    │
    ├─► MySQL: BEGIN TRANSACTION
    │       SELECT ticket_types FOR UPDATE
    │       Check inventory
    │       INSERT order + order_items
    │       UPDATE quantity_held
    │   COMMIT
    │
    ├─► Redis: Release lock
    │
    │ Returns: { order_id, payment_url, expires_at }
    ▼
Attendee (Browser)
    │
    │ POST /api/v1/payments/initiate (via Main API)
    ▼
Main API
    │
    │ POST http://payment-service/api/payments
    │     { order_id, amount, gateway, idempotency_key }
    ▼
Payment Service
    │
    ├─► Simulate payment processing (async)
    │
    │ Returns: { payment_id, status: "pending" }
    ▼
Main API → Attendee: { payment_id, status: "pending" }

[After delay — Payment Service webhook]

Payment Service
    │
    │ POST http://main-api/api/v1/webhooks/payment
    │     { payment_id, order_id, status: "success"|"failed", ... }
    ▼
Main API
    │
    ├─► Verify webhook signature
    ├─► Check idempotency (has this webhook been processed?)
    ├─► MySQL: Update order status
    │         Update quantity_sold / restore quantity_held
    │
    └─► RabbitMQ: Publish "order.confirmed" notification job
```

### 2.3 Data Flow: Payout Processing

```
Cron Job (Daily — Main API)
    │
    ├─► Calculate pending vendor balances
    │   (paid orders - already paid out - commission)
    │
    ├─► Filter: balance >= minimum_threshold AND vendor KYC verified
    │
    ├─► Create payout_batch record
    │
    │ POST http://payment-service/api/payouts/batch
    │     { batch_id, payouts: [{ vendor_id, amount, bank_details }] }
    ▼
Payment Service
    │
    ├─► Process each payout individually
    ├─► Mark each as completed/failed
    │
    │ POST http://main-api/api/v1/webhooks/payout
    │     { batch_id, results: [...] }
    ▼
Main API
    │
    ├─► Update payout records
    └─► RabbitMQ: Publish "payout.completed" notification per vendor
```

### 2.4 Data Flow: Notification Delivery

```
Main API
    │
    │ Publish to RabbitMQ exchange: "eventhub.notifications"
    │ Routing key: "email.order_confirmation" | "email.reminder" | etc.
    │ Payload: { type, recipient, data, idempotency_key }
    ▼
RabbitMQ
    │
    │ Routes to queue: "notifications.email" | "notifications.webhook"
    ▼
Notification Service (Consumer)
    │
    ├─► Process notification
    ├─► Log to file (email simulation)
    │   OR POST to vendor webhook URL
    │
    ├─► On success: ACK message, record delivery status = "sent"
    │
    └─► On failure: NACK message
            │
            ├─► Retry with exponential backoff (1s, 4s, 16s, 64s, 256s)
            ├─► Max 5 retries
            └─► After 5 failures: route to dead-letter queue
                                  record delivery status = "failed"
```

---

## 3. Authentication & Authorization Strategy

### 3.1 Frontend ↔ Main API (User Auth)

**Method:** Laravel Sanctum API tokens

**Flow:**
```
POST /api/v1/auth/login
  → Validates credentials
  → Returns: { token, user: { id, role, name } }

All subsequent requests:
  Authorization: Bearer {token}
```

**Token Properties:**
- Stored in `personal_access_tokens` table (Sanctum default)
- No expiry for POC (configurable in production)
- Token is hashed in DB (only plaintext returned once at login)
- Logout invalidates the token

**Role Enforcement:**
```php
// Middleware stack per route group
Route::middleware(['auth:sanctum', 'role:vendor'])->group(...)
Route::middleware(['auth:sanctum', 'role:attendee'])->group(...)
Route::middleware(['auth:sanctum', 'role:admin'])->group(...)
```

**Policy-based Authorization:**
- `EventPolicy` — vendor can only manage own events
- `OrderPolicy` — attendee can only view own orders
- `PayoutPolicy` — vendor can only view own payouts

### 3.2 Main API ↔ Payment Service (Service Auth)

**Method:** Shared secret via HTTP header

```
X-Service-Token: {PAYMENT_SERVICE_SECRET}
```

- Secret stored in `.env` on both services
- Payment service validates token on every incoming request
- Main API validates webhook signature:
  ```
  X-Webhook-Signature: HMAC-SHA256(payload, WEBHOOK_SECRET)
  ```

### 3.3 Main API → Notification Service (Queue Auth)

**Method:** RabbitMQ credentials (username/password)
- Messages published by Main API using RabbitMQ connection credentials
- Notification service consumes using its own credentials
- No direct HTTP auth needed (queue handles isolation)

### 3.4 Notification Service → Vendor Webhooks (Outbound)

**Method:** HMAC signature on outbound requests
```
X-EventHub-Signature: HMAC-SHA256(payload, vendor_webhook_secret)
X-EventHub-Timestamp: {unix_timestamp}
```
- Each vendor has a unique `webhook_secret` stored in DB
- Vendors verify the signature on their end

---

## 4. Database Design & ERD

### 4.1 Entity Relationship Diagram

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email UK
        string password
        string role
        timestamp email_verified_at
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    VENDORS {
        bigint id PK
        bigint user_id FK
        string business_name
        string business_description
        string contact_phone
        string kyc_status
        text kyc_rejection_reason
        string bank_account_name
        string bank_account_number
        string bank_name
        string bank_swift_code
        string webhook_url
        string webhook_secret
        decimal commission_rate_override "nullable"
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    ATTENDEES {
        bigint id PK
        bigint user_id FK
        string phone
        timestamp created_at
        timestamp updated_at
    }

    PLATFORM_SETTINGS {
        bigint id PK
        string key UK
        string value
        string description
        timestamp created_at
        timestamp updated_at
    }

    EVENTS {
        bigint id PK
        bigint vendor_id FK
        string title
        text description
        string location_name
        text location_address
        string banner_image
        datetime start_datetime "UTC"
        datetime end_datetime "UTC"
        string timezone "IANA"
        string status
        string cancellation_reason
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    TICKET_TYPES {
        bigint id PK
        bigint event_id FK
        string name
        string type
        decimal price
        int quantity
        int quantity_sold
        int quantity_held
        datetime available_from
        datetime available_until
        int min_purchase
        int max_purchase
        int group_min_quantity
        decimal group_discount_percent
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    ORDERS {
        bigint id PK
        string order_number UK
        bigint attendee_id FK
        bigint event_id FK
        string status
        decimal subtotal
        decimal platform_commission_rate
        decimal platform_commission_amount
        decimal vendor_amount
        string payment_id
        string payment_gateway
        string idempotency_key UK
        datetime expires_at
        datetime paid_at
        timestamp created_at
        timestamp updated_at
    }

    ORDER_ITEMS {
        bigint id PK
        bigint order_id FK
        bigint ticket_type_id FK
        int quantity
        decimal unit_price
        decimal discount_percent
        decimal line_total
        string qr_code_token UK
        boolean is_checked_in
        datetime checked_in_at
        timestamp created_at
        timestamp updated_at
    }

    PAYMENTS {
        bigint id PK
        bigint order_id FK
        string external_payment_id UK
        string gateway
        decimal amount
        string currency
        string status
        string idempotency_key UK
        json gateway_response
        timestamp created_at
        timestamp updated_at
    }

    REFUNDS {
        bigint id PK
        bigint order_id FK
        bigint payment_id FK
        bigint requested_by FK
        bigint reviewed_by FK
        decimal refund_amount
        decimal original_amount
        string refund_policy_applied
        string status
        text reason
        text admin_notes
        string external_refund_id
        datetime reviewed_at
        timestamp created_at
        timestamp updated_at
    }

    PAYOUTS {
        bigint id PK
        bigint vendor_id FK
        bigint payout_batch_id FK
        decimal gross_amount
        decimal commission_amount
        decimal net_amount
        string currency
        string status
        bigint approved_by FK
        datetime approved_at
        string external_payout_id
        json bank_details_snapshot
        timestamp created_at
        timestamp updated_at
    }

    PAYOUT_BATCHES {
        bigint id PK
        string batch_reference UK
        string status
        int total_vendors
        int processed_vendors
        int failed_vendors
        datetime started_at
        datetime completed_at
        timestamp created_at
        timestamp updated_at
    }

    PAYOUT_ORDER_ITEMS {
        bigint id PK
        bigint payout_id FK
        bigint order_id FK
        decimal amount
        timestamp created_at
        timestamp updated_at
    }

    NOTIFICATIONS {
        bigint id PK
        string type
        string recipient_type
        bigint recipient_id
        string recipient_email
        json payload
        string status
        int retry_count
        datetime next_retry_at
        datetime sent_at
        text failure_reason
        string idempotency_key UK
        timestamp created_at
        timestamp updated_at
    }

    VENDOR_WEBHOOK_DELIVERIES {
        bigint id PK
        bigint vendor_id FK
        string event_type
        json payload
        string webhook_url
        int http_status_code
        string status
        int retry_count
        datetime next_retry_at
        datetime delivered_at
        text failure_reason
        timestamp created_at
        timestamp updated_at
    }

    WAITLISTS {
        bigint id PK
        bigint event_id FK
        bigint ticket_type_id FK
        bigint attendee_id FK
        int position
        string status
        datetime notified_at
        datetime expires_at
        timestamp created_at
        timestamp updated_at
    }

    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK
        string action
        string entity_type
        bigint entity_id
        json old_values
        json new_values
        string ip_address
        timestamp created_at
        timestamp updated_at
    }

    USERS ||--o| VENDORS : "has"
    USERS ||--o| ATTENDEES : "has"
    VENDORS ||--o{ EVENTS : "creates"
    EVENTS ||--o{ TICKET_TYPES : "has"
    ATTENDEES ||--o{ ORDERS : "places"
    EVENTS ||--o{ ORDERS : "for"
    ORDERS ||--o{ ORDER_ITEMS : "contains"
    TICKET_TYPES ||--o{ ORDER_ITEMS : "in"
    ORDERS ||--o| PAYMENTS : "has"
    ORDERS ||--o{ REFUNDS : "has"
    PAYMENTS ||--o{ REFUNDS : "refunded via"
    VENDORS ||--o{ PAYOUTS : "receives"
    PAYOUT_BATCHES ||--o{ PAYOUTS : "contains"
    PAYOUTS ||--o{ PAYOUT_ORDER_ITEMS : "covers"
    ORDERS ||--o{ PAYOUT_ORDER_ITEMS : "included in"
    VENDORS ||--o{ VENDOR_WEBHOOK_DELIVERIES : "receives"
    EVENTS ||--o{ WAITLISTS : "has"
    TICKET_TYPES ||--o{ WAITLISTS : "for"
    ATTENDEES ||--o{ WAITLISTS : "joins"
```

### 4.2 Key Design Decisions

#### Normalization Strategy

**Normalized (3NF):**
- `users` → `vendors` / `attendees` (separate profile tables, not polymorphic)
  - *Why:* Vendors and attendees have very different profile fields. Polymorphic would create many nullable columns.
- `events` → `ticket_types` → `order_items` (fully normalized)
  - *Why:* Clean foreign key relationships, easy to query sales per ticket type

**Intentionally Denormalized / Snapshotted:**
- `orders.platform_commission_rate` — snapshot of commission rate at order time
  - *Why:* Commission rate can change; historical orders must reflect the rate that was active
- `orders.subtotal`, `orders.vendor_amount` — calculated and stored
  - *Why:* Avoid recalculation; financial records must be immutable
- `order_items.unit_price` — snapshot of ticket price at purchase time
  - *Why:* Ticket prices can change; order history must show what was actually charged
- `payouts.bank_details_snapshot` — JSON snapshot of vendor bank details at payout time
  - *Why:* Vendor may change bank details; payout record must show where money was sent

#### Indexing Strategy

```sql
-- High-frequency query patterns and their indexes:

-- Event discovery (attendee browsing)
INDEX idx_events_status_start (status, start_datetime)
INDEX idx_events_vendor_id (vendor_id)

-- Ticket availability check (hot path — checkout)
INDEX idx_ticket_types_event_id (event_id)
INDEX idx_ticket_types_available (event_id, is_active, available_from, available_until)

-- Order lookup
UNIQUE idx_orders_order_number (order_number)
UNIQUE idx_orders_idempotency_key (idempotency_key)
INDEX idx_orders_attendee_id (attendee_id)
INDEX idx_orders_event_id (event_id)
INDEX idx_orders_status_expires (status, expires_at)  -- for expiry cron

-- Payment idempotency
UNIQUE idx_payments_idempotency_key (idempotency_key)
UNIQUE idx_payments_external_id (external_payment_id)

-- Payout processing
INDEX idx_payouts_vendor_status (vendor_id, status)
INDEX idx_payouts_batch_id (payout_batch_id)

-- Notification delivery
INDEX idx_notifications_status_retry (status, next_retry_at)
INDEX idx_notifications_idempotency (idempotency_key)

-- Waitlist ordering
INDEX idx_waitlist_ticket_position (ticket_type_id, status, position)

-- Audit log queries
INDEX idx_audit_entity (entity_type, entity_id)
INDEX idx_audit_user (user_id, created_at)
```

#### Audit Trail Strategy

Financial operations are audited at two levels:

1. **Immutable financial records** — `orders`, `payments`, `refunds`, `payouts` are never updated destructively. Status changes are the only mutations. All financial amounts are set once and never changed.

2. **`audit_logs` table** — Captures before/after state for any significant entity change:
   - Order status transitions
   - Payout approvals/rejections
   - Vendor KYC status changes
   - Commission rate changes
   - Admin actions

3. **`payout_order_items`** — Junction table linking each payout to the specific orders it covers. This provides a complete audit trail of "which orders were included in which payout."

#### Soft Delete Strategy

| Entity | Strategy | Reason |
|--------|----------|--------|
| `users` | Soft delete | GDPR compliance; preserve order history |
| `vendors` | Soft delete | Preserve event and payout history |
| `events` | Soft delete | Preserve order history for past events |
| `ticket_types` | No delete (deactivate via `is_active`) | Orders reference ticket types; deletion would break history |
| `orders` | Never deleted | Financial record; immutable |
| `payments` | Never deleted | Financial record; immutable |
| `refunds` | Never deleted | Financial record; immutable |
| `payouts` | Never deleted | Financial record; immutable |
| `notifications` | Hard delete after 90 days | No financial significance; storage management |
| `audit_logs` | Never deleted | Compliance requirement |

---

## 5. API Contract Design

### 5.1 Standard Response Format

All API responses follow this envelope:

```json
// Success
{
  "success": true,
  "data": { ... },
  "message": "Operation completed successfully"
}

// Success with pagination
{
  "success": true,
  "data": {
    "items": [ ... ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 100,
      "last_page": 7
    }
  },
  "message": "Events retrieved successfully"
}

// Error
{
  "success": false,
  "data": null,
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

**HTTP Status Codes:**
- `200` — Success (GET, PUT, PATCH)
- `201` — Created (POST)
- `204` — No Content (DELETE)
- `400` — Bad Request (validation error)
- `401` — Unauthenticated
- `403` — Forbidden (wrong role or resource ownership)
- `404` — Not Found
- `409` — Conflict (lock contention, duplicate idempotency key)
- `422` — Unprocessable Entity (business rule violation)
- `500` — Internal Server Error

### 5.2 Key Endpoint Contracts

---

#### POST /api/v1/events — Create Event

**Auth:** Bearer token (vendor role)

**Request:**
```json
{
  "title": "Tech Conference 2026",
  "description": "Annual technology conference...",
  "location_name": "Kuala Lumpur Convention Centre",
  "location_address": "Jalan Pinang, 50450 Kuala Lumpur",
  "start_datetime": "2026-08-15T09:00:00",
  "end_datetime": "2026-08-15T18:00:00",
  "timezone": "Asia/Kuala_Lumpur",
  "banner_image": "base64_encoded_image_or_url"
}
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Tech Conference 2026",
    "status": "draft",
    "vendor_id": 5,
    "start_datetime": "2026-08-15T01:00:00Z",
    "end_datetime": "2026-08-15T10:00:00Z",
    "timezone": "Asia/Kuala_Lumpur",
    "created_at": "2026-05-07T12:00:00Z"
  },
  "message": "Event created successfully"
}
```

**Validation Rules:**
- `title`: required, string, max:255
- `description`: required, string
- `location_name`: required, string, max:255
- `location_address`: required, string
- `start_datetime`: required, date, after:now+1hour
- `end_datetime`: required, date, after:start_datetime
- `timezone`: required, string, in:valid_iana_timezones

---

#### POST /api/v1/orders — Purchase Tickets (Checkout)

**Auth:** Bearer token (attendee role)

**Request:**
```json
{
  "event_id": 1,
  "idempotency_key": "uuid-v4-generated-by-client",
  "items": [
    {
      "ticket_type_id": 3,
      "quantity": 2
    },
    {
      "ticket_type_id": 4,
      "quantity": 1
    }
  ]
}
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "order_id": 42,
    "order_number": "EVH-2026-000042",
    "status": "pending_payment",
    "subtotal": 450.00,
    "currency": "MYR",
    "expires_at": "2026-05-07T12:15:00Z",
    "items": [
      {
        "ticket_type_id": 3,
        "ticket_type_name": "VIP",
        "quantity": 2,
        "unit_price": 150.00,
        "line_total": 300.00
      },
      {
        "ticket_type_id": 4,
        "ticket_type_name": "General Admission",
        "quantity": 1,
        "unit_price": 150.00,
        "line_total": 150.00
      }
    ],
    "payment_initiation_url": "/api/v1/payments/initiate"
  },
  "message": "Order created. Complete payment within 15 minutes."
}
```

**Error Responses:**
- `409` — Lock contention: `"Checkout is busy, please retry in a moment"`
- `422` — Insufficient inventory: `"Only 1 ticket remaining for VIP"`
- `422` — Ticket not available: `"Early Bird tickets are no longer available"`
- `409` — Duplicate idempotency key: returns existing order

---

#### POST /api/v1/payments/initiate — Initiate Payment

**Auth:** Bearer token (attendee role)

**Request:**
```json
{
  "order_id": 42,
  "gateway": "stripe_sim"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "payment_id": "pay_abc123",
    "status": "pending",
    "gateway": "stripe_sim",
    "amount": 450.00,
    "currency": "MYR"
  },
  "message": "Payment initiated. Awaiting confirmation."
}
```

---

#### POST /api/v1/refunds — Request Refund

**Auth:** Bearer token (attendee role)

**Request:**
```json
{
  "order_id": 42,
  "reason": "Cannot attend due to schedule conflict"
}
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "refund_id": 7,
    "order_id": 42,
    "original_amount": 450.00,
    "refund_amount": 450.00,
    "refund_policy": "full",
    "status": "pending",
    "message": "Refund request submitted. Admin review required."
  },
  "message": "Refund request submitted successfully"
}
```

**Business Logic:**
- Calculates refund amount based on hours until event start
- Returns `refund_amount: 0` with `refund_policy: "none"` if < 24 hours (request still recorded)

---

#### GET /api/v1/vendors/{id}/payouts — Get Vendor Payout History

**Auth:** Bearer token (vendor role — own payouts only)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 12,
        "gross_amount": 5000.00,
        "commission_amount": 500.00,
        "net_amount": 4500.00,
        "currency": "MYR",
        "status": "completed",
        "approved_at": "2026-05-06T10:00:00Z",
        "created_at": "2026-05-06T00:00:00Z"
      }
    ],
    "summary": {
      "pending_balance": 1200.00,
      "total_paid_out": 4500.00,
      "minimum_payout_threshold": 50.00
    },
    "pagination": { ... }
  },
  "message": "Payout history retrieved"
}
```

---

#### POST /api/v1/admin/vendors/{id}/approve — Approve Vendor

**Auth:** Bearer token (admin role)

**Request:**
```json
{
  "action": "approve"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "vendor_id": 5,
    "kyc_status": "verified",
    "approved_at": "2026-05-07T12:00:00Z"
  },
  "message": "Vendor approved successfully"
}
```

---

#### POST /api/v1/webhooks/payment — Payment Webhook (Internal)

**Auth:** X-Webhook-Secret header

**Request (from Payment Service):**
```json
{
  "payment_id": "pay_abc123",
  "order_id": 42,
  "status": "completed",
  "amount": 450.00,
  "currency": "MYR",
  "gateway": "stripe_sim",
  "processed_at": "2026-05-07T12:05:00Z",
  "idempotency_key": "uuid-matching-original-request"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": null,
  "message": "Webhook processed"
}
```

---

### 5.3 Payment Service API Contracts

#### POST /api/payments — Create Payment

**Auth:** X-Service-Token header

**Request:**
```json
{
  "order_id": 42,
  "amount": 450.00,
  "currency": "MYR",
  "gateway": "stripe_sim",
  "idempotency_key": "uuid-v4",
  "callback_url": "http://main-api/api/v1/webhooks/payment",
  "metadata": {
    "attendee_id": 10,
    "event_id": 1
  }
}
```

**Response (201):**
```json
{
  "payment_id": "pay_abc123",
  "status": "pending",
  "gateway": "stripe_sim",
  "estimated_callback_delay_ms": 2000
}
```

#### POST /api/refunds — Process Refund

**Auth:** X-Service-Token header

**Request:**
```json
{
  "payment_id": "pay_abc123",
  "refund_amount": 450.00,
  "idempotency_key": "refund-uuid-v4",
  "callback_url": "http://main-api/api/v1/webhooks/refund"
}
```

---

## 6. Background Job Design

### 6.1 Job Overview

| Job | Class | Schedule | Queue |
|-----|-------|----------|-------|
| Expired Reservation Cleanup | `ExpireOrderReservationsJob` | Every 5 minutes | `default` |
| Event Reminder Notifications | `SendEventRemindersJob` | Every hour | `notifications` |
| Payout Batch Processing | `ProcessPayoutBatchJob` | Daily at 02:00 UTC | `payouts` |
| Sales Report Generation | `GenerateSalesReportsJob` | Daily at 03:00 UTC | `reports` |
| Waitlist Processing | `ProcessWaitlistJob` | Triggered on inventory release | `default` |

### 6.2 Expired Reservation Cleanup

**Trigger:** Laravel Scheduler — every 5 minutes  
**Class:** `App\Jobs\ExpireOrderReservationsJob`

**Logic:**
```
1. Query: SELECT orders WHERE status = 'pending_payment' AND expires_at < NOW()
2. For each expired order:
   a. BEGIN TRANSACTION
   b. UPDATE order SET status = 'expired'
   c. For each order_item:
      - UPDATE ticket_types SET quantity_held = quantity_held - order_item.quantity
   d. COMMIT
3. Log: "Expired {count} orders, released {total_tickets} tickets"
4. Trigger: ProcessWaitlistJob for affected ticket_types
```

**Failure Handling:**
- Each order processed in its own transaction
- If one fails, others continue
- Failed orders logged for manual review
- Job is idempotent: re-running won't double-restore inventory (status check prevents it)

### 6.3 Event Reminder Notifications

**Trigger:** Laravel Scheduler — every hour  
**Class:** `App\Jobs\SendEventRemindersJob`

**Logic:**
```
1. Query: SELECT events WHERE 
          start_datetime BETWEEN NOW()+23h AND NOW()+25h
          AND status IN ('published', 'ongoing')
2. For each event:
   a. Query: SELECT order_items JOIN orders 
             WHERE orders.event_id = event.id 
             AND orders.status = 'paid'
             AND order_items.reminder_sent = false
   b. For each order_item:
      - Publish to RabbitMQ: { type: 'email.reminder', attendee_id, event_id }
      - UPDATE order_items SET reminder_sent = true
3. Log: "Queued {count} reminders for {event_count} events"
```

**Failure Handling:**
- `reminder_sent` flag prevents duplicate reminders even if job runs twice
- Failed publishes to RabbitMQ are retried 3 times before logging error

### 6.4 Payout Batch Processing

**Trigger:** Laravel Scheduler — daily at 02:00 UTC  
**Class:** `App\Jobs\ProcessPayoutBatchJob`

**Logic:**
```
1. Check: Is there already a batch running today? (check payout_batches table)
   - If yes: skip (idempotency)
2. Create payout_batch record with status = 'processing'
3. Query: SELECT vendors WHERE kyc_status = 'verified'
4. For each vendor:
   a. Calculate pending balance:
      SUM(orders.vendor_amount) 
      WHERE orders.status = 'paid'
      AND orders.id NOT IN (SELECT order_id FROM payout_order_items)
      AND orders.paid_at < batch_start_time
   b. If balance >= minimum_threshold:
      - Create payout record (status = 'pending')
      - Create payout_order_items linking orders to payout
      - Send to payment service
5. Update payout_batch: status = 'completed' or 'partially_failed'
6. Publish notifications for completed payouts
```

**Failure Handling:**
- Each vendor payout processed in its own transaction
- If payment service is down: payout remains `pending`, batch marked `partially_failed`
- Batch can be re-run: already-processed payouts (status != 'pending') are skipped
- `payout_order_items` prevents orders from being included in multiple payouts

### 6.5 Waitlist Processing

**Trigger:** Event-driven — fired when `quantity_held` or `quantity_sold` decreases  
**Class:** `App\Jobs\ProcessWaitlistJob`

**Logic:**
```
1. Receive: ticket_type_id, quantity_released
2. Query: SELECT waitlists WHERE ticket_type_id = ? AND status = 'waiting' ORDER BY position ASC LIMIT quantity_released
3. For each waitlisted attendee:
   a. UPDATE waitlist SET status = 'notified', notified_at = NOW(), expires_at = NOW()+30min
   b. Publish to RabbitMQ: { type: 'email.waitlist_available', attendee_id, ticket_type_id }
4. If attendee doesn't purchase within 30 min:
   - Cron marks waitlist entry as 'expired'
   - Triggers next person in queue
```

---

## 7. Infrastructure & Deployment

### 7.1 Docker Compose Structure

```yaml
services:
  # Application Services
  main-api:        # Laravel 11, port 8000
  payment-service: # Laravel 11, port 8001
  notification-service: # Node.js 20, port 8002
  frontend:        # Next.js 14, port 3000

  # Infrastructure
  mysql:           # MySQL 8.0, port 3306
  redis:           # Redis 7.x, port 6379
  rabbitmq:        # RabbitMQ 3.x with management UI, ports 5672/15672
```

### 7.2 Environment Variables

**Main API (.env):**
```
APP_KEY=
DB_HOST=mysql
DB_DATABASE=eventhub
REDIS_HOST=redis
RABBITMQ_HOST=rabbitmq
PAYMENT_SERVICE_URL=http://payment-service:8001
PAYMENT_SERVICE_SECRET=
WEBHOOK_SECRET=
PLATFORM_COMMISSION_RATE=0.10
MINIMUM_PAYOUT_THRESHOLD=50.00
PLATFORM_CURRENCY=MYR
```

**Payment Service (.env):**
```
DB_HOST=mysql
DB_DATABASE=eventhub_payments
MAIN_API_URL=http://main-api:8000
SERVICE_SECRET=
STRIPE_SIM_SUCCESS_RATE=0.9
PAYPAL_SIM_SUCCESS_RATE=0.85
```

**Notification Service (.env):**
```
RABBITMQ_URL=amqp://rabbitmq:5672
MAIN_API_URL=http://main-api:8000
LOG_FILE_PATH=./logs/notifications.log
```

### 7.3 Monorepo Directory Structure

```
eventhub/
├── CLAUDE.md                    # AI agent guide
├── README.md                    # Project overview
├── docker-compose.yml           # Full stack orchestration
├── .env.example                 # Root env template
│
├── docs/                        # All documentation
│   ├── requirement-analysis.md
│   ├── system-architecture.md
│   ├── technical-decision-log.md
│   └── services/
│       ├── main-api.md
│       ├── payment-service.md
│       ├── notification-service.md
│       └── frontend.md
│
├── main-api/                    # Laravel 11 — Core API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/
│   │   │   ├── Middleware/
│   │   │   └── Requests/
│   │   ├── Services/
│   │   ├── Repositories/
│   │   ├── Models/
│   │   ├── Jobs/
│   │   └── Policies/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── tests/
│       └── Unit/
│
├── payment-service/             # Laravel 11 — Payment Microservice
│   ├── app/
│   │   ├── Http/Controllers/
│   │   ├── Services/
│   │   │   ├── StripeSimulatorService.php
│   │   │   └── PayPalSimulatorService.php
│   │   └── Models/
│   └── database/migrations/
│
├── notification-service/        # Node.js — Notification Microservice
│   ├── src/
│   │   ├── consumers/
│   │   ├── handlers/
│   │   ├── services/
│   │   └── models/
│   └── package.json
│
└── frontend/                    # Next.js 14 — Frontend
    ├── src/
    │   ├── app/
    │   │   ├── (vendor)/
    │   │   ├── (attendee)/
    │   │   └── (admin)/
    │   ├── components/
    │   ├── lib/
    │   └── hooks/
    └── package.json
```
