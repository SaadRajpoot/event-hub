# EventHub — Requirement Analysis Document

**Version:** 1.0  
**Date:** 2026-05-07  
**Author:** Technical Team Lead  
**Assessment:** NEXT Ventures — Technical Team Lead Take-Home

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Stakeholder Profiles](#2-stakeholder-profiles)
3. [User Stories](#3-user-stories)
4. [Functional Specifications](#4-functional-specifications)
5. [Edge Cases & Ambiguities](#5-edge-cases--ambiguities)
6. [Assumptions Made](#6-assumptions-made)
7. [Priority Matrix](#7-priority-matrix)
8. [Risk Analysis](#8-risk-analysis)

---

## 1. Project Overview

EventHub is a **multi-vendor event ticketing and payout platform**. It enables event organizers (vendors) to create and sell tickets for their events, allows attendees to discover and purchase tickets, and provides platform administrators with oversight and control tools.

The platform handles real money — every financial operation must be:
- **Auditable** — full trail of every transaction
- **Idempotent** — duplicate requests must not cause duplicate charges
- **Resilient** — partial failures must not corrupt financial state

---

## 2. Stakeholder Profiles

### 2.1 Vendor (Event Organizer)
- Creates and manages events
- Configures ticket types with pricing and inventory
- Tracks sales and revenue
- Requests and receives payouts (minus platform commission)
- Receives notifications about orders, sold-out events, and payouts
- Can register a webhook URL for real-time event notifications

### 2.2 Attendee
- Browses and discovers events
- Purchases tickets (one or multiple types)
- Manages their orders and ticket history
- Checks in at events via QR code
- Can request refunds (subject to time-based policy)
- Joins waitlists when events are sold out

### 2.3 Platform Admin
- Approves or rejects vendor registrations (KYC)
- Configures platform commission rates
- Monitors platform-wide analytics
- Mediates disputes between vendors and attendees
- Manages refund approvals

---

## 3. User Stories

### 3.1 Vendor Stories

| ID | Story | Priority |
|----|-------|----------|
| V-01 | As a vendor, I want to register on the platform so I can start creating events | Must Have |
| V-02 | As a vendor, I want to submit KYC documents so my account can be verified | Must Have |
| V-03 | As a vendor, I want to create an event with a name, description, location, start/end datetime (with timezone), and capacity | Must Have |
| V-04 | As a vendor, I want to define multiple ticket types per event (Early Bird, VIP, General Admission, Group Bundle) | Must Have |
| V-05 | As a vendor, I want to set inventory limits per ticket type so I don't oversell | Must Have |
| V-06 | As a vendor, I want to set availability windows per ticket type (e.g., Early Bird expires on a date) | Must Have |
| V-07 | As a vendor, I want to publish my event so attendees can discover it | Must Have |
| V-08 | As a vendor, I want to view real-time sales data per event (tickets sold, revenue) | Must Have |
| V-09 | As a vendor, I want to view my payout history and current pending balance | Must Have |
| V-10 | As a vendor, I want to request a payout when my balance exceeds the minimum threshold | Must Have |
| V-11 | As a vendor, I want to receive email notifications when an order is placed for my event | Must Have |
| V-12 | As a vendor, I want to register a webhook URL to receive real-time event notifications | Should Have |
| V-13 | As a vendor, I want to cancel an event and trigger refunds for all ticket holders | Should Have |
| V-14 | As a vendor, I want to edit event details before it goes live | Must Have |
| V-15 | As a vendor, I want to see a daily sales report for my events | Should Have |

### 3.2 Attendee Stories

| ID | Story | Priority |
|----|-------|----------|
| A-01 | As an attendee, I want to register an account so I can purchase tickets | Must Have |
| A-02 | As an attendee, I want to browse all published events | Must Have |
| A-03 | As an attendee, I want to view event details including ticket types, pricing, and availability | Must Have |
| A-04 | As an attendee, I want to select ticket types and quantities and proceed to checkout | Must Have |
| A-05 | As an attendee, I want my selected tickets to be held for 15 minutes while I complete payment | Must Have |
| A-06 | As an attendee, I want to pay for my tickets using a supported payment method | Must Have |
| A-07 | As an attendee, I want to receive an order confirmation email after successful payment | Must Have |
| A-08 | As an attendee, I want to view my order history | Must Have |
| A-09 | As an attendee, I want to receive a QR code for each ticket I purchase | Must Have |
| A-10 | As an attendee, I want to request a refund for my order | Must Have |
| A-11 | As an attendee, I want to receive a reminder email 24 hours before an event I have tickets for | Should Have |
| A-12 | As an attendee, I want to join a waitlist when an event is sold out | Should Have |
| A-13 | As an attendee, I want to transfer my ticket to another person | Nice to Have |

### 3.3 Admin Stories

| ID | Story | Priority |
|----|-------|----------|
| AD-01 | As an admin, I want to view all pending vendor registrations and approve or reject them | Must Have |
| AD-02 | As an admin, I want to configure the platform commission rate (percentage) | Must Have |
| AD-03 | As an admin, I want to view platform-wide analytics (total sales, active events, vendor count, revenue) | Must Have |
| AD-04 | As an admin, I want to view and manage dispute/refund requests | Must Have |
| AD-05 | As an admin, I want to approve or reject payout requests | Must Have |
| AD-06 | As an admin, I want to view all vendors and their KYC status | Should Have |
| AD-07 | As an admin, I want to view all orders across the platform | Should Have |

---

## 4. Functional Specifications

### 4.1 Event Management

**Event Lifecycle States:**
```
draft → published → ongoing → completed → cancelled
```

| State | Description | Allowed Transitions |
|-------|-------------|---------------------|
| `draft` | Created but not visible to attendees | → `published`, → `cancelled` |
| `published` | Visible to attendees, tickets purchasable | → `ongoing`, → `cancelled` |
| `ongoing` | Event is currently happening | → `completed`, → `cancelled` |
| `completed` | Event has ended | (terminal) |
| `cancelled` | Event was cancelled | (terminal) |

**Event Fields:**
- `title` (required, max 255 chars)
- `description` (required, rich text)
- `location` (required — venue name + address)
- `start_datetime` (required, with timezone)
- `end_datetime` (required, must be after start_datetime)
- `timezone` (required, IANA timezone string e.g. `Asia/Kuala_Lumpur`)
- `banner_image` (optional)
- `status` (managed by system + vendor actions)
- `vendor_id` (FK to vendor)

**Business Rules:**
- A vendor can only edit events in `draft` or `published` state
- An event cannot be published if it has no ticket types with inventory > 0
- Cancelling a published/ongoing event triggers refunds for all paid orders
- `start_datetime` must be at least 1 hour in the future when publishing

### 4.2 Ticket Types

**Supported Types:**

| Type | Description | Special Rules |
|------|-------------|---------------|
| `general_admission` | Standard ticket | None |
| `vip` | Premium ticket | Higher price tier |
| `early_bird` | Discounted, time-limited | Has `sale_end_datetime`; reverts to GA price after expiry |
| `group_bundle` | Bulk purchase discount | Minimum quantity required (e.g., buy 4 get 10% off) |

**Ticket Type Fields:**
- `event_id` (FK)
- `name` (e.g., "Early Bird General Admission")
- `type` (enum: `general_admission`, `vip`, `early_bird`, `group_bundle`)
- `price` (decimal, in platform currency)
- `quantity` (total inventory)
- `quantity_sold` (tracked separately for audit)
- `quantity_held` (currently in active checkout holds)
- `available_from` (datetime, optional)
- `available_until` (datetime, optional — for early bird)
- `min_purchase` (integer, default 1)
- `max_purchase` (integer per order, default 10)
- `group_min_quantity` (for group bundles, e.g., 4)
- `group_discount_percent` (for group bundles, e.g., 10)

**Business Rules:**
- `available_quantity = quantity - quantity_sold - quantity_held`
- Early bird tickets automatically become unavailable after `available_until`
- Group bundle discount applies only when `quantity >= group_min_quantity`
- A ticket type cannot be deleted if orders exist; it can be deactivated

### 4.3 Order Processing & Checkout Flow

**Order States:**
```
pending_payment → paid → cancelled → refunded → partially_refunded
```

**Checkout Flow:**
1. Attendee selects ticket types and quantities
2. System acquires **distributed lock** on each ticket type
3. System checks available inventory
4. If available: creates `Order` with status `pending_payment`, creates `OrderItems`, decrements `quantity_held` on each ticket type
5. System releases lock
6. System sets a **15-minute expiry timer** on the order
7. Attendee is redirected to payment
8. Payment service processes payment and sends webhook back
9. On payment success: order status → `paid`, `quantity_sold` incremented, `quantity_held` decremented
10. On payment failure: order status → `cancelled`, `quantity_held` decremented (inventory restored)
11. On expiry (no payment in 15 min): cron job marks order `expired`, restores inventory

**Distributed Locking Strategy:**
- Use Redis `SET NX PX` (set if not exists, with TTL) per ticket type
- Lock key: `lock:ticket_type:{id}`
- Lock TTL: 5 seconds (enough to check inventory and create order)
- If lock cannot be acquired: return 409 Conflict, ask client to retry
- Lock is released immediately after inventory check + order creation

**Concurrent Purchase Protection:**
- `quantity_held` is updated atomically within the lock
- Database-level: use transactions + row-level locking (`SELECT FOR UPDATE`) as secondary protection
- Oversell is impossible: `available_quantity` check happens inside the lock

### 4.4 Payout Management

**Payout Calculation:**
```
vendor_earnings = order_subtotal - platform_commission
platform_commission = order_subtotal × commission_rate
```

**Payout States:**
```
pending → processing → completed → failed
```

**Business Rules:**
- Minimum payout threshold: configurable (default $50 or equivalent)
- Vendor must have verified KYC status to receive payouts
- Payout requests are batched daily by cron job
- Admin must approve payout requests before processing
- If payout batch fails midway, already-processed vendors are marked `completed`; failed ones remain `pending` for retry
- Payout records are immutable once `completed` — corrections via new adjustment records

**Commission Configuration:**
- Platform-wide default commission rate (set by admin)
- Per-vendor override possible (nice-to-have)
- Commission rate is snapshotted at order creation time (not recalculated at payout)

### 4.5 Refund Policy

| Time Before Event | Refund Amount |
|-------------------|---------------|
| > 48 hours | 100% refund |
| 24–48 hours | 50% refund |
| < 24 hours | No refund |
| Event cancelled by vendor | 100% refund (always) |

**Refund Flow:**
1. Attendee submits refund request
2. System calculates refund amount based on policy
3. If refund amount > 0: creates dispute/refund record, admin reviews
4. Admin approves → payment service processes refund
5. Payment service confirms → order status updated, vendor balance adjusted

### 4.6 Authentication & Authorization

**Auth Method:** API token-based (Laravel Sanctum)

**Roles:**
| Role | Permissions |
|------|-------------|
| `admin` | Full platform access |
| `vendor` | Own events, own orders, own payouts only |
| `attendee` | Own orders, public event browsing |

**Authorization Rules:**
- A vendor cannot access another vendor's events, orders, or payouts
- An attendee cannot access admin or vendor routes
- All API routes require authentication except: event listing, event detail (public)
- Payment service endpoints are not publicly accessible (shared secret auth)
- Notification service endpoints are internal only

### 4.7 QR Code Check-In

- Each `OrderItem` (individual ticket) gets a unique QR code generated on order confirmation
- QR code encodes: `ticket_id`, `order_id`, `attendee_id`, `event_id`, signed with HMAC
- Check-in endpoint validates signature, checks ticket status, marks as `checked_in`
- A ticket can only be checked in once

---

## 5. Edge Cases & Ambiguities

### 5.1 Concurrency & Race Conditions

| Scenario | Risk | Mitigation |
|----------|------|------------|
| Two attendees checkout the last ticket simultaneously | Oversell | Distributed lock + `SELECT FOR UPDATE` |
| Payment webhook arrives after order expiry | Inventory double-restore | Idempotency check on order state before processing webhook |
| Payout batch runs while new orders are being placed | Commission calculation on in-flight orders | Only process orders with status `paid` and `created_at` before batch start time |
| Cron job runs twice (duplicate execution) | Double payout | Idempotency key on payout batch run; check `processed_at` before processing |

### 5.2 Timezone Handling

| Scenario | Assumption Made |
|----------|-----------------|
| Event stored in what timezone? | Store all datetimes in UTC in the database; store `timezone` field for display |
| Early bird expiry — which timezone? | Evaluated in UTC |
| "24 hours before event" for reminders | Calculated from event `start_datetime` in UTC |
| Refund policy time calculation | Calculated from event `start_datetime` in UTC |

### 5.3 Currency

| Ambiguity | Assumption |
|-----------|------------|
| Multi-currency support? | Single currency (MYR or USD) for POC; currency stored as string field for future |
| Decimal precision | Store as `DECIMAL(10,2)` — 2 decimal places |
| Currency conversion | Not in scope for POC |

### 5.4 Group Bundle Edge Cases

| Scenario | Assumption |
|----------|------------|
| Attendee buys 3 of a "buy 4 get discount" bundle | No discount applied; full price charged |
| Attendee buys 8 (2× bundle) | Discount applies to all 8 |
| Partial refund on group bundle | Refund calculated on per-ticket price after discount |

### 5.5 Vendor Webhook Delivery

| Scenario | Assumption |
|----------|------------|
| Vendor webhook URL is down | Retry with exponential backoff, max 5 retries, then dead-letter |
| Vendor webhook URL returns non-2xx | Treated as failure, retry |
| Vendor has no webhook URL registered | Skip webhook delivery silently |

### 5.6 Event Cancellation

| Scenario | Assumption |
|----------|------------|
| Vendor cancels event with pending orders | Pending orders are cancelled, no charge |
| Vendor cancels event with paid orders | Full refund triggered for all paid orders regardless of time |
| Admin cancels event | Same as vendor cancellation |

### 5.7 Waitlist

| Scenario | Assumption |
|----------|------------|
| Multiple tickets become available at once | Notify waitlisted attendees in FIFO order, one at a time |
| Waitlisted attendee doesn't purchase within time limit | Slot passes to next person on waitlist |
| Waitlist notification time limit | 30 minutes to complete purchase before next person is notified |

---

## 6. Assumptions Made

1. **Single currency** — Platform operates in one currency (configurable). No multi-currency for POC.
2. **Commission rate is fixed at order time** — The commission rate applied to an order is snapshotted when the order is created, not when the payout is processed.
3. **KYC is manual** — Admin manually reviews and approves/rejects vendor KYC. No automated document verification.
4. **Payment simulation only** — No real payment gateway integration. Two simulated gateways with configurable success/failure rates.
5. **Email is simulated** — Emails are logged to file/console, not sent via SMTP.
6. **QR code check-in is API-only** — No physical scanner integration; check-in is done via API call.
7. **Ticket transfers are nice-to-have** — Not in core scope for POC.
8. **Vendor bank details are stored but not validated** — Bank account details are stored for payout reference but not validated against real banking systems.
9. **Refund approval is required** — All refunds go through admin approval, even automatic ones (for audit trail).
10. **Group bundle discount is all-or-nothing per order** — Discount applies if the order meets the minimum quantity; no partial discount.
11. **One active checkout per attendee per event** — An attendee cannot have two simultaneous pending orders for the same event.
12. **Platform admin is a single role** — No sub-roles within admin for POC.
13. **All datetimes stored in UTC** — Display timezone is handled by the frontend using the event's `timezone` field.

---

## 7. Priority Matrix

### Must Have (Core POC — Day 2–3)

| Feature | Reason |
|---------|--------|
| Vendor registration + KYC flow | Gate for all vendor functionality |
| Event CRUD with lifecycle management | Core platform feature |
| Ticket type management | Required for any sales |
| Order processing with distributed locking | Core financial safety requirement |
| Payment microservice with webhook simulation | Required for order completion |
| Payout calculation + batch processing | Core financial feature |
| Attendee registration + checkout flow | Core attendee journey |
| Role-based authentication (admin/vendor/attendee) | Security requirement |
| Admin vendor approval | Required for vendor onboarding |
| Order confirmation email (simulated) | Core notification |
| Unit tests: order processing, payout, inventory | Assessment requirement |
| Docker Compose setup | Assessment requirement |
| CLAUDE.md + agent skills | Assessment requirement |

### Should Have (Day 4)

| Feature | Reason |
|---------|--------|
| Refund request + approval flow | Important financial feature |
| QR code generation + check-in | Attendee experience |
| Event reminder notifications | Attendee experience |
| Vendor dashboard (frontend) | Assessment requirement |
| Attendee purchase flow (frontend) | Assessment requirement |
| Admin panel (frontend) | Assessment requirement |
| Waitlist functionality | Inventory management |
| Vendor webhook delivery | Vendor integration |
| Sales report generation (cron) | Analytics |

### Nice to Have (Day 5 if time allows)

| Feature | Reason |
|---------|--------|
| Ticket transfers | Attendee convenience |
| Per-vendor commission override | Business flexibility |
| Multi-currency support | Future scalability |
| Advanced analytics dashboard | Business intelligence |
| Dispute resolution workflow (detailed) | Admin tooling |
| Postman collection | API documentation |

---

## 8. Risk Analysis

### 8.1 Technical Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Distributed locking complexity | High | Critical | Use Redis with well-tested lock implementation; add database-level `SELECT FOR UPDATE` as fallback |
| Idempotency failures in payment webhook | Medium | Critical | Store idempotency keys in DB; check before processing any financial state change |
| Payout batch partial failure | Medium | High | Process payouts in individual transactions; mark each as completed before moving to next |
| Race condition in inventory management | High | High | Atomic Redis operations + DB transactions; comprehensive unit tests |
| Inter-service communication failure | Medium | High | Retry logic with exponential backoff; circuit breaker pattern |
| Queue backlog causing notification delays | Low | Medium | Monitor queue depth; dead-letter queue for failed messages |

### 8.2 Product/Business Risks

| Risk | Likelihood | Impact | What to Flag to PM |
|------|-----------|--------|-------------------|
| Refund policy edge cases (event cancellation by vendor) | High | High | Clarify: does vendor pay back commission on cancelled events? |
| Timezone confusion for international events | Medium | Medium | Confirm: should event times display in attendee's local timezone or event's timezone? |
| Minimum payout threshold currency | Low | Medium | Confirm: is minimum threshold in platform currency or vendor's local currency? |
| Commission rate changes affecting existing orders | Low | High | Confirm: should commission rate changes affect pending (unpaid) orders? |
| Vendor dispute resolution workflow | Medium | Medium | Clarify: can vendor contest a refund? What's the escalation path? |
| Group bundle partial refund calculation | Medium | Medium | Clarify: if attendee bought 4 tickets at bundle price and refunds 2, what's the refund amount? |

### 8.3 Timeline Risks

| Risk | Mitigation |
|------|------------|
| Frontend takes longer than expected | Use shadcn/ui component library; focus on functional over polished |
| Microservice communication setup complexity | Define API contracts first; use simple HTTP with shared secret for POC |
| Test coverage taking too long | Focus tests on financial logic only (as specified); skip UI tests |
| Documentation quality vs. code quantity trade-off | Documentation is 40% of the rubric; do not sacrifice it for features |
