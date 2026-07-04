# EventHub — Frontend Service Specification

**Service:** Frontend  
**Stack:** Next.js 14 (App Router) / TypeScript / shadcn/ui / TanStack Query  
**Port:** 3000  
**Version:** 1.0  
**Date:** 2026-05-07

---

## Build Progress Checklist

### Phase 1: Project Scaffold
- [ ] Next.js 14 project created with TypeScript and App Router
- [ ] shadcn/ui initialized and configured
- [ ] TanStack Query (React Query) configured
- [ ] Axios API client configured with base URL and auth interceptor
- [ ] Route groups created: `(vendor)`, `(attendee)`, `(admin)`, `(auth)`
- [ ] Auth context/store implemented (token + user role)
- [ ] Role-based redirect middleware (`middleware.ts`)

### Phase 2: Auth Pages
- [ ] Login page (`/login`)
- [ ] Attendee registration page (`/register`)
- [ ] Vendor registration page (`/register/vendor`)
- [ ] Auth token stored in localStorage / httpOnly cookie
- [ ] Auto-redirect based on role after login

### Phase 3: Public & Attendee Pages
- [ ] Event listing page (`/events`) — paginated, public
- [ ] Event detail page (`/events/[id]`) — ticket types, pricing
- [ ] Checkout page (`/checkout`) — ticket selection + quantity
- [ ] Payment page (`/payment/[orderId]`) — gateway selection + status polling
- [ ] Order history page (`/orders`)
- [ ] Order detail page (`/orders/[id]`) — QR codes display
- [ ] Refund request page (`/orders/[id]/refund`)

### Phase 4: Vendor Dashboard
- [ ] Dashboard overview (`/vendor/dashboard`) — sales summary, pending payout
- [ ] Events list (`/vendor/events`)
- [ ] Create/edit event form (`/vendor/events/new`, `/vendor/events/[id]/edit`)
- [ ] Ticket types management (`/vendor/events/[id]/tickets`)
- [ ] Sales report per event (`/vendor/events/[id]/sales`)
- [ ] Payout history (`/vendor/payouts`)
- [ ] Profile & bank details (`/vendor/profile`)

### Phase 5: Admin Panel
- [ ] Vendor approvals list (`/admin/vendors`)
- [ ] Vendor detail + approve/reject (`/admin/vendors/[id]`)
- [ ] Refund requests list + approve/reject (`/admin/refunds`)
- [ ] Payout approvals (`/admin/payouts`)
- [ ] Platform analytics (`/admin/analytics`)
- [ ] Platform settings (`/admin/settings`)

### Phase 6: Polish
- [ ] Loading states on all data-fetching components
- [ ] Error boundaries and error states
- [ ] Toast notifications for actions (success/error)
- [ ] Responsive layout (mobile-friendly)

---

## Table of Contents

