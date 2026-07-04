# EventHub

A multi-service event ticketing platform — Proof of Concept.

## Services

| Service | Stack | Port |
|---------|-------|------|
| Main API | Laravel 11 / PHP 8.2+ | 8000 |
| Payment Service | Laravel 11 / PHP 8.2+ | 8001 |
| Notification Service | Node.js 20 / Express | 8002 |
| Frontend | Next.js 14 / TypeScript | 3000 |

**Infrastructure:** Docker Compose · MySQL 8.0 · Redis 7 · RabbitMQ 3.12

---

## Quick Start

```bash
# Clone and start all services
git clone <repo-url> eventhub
cd eventhub
cp main-api/.env.example main-api/.env
cp payment-service/.env.example payment-service/.env
cp notification-service/.env.example notification-service/.env
cp frontend/.env.local.example frontend/.env.local

docker compose up --build
```

**Access:**
- Frontend: http://localhost:3000
- Main API: http://localhost:8000
- RabbitMQ Management: http://localhost:15672 (guest/guest)

---

## Documentation

| Document | Description |
|----------|-------------|
| [`CLAUDE.md`](./CLAUDE.md) | AI agent guide — read this first |
| [`docs/requirement-analysis.md`](./docs/requirement-analysis.md) | Full feature requirements |
| [`docs/system-architecture.md`](./docs/system-architecture.md) | Architecture diagrams & data flows |
| [`docs/technical-decision-log.md`](./docs/technical-decision-log.md) | Why each technology was chosen |
| [`docs/services/main-api.md`](./docs/services/main-api.md) | Main API spec (endpoints, services, models) |
| [`docs/services/payment-service.md`](./docs/services/payment-service.md) | Payment service spec |
| [`docs/services/notification-service.md`](./docs/services/notification-service.md) | Notification service spec |
| [`docs/services/frontend.md`](./docs/services/frontend.md) | Frontend spec (pages, components, routing) |

---

## Project Structure

```
eventhub/
├── main-api/              # Laravel 11 — core business logic
├── payment-service/       # Laravel 11 — payment gateway simulator
├── notification-service/  # Node.js — RabbitMQ consumer, email/webhook delivery
├── frontend/              # Next.js 14 — attendee, vendor, admin UI
├── docs/
│   ├── requirement-analysis.md
│   ├── system-architecture.md
│   ├── technical-decision-log.md
│   └── services/
│       ├── main-api.md
│       ├── payment-service.md
│       ├── notification-service.md
│       └── frontend.md
├── CLAUDE.md              # AI agent master guide
├── docker-compose.yml
└── README.md
```

---

## Key Features

- **Multi-role auth** — Admin, Vendor (KYC), Attendee via Laravel Sanctum
- **Ticket inventory** — Redis distributed locks + `SELECT FOR UPDATE` prevent overselling
- **Ticket types** — General Admission, VIP, Early Bird, Group Bundle (with discount)
- **Async payments** — Simulated Stripe/PayPal with configurable success rates and webhook callbacks
- **Time-based refunds** — 100% (>48h), 50% (24–48h), 0% (<24h), 100% if event cancelled
- **Vendor payouts** — Daily batch job with commission deduction and double-payout prevention
- **Notifications** — RabbitMQ with exponential backoff retry and dead-letter queues
- **QR check-in** — HMAC-signed tokens, vendor scan endpoint
- **Waitlist** — Auto-notify when tickets become available

---

## Default Test Accounts (after seeding)

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@eventhub.com | password |
| Vendor | vendor@techevents.com | password |
| Attendee | attendee@example.com | password |

```bash
# Run seeders
cd main-api && php artisan db:seed
```
