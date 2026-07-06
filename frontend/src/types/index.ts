export interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'vendor' | 'attendee';
  vendor?: Vendor;
  attendee?: Attendee;
}

export interface Vendor {
  id: number;
  user_id: number;
  business_name: string;
  slug: string;
  description?: string;
  contact_email?: string;
  contact_phone?: string;
  status: 'pending' | 'active' | 'suspended';
  commission_rate?: number;
}

export interface Attendee {
  id: number;
  user_id: number;
  phone?: string;
}

export interface Event {
  id: number;
  vendor_id: number;
  title: string;
  slug: string;
  description?: string;
  location?: string;
  venue_name?: string;
  starts_at: string;
  ends_at: string;
  sale_starts_at?: string;
  sale_ends_at?: string;
  banner_image?: string;
  category?: string;
  tags?: string[];
  status: 'draft' | 'published' | 'cancelled' | 'completed';
  is_featured: boolean;
  vendor?: Vendor;
  ticket_types?: TicketType[];
}

export interface TicketType {
  id: number;
  event_id: number;
  name: string;
  description?: string;
  price: string;
  total_quantity: number;
  quantity_sold: number;
  quantity_reserved: number;
  max_per_order: number;
  sale_starts_at?: string;
  sale_ends_at?: string;
  is_active: boolean;
}

export interface Order {
  id: number;
  order_number: string;
  attendee_id: number;
  event_id: number;
  vendor_id: number;
  subtotal: string;
  platform_fee: string;
  total_amount: string;
  status: 'pending_payment' | 'confirmed' | 'cancelled' | 'expired' | 'partially_refunded' | 'refunded';
  payment_method?: string;
  payment_reference?: string;
  expires_at?: string;
  paid_at?: string;
  cancelled_at?: string;
  cancellation_reason?: string;
  created_at?: string;
  updated_at?: string;
  items?: OrderItem[];
  event?: Event;
}

export interface OrderItem {
  id: number;
  order_id: number;
  ticket_type_id: number;
  quantity: number;
  unit_price: string;
  subtotal: string;
  ticket_code: string;
  status: 'active' | 'used' | 'cancelled' | 'refunded';
  checked_in_at?: string;
  ticket_type?: TicketType;
}

export interface PaginatedResponse<T> {
  data: {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface ApiResponse<T> {
  data: T;
  message?: string;
}
