# EventHub — Notification Service Specification

**Service:** Notification Microservice  
**Stack:** Node.js 20 / Express  
**Port:** 8002  
**Queue:** RabbitMQ (AMQP)  
**Version:** 1.0  
**Date:** 2026-05-07

---

## Build Progress Checklist

> Use this checklist to track implementation progress. Update as each item is completed.

### Phase 1: Project Scaffold
- [ ] Node.js project initialized (`npm init`)
- [ ] Required packages installed (amqplib, express, etc.)
- [ ] `.env` configured for Docker environment
- [ ] Docker container running and accessible at port 8002
- [ ] Health check endpoint: `GET /health`
- [ ] RabbitMQ connection established and tested

### Phase 2: Queue Infrastructure
- [ ] RabbitMQ exchange declared: `eventhub.notifications` (topic)
- [ ] Queue declared: `notifications.email`
- [ ] Queue declared: `notifications.webhook`
- [ ] Dead-letter exchange declared: `eventhub.notifications.dlx`
- [ ] Dead-letter queues declared: `notifications.email.dead`, `notifications.webhook.dead`
- [ ] Consumer registered for `notifications.email`
- [ ] Consumer registered for `notifications.webhook`

### Phase 3: Email Notification Handlers
- [ ] `OrderConfirmationHandler` — logs order confirmation to file
- [ ] `EventReminderHandler` — logs event reminder to file
- [ ] `PayoutCompletedHandler` — logs payout notification to file
- [ ] `VendorApprovedHandler` — logs vendor approval to file
- [ ] `VendorRejectedHandler` — logs vendor rejection to file
- [ ] `WaitlistAvailableHandler` — logs waitlist notification to file

### Phase 4: Vendor Webhook Delivery
- [ ] `VendorWebhookHandler` — delivers payload to vendor's registered URL
- [ ] HMAC signature generation on outbound webhooks
- [ ] HTTP POST to vendor URL with timeout (5 seconds)
- [ ] Non-2xx response treated as failure

### Phase 5: Retry Logic
- [ ] Exponential backoff implemented (1s, 4s, 16s, 64s, 256s)
- [ ] Max 5 retries per message
- [ ] Dead-letter routing after 5 failures
- [ ] Delivery status tracking (sent/failed/retrying/dead_lettered)
- [ ] Retry count tracked per message

### Phase 6: Delivery Tracking
- [ ] Notification delivery log written to file/SQLite
- [ ] Status updates: pending → sent / failed / dead_lettered
- [ ] `GET /api/notifications/:id/status` endpoint

### Phase 7: Testing
- [ ] Unit tests: handler logic (correct log format)
- [ ] Unit tests: retry logic (backoff timing)
- [ ] Unit tests: HMAC signature generation
- [ ] Integration test: consume message → log output

---

## Table of Contents

