# EventHub — Technical Decision Log

**Version:** 1.0  
**Date:** 2026-05-07  
**Author:** Technical Team Lead  
**Assessment:** NEXT Ventures — Technical Team Lead Take-Home

---

## Table of Contents

1. [Technology Stack Decisions](#1-technology-stack-decisions)
2. [Architecture Decisions](#2-architecture-decisions)
3. [Database Decisions](#3-database-decisions)
4. [Security Decisions](#4-security-decisions)
5. [Trade-offs Due to Time Constraint](#5-trade-offs-due-to-time-constraint)
6. [With More Time](#6-with-more-time)
7. [Team Delegation Plan](#7-team-delegation-plan)

---

## 1. Technology Stack Decisions

### 1.1 Main API: Laravel 11 / PHP 8.2

**Decision:** Use Laravel 11 as specified.

**Why Laravel 11:**
- Specified in the assessment requirements
- Excellent ecosystem for rapid API development (Sanctum, Eloquent, Queues, Scheduler)
- Built-in support for all required patterns: service layer, repository pattern, policies
- Laravel Sanctum provides token-based auth out of the box
- Laravel Scheduler handles all cron jobs without external tooling
- Strong testing support (PHPUnit + Laravel testing helpers)

**Alternatives Considered:**
- Symfony — More verbose, slower to scaffold; better for enterprise but overkill for POC
- Lumen — Lighter but deprecated; Laravel 11 is already lean enough

**Pattern Choice: Controller → Service → Repository → Model**
- Controllers handle HTTP concerns only (request validation, response formatting)
- Services contain all business logic (order processing, payout calculation)
- Repositories abstract data access (easy to swap DB or add caching)
- Models are pure Eloquent models with relationships and scopes only

---

### 1.2 Payment Service: Laravel 11

**Decision:** Use Laravel for the payment service (same as main API).

**Why Laravel over Node.js:**
- Code consistency across backend services — one language, one framework
- Laravel's HTTP client (Guzzle wrapper) is excellent for webhook simulation
- Easier to share patterns and conventions with the main API
- Faster to build for a solo developer in a 5-day window

**Alternatives Considered:**
- Node.js/Express — Would be fine but introduces a second language for the backend
- Node.js/Fastify — Same concern; adds cognitive overhead

**Trade-off:** Node.js would be more appropriate for a high-throughput payment service in production (event loop handles concurrent webhook callbacks better). For POC simulation, Laravel is sufficient.

---

### 1.3 Notification Service: Node.js 20 / Express

**Decision:** Use Node.js for the notification service.

**Why Node.js:**
- Specified as recommended in the assessment
- Excellent for I/O-bound work (queue consumption, HTTP webhook delivery)
- `amqplib` is the de-facto RabbitMQ client for Node.js
- Non-blocking I/O is ideal for retry logic with exponential backoff
- Demonstrates polyglot microservices capability

**Alternatives Considered:**
- Python/Celery — Good queue support but heavier setup; less natural for webhook delivery
- Laravel Queue Worker — Would work but defeats the purpose of a separate microservice

---

### 1.4 Frontend: Next.js 14

**Decision:** Use Next.js 14 with App Router.

**Why Next.js 14:**
- Specified as recommended in the assessment
- App Router provides clean route grouping for vendor/attendee/admin sections
- Server Components reduce client-side bundle size
- Built-in API route handling if needed
- Strong TypeScript support

**Component Library: shadcn/ui**
- Why: Unstyled by default, highly composable, built on Radix UI primitives
- Faster to build functional UIs than writing from scratch
- Better than Ant Design for Next.js App Router (no SSR hydration issues)
- Better than Material UI for customization without fighting the framework

**State Management: React Query (TanStack Query)**
- Why: Server state management is the primary need (API data fetching, caching, mutations)
- Handles loading/error states, caching, and refetching automatically
- No need for Redux/Zustand for this scope — server state is the dominant concern

**Alternatives Considered:**
- Remix — Good but less familiar; Next.js has broader ecosystem
- SvelteKit — Excellent but not specified; introduces unfamiliar tech for reviewers
- Ant Design — Works but has SSR hydration issues with Next.js App Router

---

### 1.5 Database: MySQL 8.0

**Decision:** Use MySQL 8.0 as the primary database.

**Why MySQL:**
- Mature, well-understood relational database
- Excellent Laravel/Eloquent support
- Row-level locking (`SELECT FOR UPDATE`) for inventory management
- JSON column support for audit snapshots
- Window functions for analytics queries (MySQL 8.0+)

**Alternatives Considered:**
- PostgreSQL — Arguably better for complex queries and JSON; either would work. MySQL chosen for wider familiarity and simpler Docker setup.
- SQLite — Too limited for concurrent access patterns required by this system

**Two Databases:**
- `eventhub` — Main application database
- `eventhub_payments` — Payment service database (service isolation)

---

### 1.6 Cache & Distributed Locking: Redis 7.x

**Decision:** Use Redis for distributed locking, caching, and session storage.

**Why Redis:**
- Industry standard for distributed locking (`SET NX PX`)
- Laravel has first-class Redis support
- Single tool serves multiple purposes: locks, cache, queue (if needed)
- Atomic operations prevent race conditions in inventory management

**Locking Strategy: Redis `SET NX PX` (Redlock-lite)**
- For POC: single Redis instance with `SET NX PX` is sufficient
- Lock key: `lock:ticket_type:{id}` with 5-second TTL
- If lock acquisition fails: return 409, client retries
- Production consideration: Use Redlock algorithm with 3+ Redis nodes for true distributed safety

**Alternatives Considered:**
- Database-level locking only — `SELECT FOR UPDATE` is sufficient but creates DB bottleneck under high concurrency
- Memcached — No atomic operations; cannot implement distributed locks

---

### 1.7 Message Queue: RabbitMQ

**Decision:** Use RabbitMQ for the notification queue.

**Why RabbitMQ:**
- Specified as an option in the assessment (Redis or RabbitMQ)
- Better suited than Redis Pub/Sub for reliable message delivery (persistence, acknowledgements)
- Dead-letter exchange (DLX) is a native feature — perfect for failed notification handling
- AMQP protocol provides message routing flexibility (exchanges, routing keys, queues)
- Management UI (port 15672) makes it easy to inspect queue state during development

**Queue Design:**
- Exchange: `eventhub.notifications` (topic exchange)
- Queues: `notifications.email`, `notifications.webhook`
- Dead-letter exchange: `eventhub.notifications.dlx`
- Routing keys: `email.*`, `webhook.*`

**Alternatives Considered:**
- Redis Pub/Sub — No message persistence; if consumer is down, messages are lost
- Redis Streams — Better than Pub/Sub but more complex to configure
- AWS SQS — Not appropriate for local POC
- Apache Kafka — Overkill for this scale; complex setup

---

## 2. Architecture Decisions

### 2.1 Microservices vs. Monolith

**Decision:** Microservices as specified, with 3 backend services.

**Why this decomposition:**
- **Main API** — Core domain logic; must be the single source of truth for business state
- **Payment Service** — Financial operations isolated for security and independent scaling
- **Notification Service** — Async, I/O-bound work that should not block the main request cycle

**Service Boundary Justification:**
- Payment service: Isolates financial logic, allows different security posture (no public access), can be replaced with real gateway integration without touching main API
- Notification service: Failures in notification delivery must not affect order processing; async by nature

**What I would NOT split further:**
- Vendor management and event management stay in the main API — they share the same database and have tight coupling (vendor owns events)
- Admin functionality stays in the main API — it's a thin layer over existing data

### 2.2 Synchronous vs. Asynchronous Communication

**Decision:** Synchronous HTTP for payment initiation; asynchronous (webhook + queue) for everything else.

**Reasoning:**
- Payment initiation is synchronous because the attendee is waiting for a response
- Payment confirmation is asynchronous (webhook) because processing takes time and we don't want to hold the HTTP connection
- Notifications are always asynchronous — they are fire-and-forget from the main API's perspective
- Payout processing is asynchronous — batch job, not user-facing

### 2.3 Idempotency Strategy

**Decision:** Client-provided idempotency keys for orders and payments; server-generated keys for internal operations.

**Implementation:**
- Orders: Client sends `idempotency_key` (UUID v4) with checkout request
- Payments: Main API generates idempotency key when calling payment service
- Webhooks: Payment service includes original idempotency key in webhook; main API checks if already processed
- Payout batches: Keyed by date (`payout_batch_{YYYY-MM-DD}`) — only one batch per day

**Why client-provided for orders:**
- Allows frontend to safely retry failed requests without creating duplicate orders
- Standard practice (Stripe uses this pattern)

### 2.4 API Versioning

**Decision:** URL-based versioning (`/api/v1/`).

**Why URL versioning over header versioning:**
- Simpler to implement and test
- Easier to route in nginx/load balancer
- More visible in logs and debugging
- Standard practice for REST APIs at this scale

---

## 3. Database Decisions

### 3.1 Financial Amount Storage

**Decision:** Store all monetary values as `DECIMAL(10,2)`.

**Why DECIMAL over FLOAT:**
- FLOAT has floating-point precision errors (0.1 + 0.2 ≠ 0.3)
- DECIMAL is exact — critical for financial calculations
- `DECIMAL(10,2)` supports up to 99,999,999.99 — sufficient for event ticketing

### 3.2 Snapshot Pattern for Financial Records

**Decision:** Snapshot price, commission rate, and bank details at transaction time.

**Why:**
- Prices change over time; historical orders must reflect what was actually charged
- Commission rates change; historical payouts must reflect the rate that was active
- Bank details change; payout records must show where money was actually sent
- This is standard practice in financial systems (event sourcing lite)

### 3.3 Separate `quantity_sold` and `quantity_held`

**Decision:** Track inventory with three fields: `quantity`, `quantity_sold`, `quantity_held`.

**Why not just `quantity_remaining`:**
- `quantity_held` represents tickets in active checkout (not yet paid)
- `quantity_sold` represents confirmed paid tickets
- Separating them allows: accurate available inventory calculation, easy expiry cleanup, audit of held vs. sold
- Formula: `available = quantity - quantity_sold - quantity_held`

### 3.4 `payout_order_items` Junction Table

**Decision:** Create a junction table linking payouts to the specific orders they cover.

**Why:**
- Prevents double-payout: if an order is in `payout_order_items`, it won't be included in the next batch
- Provides complete audit trail: "which orders were paid out in which batch"
- Allows partial batch recovery: if batch fails midway, completed payouts are already linked

---

## 4. Security Decisions

### 4.1 Authentication: Laravel Sanctum

**Decision:** Use Laravel Sanctum for API token authentication.

**Why Sanctum over JWT:**
- Tokens are stored in the database — can be revoked instantly
- JWT tokens cannot be revoked without a blacklist (adds complexity)
- Sanctum is Laravel-native, well-maintained, and battle-tested
- Token hashing in DB means a compromised DB doesn't expose tokens

**Why not OAuth2:**
- Overkill for a POC with a single frontend client
- OAuth2 is appropriate when third-party apps need to access the API

### 4.2 Inter-Service Authentication: Shared Secret

**Decision:** Use shared secret (HMAC-signed headers) for service-to-service communication.

**Why:**
- Simple to implement for POC
- Secrets stored in environment variables (not hardcoded)
- HMAC signature on webhooks prevents replay attacks (timestamp included)

**Production consideration:** Replace with mTLS or a service mesh (Istio) for true zero-trust service communication.

### 4.3 Input Validation

**Decision:** Validate all inputs using Laravel Form Requests.

**Implementation:**
- Every endpoint has a dedicated `FormRequest` class
- Validation rules defined in `rules()` method
- Custom error messages in `messages()` method
- Authorization check in `authorize()` method
- No raw `$request->all()` passed to models (prevents mass assignment)

### 4.4 QR Code Security

**Decision:** Sign QR code tokens with HMAC-SHA256.

**Why:**
- Prevents ticket forgery (cannot generate valid QR without the secret)
- Encodes: `ticket_id`, `order_id`, `attendee_id`, `event_id`, `timestamp`
- Signature: `HMAC-SHA256(payload, APP_KEY)`
- Check-in endpoint verifies signature before marking as checked in

---

## 5. Trade-offs Due to Time Constraint

| Decision | What Was Done | What Would Be Better |
|----------|--------------|---------------------|
| Single Redis instance for locking | Simple `SET NX PX` | Redlock with 3+ Redis nodes for true distributed safety |
| No circuit breaker | HTTP calls to payment service can fail and propagate | Implement circuit breaker (e.g., `php-circuit-breaker`) |
| No API rate limiting | All endpoints unthrottled | Add `throttle` middleware per role (e.g., 60/min for attendees, 1000/min for vendors) |
| Simulated payments only | Random success/failure | Real Stripe/PayPal integration |
| Email logged to file | No actual email delivery | SMTP integration (Mailgun, SES) |
| No event sourcing | State stored as current value | Full event log for complete audit trail |
| No HTTPS in Docker | HTTP between services | TLS termination at load balancer |
| No pagination on all endpoints | Some list endpoints return all records | Cursor-based pagination for large datasets |
| No search/filter on events | Basic listing only | Elasticsearch for full-text search |
| No image upload service | Banner image as URL string | S3/MinIO for file storage |

---

## 6. With More Time

### 6.1 Architecture Improvements

1. **Event Sourcing for Financial Operations**
   - Instead of mutating order/payment state, append immutable events
   - `OrderCreated`, `PaymentInitiated`, `PaymentCompleted`, `RefundRequested`, etc.
   - Complete audit trail, easy replay, time-travel debugging

2. **API Gateway**
   - Single entry point for all services
   - Centralized auth, rate limiting, logging
   - Kong or AWS API Gateway

3. **Service Mesh**
   - mTLS between services (Istio or Linkerd)
   - Distributed tracing (Jaeger/Zipkin)
   - Circuit breakers at the infrastructure level

4. **CQRS for Analytics**
   - Separate read models for dashboard queries
   - Avoid complex JOINs on the write database
   - Materialized views or dedicated read replicas

### 6.2 Feature Improvements

1. **Real Payment Gateway Integration**
   - Stripe for card payments
   - FPX for Malaysian bank transfers
   - Proper webhook signature verification

2. **Advanced Refund Workflow**
   - Vendor can contest refund requests
   - Escalation path to admin arbitration
   - Partial refund for individual tickets within an order

3. **Ticket Transfer**
   - Attendee can transfer ticket to another registered user
   - Transfer creates audit trail
   - Original QR code invalidated, new one issued

4. **Multi-Currency Support**
   - Store amounts in minor units (cents) as integers
   - Currency conversion at display time
   - Payout in vendor's preferred currency

5. **Advanced Analytics**
   - Revenue trends over time
   - Ticket type performance comparison
   - Attendee demographics
   - Conversion funnel (viewed → checkout → paid)

### 6.3 Operational Improvements

1. **Observability Stack**
   - Structured logging (JSON) with correlation IDs
   - Metrics (Prometheus + Grafana)
   - Distributed tracing (OpenTelemetry)
   - Alerting on financial operation failures

2. **Database Improvements**
   - Read replicas for analytics queries
   - Connection pooling (PgBouncer/ProxySQL)
   - Automated backups with point-in-time recovery

3. **Security Hardening**
   - PCI DSS compliance review
   - Penetration testing
   - OWASP Top 10 audit
   - Secrets management (HashiCorp Vault)

---

## 7. Team Delegation Plan

### Scenario: 3–4 Developers, 2 Weeks

**Assumption:** 1 Tech Lead + 3 Developers

#### Stream A: Backend Core (Developer 1)
**Week 1:**
- Database migrations and seeders
- Auth system (registration, login, roles)
- Event CRUD + lifecycle management
- Ticket type management

**Week 2:**
- Order processing with distributed locking
- Payout calculation and batch processing
- Admin endpoints
- Unit tests for financial logic

#### Stream B: Payment & Notifications (Developer 2)
**Week 1:**
- Payment service scaffold
- Gateway simulators (Stripe, PayPal)
- Webhook handling (payment → main API)
- Idempotency implementation

**Week 2:**
- Notification service scaffold
- RabbitMQ consumer setup
- Email notification handlers
- Vendor webhook delivery + retry logic

#### Stream C: Frontend (Developer 3)
**Week 1:**
- Project setup (Next.js, shadcn/ui, React Query)
- Auth flow (login, registration, role routing)
- Event listing and detail pages
- Attendee checkout flow

**Week 2:**
- Vendor dashboard (events, sales, payouts)
- Admin panel (vendor approvals, disputes)
- Order history and QR code display
- Error handling and loading states

#### Tech Lead (Ongoing)
- Architecture decisions and code review
- API contract definition (unblocks all streams)
- Docker Compose setup (Day 1 — unblocks everyone)
- Integration testing between services
- Documentation and CLAUDE.md
- Final video walkthrough

#### Parallelization Dependencies

```
Day 1 (Tech Lead):
  └─► Docker Compose + DB migrations + API contracts
      (unblocks all three developer streams)

Week 1 (Parallel):
  Stream A: Core API models + auth
  Stream B: Payment service scaffold
  Stream C: Frontend scaffold + auth UI

Week 1 Integration Point (End of Week 1):
  └─► Stream A + Stream B: Order creation → payment initiation flow
  └─► Stream A + Stream C: Auth + event listing working end-to-end

Week 2 (Parallel):
  Stream A: Order processing + payouts
  Stream B: Notifications + webhooks
  Stream C: Vendor dashboard + admin panel

Week 2 Integration Point (End of Week 2):
  └─► Full end-to-end: create event → purchase ticket → payment → notification
  └─► Payout flow: batch processing → payment service → vendor notification
```

#### Critical Path
The critical path is: **DB Schema → Auth → Order Processing → Payment Webhook → Notification**

This chain must be unblocked first. Everything else (frontend, admin panel, analytics) can be built in parallel once the core order flow works.
