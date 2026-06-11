/**
 * Types for the public booking wizard.
 */
export interface BookoraBookingGlobal {
  restUrl: string;
  nonce: string;
}

declare global {
  interface Window {
    BookoraBooking?: BookoraBookingGlobal;
  }
}

export interface Service {
  id: number;
  name: string;
  description: string;
  duration_min: number;
  price: number;
  currency: string;
  category_id: number | null;
}

export interface StaffOption {
  id: number;
  name: string;
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

export interface HoldResult {
  token: string;
  expires_at: string;
}

export interface BookingConfirmation {
  appointment: {
    id: number;
    service_id: number;
    staff_id: number;
    start_at: string;
    end_at: string;
    status: string;
    total: string;
    currency: string;
  };
}

export interface ApiEnvelope<T> {
  success: boolean;
  data: T;
}