1. [Project Structure](#1-project-structure)
2. [Required Packages](#2-required-packages)
3. [RabbitMQ Queue Design](#3-rabbitmq-queue-design)
4. [Message Schemas](#4-message-schemas)
5. [Notification Handlers](#5-notification-handlers)
6. [Retry Logic](#6-retry-logic)
7. [Vendor Webhook Delivery](#7-vendor-webhook-delivery)
8. [Delivery Tracking](#8-delivery-tracking)
9. [Coding Conventions](#9-coding-conventions)

---

## 1. Project Structure

```
notification-service/
├── src/
│   ├── config/
│   │   └── index.js              # All config from env vars
│   ├── consumers/
│   │   ├── emailConsumer.js      # Consumes notifications.email queue
│   │   └── webhookConsumer.js    # Consumes notifications.webhook queue
│   ├── handlers/
│   │   ├── email/
│   │   │   ├── orderConfirmationHandler.js
│   │   │   ├── eventReminderHandler.js
│   │   │   ├── payoutCompletedHandler.js
│   │   │   ├── vendorApprovedHandler.js
│   │   │   ├── vendorRejectedHandler.js
│   │   │   └── waitlistAvailableHandler.js
│   │   └── webhook/
│   │       └── vendorWebhookHandler.js
│   ├── services/
│   │   ├── rabbitmqService.js    # Connection + channel management
│   │   ├── retryService.js       # Exponential backoff retry logic
│   │   ├── deliveryTracker.js    # Track delivery status
│   │   └── hmacService.js        # HMAC signature for outbound webhooks
│   ├── routes/
│   │   └── health.js             # GET /health
│   ├── utils/
│   │   └── logger.js             # Structured logging
│   └── index.js                  # App entry point
├── logs/
│   └── notifications.log         # Simulated email output
├── .env
├── .env.example
├── package.json
└── tests/
    ├── handlers/
    │   └── orderConfirmationHandler.test.js
    ├── services/
    │   └── retryService.test.js
    └── utils/
        └── hmacService.test.js
```

---

## 2. Required Packages

```json
{
  "dependencies": {
    "amqplib": "^0.10.3",
    "express": "^4.18.2",
    "axios": "^1.6.0",
    "dotenv": "^16.3.1",
    "winston": "^3.11.0"
  },
  "devDependencies": {
    "jest": "^29.7.0",
    "nodemon": "^3.0.2"
  }
}
```

**Package Rationale:**
- `amqplib` — AMQP 0-9-1 client for RabbitMQ (de-facto standard for Node.js)
- `express` — Minimal HTTP server for health check endpoint
- `axios` — HTTP client for vendor webhook delivery
- `winston` — Structured logging (JSON format, file + console transports)
- `dotenv` — Environment variable loading

---

## 3. RabbitMQ Queue Design

### 3.1 Exchange & Queue Topology

```
Exchange: eventhub.notifications (type: topic, durable: true)
    │
    ├── Routing key: email.*
    │       └── Queue: notifications.email (durable: true)
    │               └── Dead-letter → eventhub.notifications.dlx
    │                                  └── Queue: notifications.email.dead
    │
    └── Routing key: webhook.*
            └── Queue: notifications.webhook (durable: true)
                    └── Dead-letter → eventhub.notifications.dlx
                                       └── Queue: notifications.webhook.dead
```

### 3.2 Queue Declaration (rabbitmqService.js)

```javascript
async function setupQueues(channel) {
  // Main exchange
  await channel.assertExchange('eventhub.notifications', 'topic', { durable: true });

  // Dead-letter exchange
  await channel.assertExchange('eventhub.notifications.dlx', 'topic', { durable: true });

  // Email queue with dead-letter config
  await channel.assertQueue('notifications.email', {
    durable: true,
    arguments: {
      'x-dead-letter-exchange': 'eventhub.notifications.dlx',
      'x-dead-letter-routing-key': 'dead.email',
    },
  });

  // Webhook queue with dead-letter config
  await channel.assertQueue('notifications.webhook', {
    durable: true,
    arguments: {
      'x-dead-letter-exchange': 'eventhub.notifications.dlx',
      'x-dead-letter-routing-key': 'dead.webhook',
    },
  });

  // Dead-letter queues
  await channel.assertQueue('notifications.email.dead', { durable: true });
  await channel.assertQueue('notifications.webhook.dead', { durable: true });

  // Bindings
  await channel.bindQueue('notifications.email', 'eventhub.notifications', 'email.*');
  await channel.bindQueue('notifications.webhook', 'eventhub.notifications', 'webhook.*');
  await channel.bindQueue('notifications.email.dead', 'eventhub.notifications.dlx', 'dead.email');
  await channel.bindQueue('notifications.webhook.dead', 'eventhub.notifications.dlx', 'dead.webhook');
}
```

### 3.3 Message Format (Published by Main API)

All messages published to the exchange follow this envelope:

```json
{
  "type": "email.order_confirmation",
  "idempotency_key": "uuid-v4",
  "retry_count": 0,
  "published_at": "2026-05-07T12:00:00Z",
  "data": {
    // Type-specific payload (see Section 4)
  }
}
```

---

## 4. Message Schemas

### 4.1 email.order_confirmation

```json
{
  "type": "email.order_confirmation",
  "idempotency_key": "order-42-confirmation",
  "retry_count": 0,
  "published_at": "2026-05-07T12:05:00Z",
  "data": {
    "order_id": 42,
    "order_number": "EVH-2026-000042",
    "attendee_name": "Ahmad Razif",
    "attendee_email": "ahmad@example.com",
    "event_title": "Tech Conference 2026",
    "event_date": "2026-08-15T09:00:00Z",
    "event_timezone": "Asia/Kuala_Lumpur",
    "event_location": "Kuala Lumpur Convention Centre",
    "items": [
      { "name": "VIP Ticket", "quantity": 2, "unit_price": 150.00 }
    ],
    "total": 300.00,
    "currency": "MYR"
  }
}
```

### 4.2 email.event_reminder

```json
{
  "type": "email.event_reminder",
  "idempotency_key": "reminder-order-42-event-1",
  "data": {
    "attendee_name": "Ahmad Razif",
    "attendee_email": "ahmad@example.com",
    "event_title": "Tech Conference 2026",
    "event_date": "2026-08-15T09:00:00Z",
    "event_timezone": "Asia/Kuala_Lumpur",
    "event_location": "Kuala Lumpur Convention Centre",
    "hours_until_event": 24
  }
}
```

### 4.3 email.payout_completed

```json
{
  "type": "email.payout_completed",
  "idempotency_key": "payout-12-completed",
  "data": {
    "vendor_name": "Tech Events Sdn Bhd",
    "vendor_email": "vendor@techevents.com",
    "payout_id": 12,
    "gross_amount": 5000.00,
    "commission_amount": 500.00,
    "net_amount": 4500.00,
    "currency": "MYR",
    "bank_name": "Maybank",
    "account_last_four": "7890"
  }
}
```

### 4.4 email.vendor_approved / email.vendor_rejected

```json
{
  "type": "email.vendor_approved",
  "idempotency_key": "vendor-5-approved",
  "data": {
    "vendor_name": "Tech Events Sdn Bhd",
    "vendor_email": "vendor@techevents.com",
    "approved_at": "2026-05-07T12:00:00Z"
  }
}
```

```json
{
  "type": "email.vendor_rejected",
  "idempotency_key": "vendor-5-rejected",
  "data": {
    "vendor_name": "Tech Events Sdn Bhd",
    "vendor_email": "vendor@techevents.com",
    "rejection_reason": "Incomplete KYC documentation"
  }
}
```

### 4.5 webhook.vendor_event

```json
{
  "type": "webhook.vendor_event",
  "idempotency_key": "webhook-vendor-5-order-42",
  "data": {
    "vendor_id": 5,
    "webhook_url": "https://vendor-app.com/webhooks/eventhub",
    "webhook_secret": "vendor-specific-secret",
    "event_type": "new_order",
    "payload": {
      "order_id": 42,
      "order_number": "EVH-2026-000042",
      "event_id": 1,
      "event_title": "Tech Conference 2026",
      "tickets_sold": 2,
      "amount": 300.00,
      "currency": "MYR",
      "occurred_at": "2026-05-07T12:05:00Z"
    }
  }
}
```

---

## 5. Notification Handlers

### 5.1 Email Consumer (emailConsumer.js)

```javascript
const HANDLER_MAP = {
  'email.order_confirmation': require('../handlers/email/orderConfirmationHandler'),
  'email.event_reminder':     require('../handlers/email/eventReminderHandler'),
  'email.payout_completed':   require('../handlers/email/payoutCompletedHandler'),
  'email.vendor_approved':    require('../handlers/email/vendorApprovedHandler'),
  'email.vendor_rejected':    require('../handlers/email/vendorRejectedHandler'),
  'email.waitlist_available': require('../handlers/email/waitlistAvailableHandler'),
};

async function startEmailConsumer(channel) {
  await channel.consume('notifications.email', async (msg) => {
    if (!msg) return;

    const message = JSON.parse(msg.content.toString());
    const handler = HANDLER_MAP[message.type];

    if (!handler) {
      logger.warn(`No handler for message type: ${message.type}`);
      channel.ack(msg);  // Acknowledge unknown types to prevent queue blocking
      return;
    }

    try {
      await handler.handle(message.data);
      await deliveryTracker.markSent(message.idempotency_key);
      channel.ack(msg);
      logger.info(`Notification sent`, { type: message.type, idempotency_key: message.idempotency_key });
    } catch (error) {
      await handleFailure(channel, msg, message, error);
    }
  });
}
```

### 5.2 Order Confirmation Handler

```javascript
// handlers/email/orderConfirmationHandler.js
const logger = require('../../utils/logger');
const fs = require('fs').promises;
const path = require('path');

async function handle(data) {
  const emailContent = formatOrderConfirmationEmail(data);

  // Simulate email by logging to file
  await fs.appendFile(
    path.join(process.env.LOG_FILE_PATH || './logs/notifications.log'),
    JSON.stringify({
      timestamp: new Date().toISOString(),
      type: 'email.order_confirmation',
      to: data.attendee_email,
      subject: `Order Confirmed: ${data.order_number}`,
      body: emailContent,
    }) + '\n'
  );

  logger.info('Order confirmation email logged', {
    order_number: data.order_number,
    to: data.attendee_email,
  });
}

function formatOrderConfirmationEmail(data) {
  return `
Dear ${data.attendee_name},

Your order has been confirmed!

Order Number: ${data.order_number}
Event: ${data.event_title}
Date: ${new Date(data.event_date).toLocaleString('en-MY', { timeZone: data.event_timezone })}
Location: ${data.event_location}

Tickets:
${data.items.map(item => `  - ${item.name} x${item.quantity}: ${data.currency} ${(item.unit_price * item.quantity).toFixed(2)}`).join('\n')}

Total: ${data.currency} ${data.total.toFixed(2)}

Your QR codes are attached to this email.

Thank you for your purchase!
EventHub Team
  `.trim();
}

module.exports = { handle };
```

---

## 6. Retry Logic

### 6.1 Retry Strategy

```
Attempt 1: Immediate
Attempt 2: After 1 second   (1^2 = 1s)
Attempt 3: After 4 seconds  (2^2 = 4s)
Attempt 4: After 16 seconds (4^2 = 16s)
Attempt 5: After 64 seconds (8^2 = 64s)
After 5 failures: Route to dead-letter queue
```

### 6.2 Retry Implementation (retryService.js)

```javascript
// Retry delays in milliseconds
const RETRY_DELAYS = [0, 1000, 4000, 16000, 64000];
const MAX_RETRIES = 5;

async function handleFailure(channel, msg, message, error) {
  const retryCount = (message.retry_count || 0) + 1;

  logger.error('Notification delivery failed', {
    type: message.type,
    idempotency_key: message.idempotency_key,
    retry_count: retryCount,
    error: error.message,
  });

  if (retryCount >= MAX_RETRIES) {
    // Dead-letter: acknowledge original, let DLX handle it
    logger.error('Max retries exceeded, routing to dead-letter', {
      type: message.type,
      idempotency_key: message.idempotency_key,
    });
    await deliveryTracker.markDeadLettered(message.idempotency_key, error.message);
    channel.nack(msg, false, false);  // nack without requeue → goes to DLX
    return;
  }

  // Schedule retry with exponential backoff
  const delay = RETRY_DELAYS[retryCount] || 64000;
  await deliveryTracker.markRetrying(message.idempotency_key, retryCount, delay);

  // Re-publish with updated retry_count after delay
  setTimeout(async () => {
    const retryMessage = {
      ...message,
      retry_count: retryCount,
    };

    channel.publish(
      'eventhub.notifications',
      message.type,  // routing key
      Buffer.from(JSON.stringify(retryMessage)),
      { persistent: true }
    );

    channel.ack(msg);  // Acknowledge original message
  }, delay);
}

module.exports = { handleFailure, MAX_RETRIES, RETRY_DELAYS };
```

---

## 7. Vendor Webhook Delivery

### 7.1 Vendor Webhook Handler

```javascript
// handlers/webhook/vendorWebhookHandler.js
const axios = require('axios');
const hmacService = require('../../services/hmacService');
const logger = require('../../utils/logger');

async function handle(data) {
  const { vendor_id, webhook_url, webhook_secret, event_type, payload } = data;

  if (!webhook_url) {
    logger.info('Vendor has no webhook URL, skipping', { vendor_id });
    return;
  }

  const body = JSON.stringify(payload);
  const timestamp = Math.floor(Date.now() / 1000);
  const signature = hmacService.sign(body, webhook_secret);

  const response = await axios.post(webhook_url, payload, {
    timeout: 5000,  // 5 second timeout
    headers: {
      'Content-Type': 'application/json',
      'X-EventHub-Signature': signature,
      'X-EventHub-Timestamp': timestamp,
      'X-EventHub-Event': event_type,
    },
  });

  if (response.status < 200 || response.status >= 300) {
    throw new Error(`Vendor webhook returned ${response.status}: ${response.statusText}`);
  }

  logger.info('Vendor webhook delivered', {
    vendor_id,
    event_type,
    status: response.status,
  });
}

module.exports = { handle };
```

### 7.2 HMAC Service (hmacService.js)

```javascript
const crypto = require('crypto');

function sign(body, secret) {
  return crypto
    .createHmac('sha256', secret)
    .update(body)
    .digest('hex');
}

function verify(body, secret, signature) {
  const expected = sign(body, secret);
  return crypto.timingSafeEqual(
    Buffer.from(expected, 'hex'),
    Buffer.from(signature, 'hex')
  );
}

module.exports = { sign, verify };
```

---

## 8. Delivery Tracking

### 8.1 Delivery Tracker (deliveryTracker.js)

Tracks delivery status in a local log file (or SQLite for persistence):

```javascript
const fs = require('fs').promises;
const path = require('path');

const TRACKING_FILE = path.join(process.env.LOG_FILE_PATH || './logs', 'delivery-tracking.jsonl');

async function markSent(idempotencyKey) {
  await appendRecord({ idempotency_key: idempotencyKey, status: 'sent', timestamp: new Date().toISOString() });
}

async function markFailed(idempotencyKey, reason) {
  await appendRecord({ idempotency_key: idempotencyKey, status: 'failed', reason, timestamp: new Date().toISOString() });
}

async function markRetrying(idempotencyKey, retryCount, nextRetryMs) {
  await appendRecord({
    idempotency_key: idempotencyKey,
    status: 'retrying',
    retry_count: retryCount,
    next_retry_at: new Date(Date.now() + nextRetryMs).toISOString(),
    timestamp: new Date().toISOString(),
  });
}

async function markDeadLettered(idempotencyKey, reason) {
  await appendRecord({ idempotency_key: idempotencyKey, status: 'dead_lettered', reason, timestamp: new Date().toISOString() });
}

async function appendRecord(record) {
  await fs.appendFile(TRACKING_FILE, JSON.stringify(record) + '\n');
}

module.exports = { markSent, markFailed, markRetrying, markDeadLettered };
```

### 8.2 Status Endpoint

```javascript
// routes/health.js
router.get('/health', (req, res) => {
  res.json({ status: 'ok', service: 'notification-service', timestamp: new Date().toISOString() });
});

router.get('/api/notifications/stats', async (req, res) => {
  // Read tracking file and return summary stats
  res.json({
    queues: {
      email: await getQueueDepth('notifications.email'),
      webhook: await getQueueDepth('notifications.webhook'),
      dead_letter_email: await getQueueDepth('notifications.email.dead'),
      dead_letter_webhook: await getQueueDepth('notifications.webhook.dead'),
    }
  });
});
```

---

## 9. Coding Conventions

### 9.1 Environment Variables

```env
# .env
NODE_ENV=development
PORT=8002

# RabbitMQ
RABBITMQ_URL=amqp://guest:guest@rabbitmq:5672

# Logging
LOG_FILE_PATH=./logs
LOG_LEVEL=info

# Main API (for fetching vendor webhook details if needed)
MAIN_API_URL=http://main-api:8000
```

### 9.2 Logger Configuration (utils/logger.js)

```javascript
const winston = require('winston');

const logger = winston.createLogger({
  level: process.env.LOG_LEVEL || 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.json()
  ),
  transports: [
    new winston.transports.Console(),
    new winston.transports.File({
      filename: `${process.env.LOG_FILE_PATH || './logs'}/service.log`,
    }),
  ],
});

module.exports = logger;
```

### 9.3 App Entry Point (index.js)

```javascript
require('dotenv').config();
const express = require('express');
const { connectRabbitMQ, setupQueues } = require('./services/rabbitmqService');
const { startEmailConsumer } = require('./consumers/emailConsumer');
const { startWebhookConsumer } = require('./consumers/webhookConsumer');
const healthRoutes = require('./routes/health');
const logger = require('./utils/logger');

const app = express();
app.use(express.json());
app.use('/', healthRoutes);

async function start() {
  try {
    const { channel } = await connectRabbitMQ();
    await setupQueues(channel);
    await startEmailConsumer(channel);
    await startWebhookConsumer(channel);

    app.listen(process.env.PORT || 8002, () => {
      logger.info(`Notification service running on port ${process.env.PORT || 8002}`);
    });
  } catch (error) {
    logger.error('Failed to start notification service', { error: error.message });
    process.exit(1);
  }
}

start();
```

### 9.4 Running the Service

```bash
cd notification-service

# Development
npm install
npm run dev   # Uses nodemon for hot reload

# Production
npm start

# Tests
npm test
```

### 9.5 Key Test Cases

```javascript
// tests/services/retryService.test.js
describe('RetryService', () => {
  test('should retry with correct exponential backoff delays', ...)
  test('should route to dead-letter after max retries', ...)
  test('should not retry if max retries already reached', ...)
});

// tests/handlers/orderConfirmationHandler.test.js
describe('OrderConfirmationHandler', () => {
  test('should log email to file with correct format', ...)
  test('should include all order items in email body', ...)
});

// tests/utils/hmacService.test.js
describe('HmacService', () => {
  test('should generate consistent HMAC signatures', ...)
  test('should verify valid signatures', ...)
  test('should reject invalid signatures', ...)
});
```