1. [Project Structure](#1-project-structure)
2. [Route Architecture](#2-route-architecture)
3. [API Client Setup](#3-api-client-setup)
4. [Auth Implementation](#4-auth-implementation)
5. [Key Page Specifications](#5-key-page-specifications)
6. [Component Library Usage](#6-component-library-usage)
7. [Coding Conventions](#7-coding-conventions)

---

## 1. Project Structure

```
frontend/
├── src/
│   ├── app/
│   │   ├── (auth)/
│   │   │   ├── login/page.tsx
│   │   │   ├── register/page.tsx
│   │   │   └── register/vendor/page.tsx
│   │   ├── (public)/
│   │   │   ├── events/page.tsx
│   │   │   └── events/[id]/page.tsx
│   │   ├── (attendee)/
│   │   │   ├── checkout/page.tsx
│   │   │   ├── payment/[orderId]/page.tsx
│   │   │   ├── orders/page.tsx
│   │   │   └── orders/[id]/page.tsx
│   │   ├── (vendor)/
│   │   │   ├── vendor/dashboard/page.tsx
│   │   │   ├── vendor/events/page.tsx
│   │   │   ├── vendor/events/new/page.tsx
│   │   │   ├── vendor/events/[id]/edit/page.tsx
│   │   │   ├── vendor/events/[id]/tickets/page.tsx
│   │   │   ├── vendor/events/[id]/sales/page.tsx
│   │   │   ├── vendor/payouts/page.tsx
│   │   │   └── vendor/profile/page.tsx
│   │   ├── (admin)/
│   │   │   ├── admin/vendors/page.tsx
│   │   │   ├── admin/vendors/[id]/page.tsx
│   │   │   ├── admin/refunds/page.tsx
│   │   │   ├── admin/payouts/page.tsx
│   │   │   ├── admin/analytics/page.tsx
│   │   │   └── admin/settings/page.tsx
│   │   ├── layout.tsx
│   │   └── page.tsx              # Redirect to /events
│   ├── components/
│   │   ├── ui/                   # shadcn/ui components
│   │   ├── layout/
│   │   │   ├── VendorSidebar.tsx
│   │   │   ├── AdminSidebar.tsx
│   │   │   └── AttendeeNav.tsx
│   │   ├── events/
│   │   │   ├── EventCard.tsx
│   │   │   ├── EventList.tsx
│   │   │   └── TicketTypeCard.tsx
│   │   ├── orders/
│   │   │   ├── OrderCard.tsx
│   │   │   └── QrCodeDisplay.tsx
│   │   └── shared/
│   │       ├── StatusBadge.tsx
│   │       ├── LoadingSpinner.tsx
│   │       └── ErrorMessage.tsx
│   ├── lib/
│   │   ├── api/
│   │   │   ├── client.ts         # Axios instance
│   │   │   ├── auth.ts           # Auth API calls
│   │   │   ├── events.ts         # Events API calls
│   │   │   ├── orders.ts         # Orders API calls
│   │   │   ├── payouts.ts        # Payouts API calls
│   │   │   └── admin.ts          # Admin API calls
│   │   └── utils.ts
│   ├── hooks/
│   │   ├── useAuth.ts
│   │   ├── useEvents.ts
│   │   └── useOrders.ts
│   ├── types/
│   │   └── index.ts              # All TypeScript interfaces
│   ├── providers/
│   │   └── QueryProvider.tsx     # TanStack Query provider
│   └── middleware.ts             # Route protection
├── .env.local
└── package.json
```

---

## 2. Route Architecture

### Route Groups & Layouts

```
/                           → Redirect to /events
/login                      → Public
/register                   → Public (attendee)
/register/vendor            → Public (vendor)
/events                     → Public
/events/[id]                → Public

/checkout                   → Attendee only
/payment/[orderId]          → Attendee only
/orders                     → Attendee only
/orders/[id]                → Attendee only

/vendor/*                   → Vendor only (KYC verified)
/admin/*                    → Admin only
```

### Middleware (middleware.ts)

```typescript
import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export function middleware(request: NextRequest) {
  const token = request.cookies.get('auth_token')?.value;
  const role = request.cookies.get('user_role')?.value;
  const path = request.nextUrl.pathname;

  // Protect vendor routes
  if (path.startsWith('/vendor') && role !== 'vendor') {
    return NextResponse.redirect(new URL('/login', request.url));
  }

  // Protect admin routes
  if (path.startsWith('/admin') && role !== 'admin') {
    return NextResponse.redirect(new URL('/login', request.url));
  }

  // Protect attendee routes
  const attendeeRoutes = ['/checkout', '/payment', '/orders'];
  if (attendeeRoutes.some(r => path.startsWith(r)) && !token) {
    return NextResponse.redirect(new URL('/login', request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/vendor/:path*', '/admin/:path*', '/checkout/:path*', '/payment/:path*', '/orders/:path*'],
};
```

---

## 3. API Client Setup

### Axios Instance (lib/api/client.ts)

```typescript
import axios from 'axios';

const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1',
  headers: { 'Content-Type': 'application/json' },
  timeout: 10000,
});

// Request interceptor — attach auth token
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor — handle 401
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user_role');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default apiClient;
```

### TanStack Query Provider (providers/QueryProvider.tsx)

```typescript
'use client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useState } from 'react';

export function QueryProvider({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(() => new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 60 * 1000,  // 1 minute
        retry: 1,
      },
    },
  }));

  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}
```

---

## 4. Auth Implementation

### useAuth Hook (hooks/useAuth.ts)

```typescript
'use client';
import { create } from 'zustand';

interface AuthState {
  token: string | null;
  user: { id: number; name: string; email: string; role: string } | null;
  login: (token: string, user: AuthState['user']) => void;
  logout: () => void;
}

export const useAuth = create<AuthState>((set) => ({
  token: typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null,
  user: typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || 'null') : null,

  login: (token, user) => {
    localStorage.setItem('auth_token', token);
    localStorage.setItem('user', JSON.stringify(user));
    document.cookie = `auth_token=${token}; path=/`;
    document.cookie = `user_role=${user?.role}; path=/`;
    set({ token, user });
  },

  logout: () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    document.cookie = 'auth_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    document.cookie = 'user_role=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    set({ token: null, user: null });
  },
}));
```

---

## 5. Key Page Specifications

### 5.1 Checkout Page

**Flow:**
1. User arrives from event detail page with `event_id` in query params
2. Page loads ticket types for the event
3. User selects quantities (respects min/max per ticket type)
4. Group bundle discount shown in real-time if minimum met
5. "Proceed to Payment" generates `idempotency_key` (UUID v4) and calls `POST /attendee/orders`
6. On success: redirect to `/payment/[orderId]`

**Key Components:**
- `TicketSelector` — quantity input per ticket type with availability display
- `OrderSummary` — running total with discount breakdown
- Countdown timer showing 15-minute hold window (after order created)

### 5.2 Payment Page

**Flow:**
1. Page loads order details
2. User selects gateway (Stripe Sim / PayPal Sim)
3. "Pay Now" calls `POST /attendee/payments/initiate`
4. Page polls `GET /attendee/orders/[id]` every 3 seconds
5. When order status changes to `paid`: show success + link to order detail
6. When order status changes to `cancelled`/`expired`: show failure message

**Key Components:**
- `PaymentGatewaySelector` — radio buttons for gateway selection
- `OrderCountdown` — shows time remaining before order expires
- `PaymentStatusPoller` — polls order status and updates UI

### 5.3 Vendor Dashboard

**Sections:**
- **Summary Cards:** Total events, tickets sold this month, revenue this month, pending payout balance
- **Recent Orders Table:** Last 10 orders across all events
- **Events List:** Quick view of active events with sold/total tickets

### 5.4 Admin Vendor Approvals

**Table columns:** Business name, email, registered date, KYC status, actions (Approve/Reject)

**Approve flow:**
1. Admin clicks "Approve"
2. Confirmation dialog
3. `POST /admin/vendors/[id]/approve`
4. Table row updates to "Verified" status
5. Toast: "Vendor approved successfully"

**Reject flow:**
1. Admin clicks "Reject"
2. Modal with rejection reason textarea (required)
3. `POST /admin/vendors/[id]/reject` with `{ reason }`
4. Toast: "Vendor rejected"

---

## 6. Component Library Usage

### shadcn/ui Components Used

```bash
npx shadcn-ui@latest add button
npx shadcn-ui@latest add card
npx shadcn-ui@latest add table
npx shadcn-ui@latest add form
npx shadcn-ui@latest add input
npx shadcn-ui@latest add select
npx shadcn-ui@latest add badge
npx shadcn-ui@latest add dialog
npx shadcn-ui@latest add toast
npx shadcn-ui@latest add tabs
npx shadcn-ui@latest add separator
npx shadcn-ui@latest add skeleton
npx shadcn-ui@latest add alert
```

### StatusBadge Component

```typescript
// components/shared/StatusBadge.tsx
const STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  paid: 'default',
  published: 'default',
  verified: 'default',
  pending_payment: 'secondary',
  pending: 'secondary',
  draft: 'outline',
  cancelled: 'destructive',
  expired: 'destructive',
  rejected: 'destructive',
  refunded: 'secondary',
};

export function StatusBadge({ status }: { status: string }) {
  return (
    <Badge variant={STATUS_VARIANTS[status] || 'outline'}>
      {status.replace(/_/g, ' ')}
    </Badge>
  );
}
```

---

## 7. Coding Conventions

### 7.1 TypeScript Interfaces (types/index.ts)

```typescript
export interface Event {
  id: number;
  title: string;
  description: string;
  location_name: string;
  location_address: string;
  start_datetime: string;  // ISO 8601 UTC
  end_datetime: string;
  timezone: string;
  status: 'draft' | 'published' | 'ongoing' | 'completed' | 'cancelled';
  banner_image?: string;
  vendor_id: number;
  ticket_types?: TicketType[];
}

export interface TicketType {
  id: number;
  event_id: number;
  name: string;
  type: 'general_admission' | 'vip' | 'early_bird' | 'group_bundle';
  price: number;
  quantity: number;
  quantity_sold: number;
  quantity_held: number;
  available_quantity: number;
  available_from?: string;
  available_until?: string;
  min_purchase: number;
  max_purchase: number;
  group_min_quantity?: number;
  group_discount_percent?: number;
  is_active: boolean;
}

export interface Order {
  id: number;
  order_number: string;
  status: 'pending_payment' | 'paid' | 'cancelled' | 'expired' | 'refunded' | 'partially_refunded';
  subtotal: number;
  currency: string;
  expires_at: string;
  paid_at?: string;
  items: OrderItem[];
}

export interface OrderItem {
  id: number;
  ticket_type_id: number;
  ticket_type_name: string;
  quantity: number;
  unit_price: number;
  line_total: number;
  qr_code_token: string;
  is_checked_in: boolean;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message: string;
  errors?: Record<string, string[]>;
}
```

### 7.2 Data Fetching Pattern

```typescript
// hooks/useEvents.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getEvents, createEvent } from '@/lib/api/events';

export function useEvents(params?: { page?: number; status?: string }) {
  return useQuery({
    queryKey: ['events', params],
    queryFn: () => getEvents(params),
  });
}

export function useCreateEvent() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createEvent,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['events'] });
    },
  });
}
```

### 7.3 Environment Variables

```env
# .env.local
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
```

### 7.4 Running the Frontend

```bash
cd frontend
npm install
npm run dev    # Development server on port 3000
npm run build  # Production build
npm start      # Production server
```
