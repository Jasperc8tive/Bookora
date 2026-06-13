/**
 * Types for the customer portal.
 */
export interface BookoraPortalGlobal {
  restUrl: string;
  nonce: string;
}

declare global {
  interface Window {
    BookoraPortal?: BookoraPortalGlobal;
  }
}

export interface PortalProfile {
  id: number;
  name: string;
  email: string;
  phone: string;
  timezone: string;
  locale: string;
}

export interface PortalBooking {
  id: number;
  service_id: number;
  staff_id: number | null;
  start_at: string;
  end_at: string;
  status: string;
  total: string;
  currency: string;
  service_name: string | null;
  staff_name: string | null;
  can_reschedule: boolean;
  can_cancel: boolean;
}

export interface PortalBookings {
  upcoming: PortalBooking[];
  past: PortalBooking[];
}

export interface Slot {
  staff_id: number;
  start_utc: string;
  end_utc: string;
  start_local: string;
}

export interface Availability {
  date: string;
  service_id: number;
  slots: Slot[];
}

export interface ApiEnvelope<T> {
  success: boolean;
  data: T;
}
