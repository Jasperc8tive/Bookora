/**
 * Shared types for the Bookora admin SPA.
 */

export interface BookoraAdminGlobal {
  restUrl: string;
  nonce: string;
  version: string;
  screen?: string;
}

export interface ServiceCategory {
  id: number;
  name: string;
  slug: string | null;
  description: string | null;
  color: string | null;
  sort_order: number;
  status: string;
}

export interface Service {
  id: number;
  category_id: number | null;
  name: string;
  slug: string | null;
  description: string | null;
  duration_min: number;
  buffer_before_min: number;
  buffer_after_min: number;
  price: string;
  currency: string;
  deposit_type: string;
  deposit_value: string;
  capacity: number;
  image_url: string | null;
  status: string;
}

export interface StaffMember {
  id: number;
  display_name: string;
  email: string | null;
  phone: string | null;
  bio: string | null;
  avatar_url: string | null;
  color: string | null;
  status: string;
  skills?: string[];
  service_ids?: number[];
}

export interface AvailabilityTimeEntry {
  weekday: number;
  start_time: string;
  end_time: string;
}

export interface AvailabilityDateEntry {
  start_date: string;
  end_date: string;
  note: string | null;
}

export interface Availability {
  working_hours: AvailabilityTimeEntry[];
  breaks: AvailabilityTimeEntry[];
  time_off: AvailabilityDateEntry[];
  holidays: AvailabilityDateEntry[];
}

export interface CustomerStats {
  count: number;
  last_at: string | null;
  total_spent: number;
}

export interface Customer {
  id: number;
  name: string | null;
  first_name: string | null;
  last_name: string | null;
  email: string | null;
  phone: string | null;
  timezone: string | null;
  locale: string | null;
  tags?: string[];
  stats?: CustomerStats;
}

export interface CustomerNote {
  id: number;
  body: string;
  author_id: number | null;
  is_private: number;
  created_at: string;
}

export interface CustomerBooking {
  id: number;
  start_at: string;
  end_at: string;
  status: string;
  total: string;
  currency: string;
  service_name: string | null;
  staff_name: string | null;
}

export interface TimelineEvent {
  type: string;
  at: string;
  summary: string;
  meta: Record<string, unknown>;
}

export interface Paginated<T> {
  items: T[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

export interface SystemHealth {
  plugin: {
    name: string;
    version: string;
    db_version: string;
  };
  env: {
    php: string;
    wp: string;
    prefix: string;
  };
  database: {
    migrated: boolean;
    applied: string[];
    tables: Record<string, boolean>;
  };
  healthy: boolean;
  timestamp: string;
}

export interface ApiEnvelope<T> {
  success: boolean;
  data: T;
}

interface WpMediaFrame {
  on: (event: string, cb: () => void) => void;
  open: () => void;
  state: () => { get: (key: string) => { first: () => { toJSON: () => { url: string } } } };
}

declare global {
  interface Window {
    BookoraAdmin?: BookoraAdminGlobal;
    wp?: {
      media?: ((options: Record<string, unknown>) => WpMediaFrame) & Record<string, unknown>;
    };
  }
}
