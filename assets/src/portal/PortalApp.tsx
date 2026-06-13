/**
 * Customer self-service portal: magic-link login, profile, bookings, reschedule,
 * cancel.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPatch, apiPost, ApiError, clearToken, getToken } from './api';
import type { Availability, PortalBooking, PortalBookings, PortalProfile, Slot } from './types';

type View = 'loading' | 'login' | 'dashboard';

function money(total: string, currency: string): string {
  return `${currency} ${total}`;
}

export function PortalApp() {
  const [view, setView] = useState<View>('loading');
  const [profile, setProfile] = useState<PortalProfile | null>(null);
  const [bookings, setBookings] = useState<PortalBookings>({ upcoming: [], past: [] });
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const load = useCallback(async () => {
    if (getToken() === '') {
      setView('login');
      return;
    }
    try {
      const [me, list] = await Promise.all([
        apiGet<PortalProfile>('portal/me'),
        apiGet<PortalBookings>('portal/bookings'),
      ]);
      setProfile(me);
      setBookings(list);
      setView('dashboard');
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        clearToken();
        setView('login');
      } else {
        setError('Could not load your portal.');
        setView('login');
      }
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  if (view === 'loading') {
    return <p className="bkra-p-4 bkra-text-sm bkra-text-gray-500">Loading…</p>;
  }

  return (
    <div className="bkra-mx-auto bkra-max-w-2xl bkra-p-4 bkra-text-gray-900">
      {error && <div role="alert" className="bkra-mb-4 bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">{error}</div>}
      {notice && <div className="bkra-mb-4 bkra-rounded bkra-bg-green-50 bkra-p-3 bkra-text-sm bkra-text-green-700">{notice}</div>}

      {view === 'login' ? (
        <LoginForm onSent={() => setNotice('If that email is registered, a secure link is on its way.')} />
      ) : (
        <Dashboard
          profile={profile as PortalProfile}
          bookings={bookings}
          onChanged={load}
          onProfileSaved={() => setNotice('Profile updated.')}
          onError={setError}
          onLogout={() => {
            clearToken();
            setProfile(null);
            setView('login');
          }}
        />
      )}
    </div>
  );
}

function LoginForm({ onSent }: { onSent: () => void }) {
  const [email, setEmail] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    try {
      await apiPost('portal/request-link', { email });
      onSent();
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={submit} className="bkra-space-y-3 bkra-rounded-xl bkra-border bkra-border-gray-200 bkra-bg-white bkra-p-6" aria-label="Sign in">
      <h2 className="bkra-text-lg bkra-font-semibold">Manage your bookings</h2>
      <p className="bkra-text-sm bkra-text-gray-600">Enter your email and we&apos;ll send you a secure link.</p>
      <input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@example.com" aria-label="Email" className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-p-3" />
      <button type="submit" disabled={busy} className="bkra-w-full bkra-rounded bkra-bg-bookora-600 bkra-p-3 bkra-font-medium bkra-text-white disabled:bkra-opacity-50">
        {busy ? 'Sending…' : 'Send my link'}
      </button>
    </form>
  );
}

interface DashboardProps {
  profile: PortalProfile;
  bookings: PortalBookings;
  onChanged: () => Promise<void> | void;
  onProfileSaved: () => void;
  onError: (message: string) => void;
  onLogout: () => void;
}

function Dashboard({ profile, bookings, onChanged, onProfileSaved, onError, onLogout }: DashboardProps) {
  const [name, setName] = useState(profile.name);
  const [phone, setPhone] = useState(profile.phone);

  const saveProfile = async () => {
    try {
      await apiPatch('portal/me', { name, phone });
      onProfileSaved();
    } catch {
      onError('Could not save your profile.');
    }
  };

  return (
    <div className="bkra-space-y-6">
      <div className="bkra-flex bkra-items-center bkra-justify-between">
        <h2 className="bkra-text-lg bkra-font-semibold">Hi {profile.name || 'there'}</h2>
        <button type="button" onClick={onLogout} className="bkra-text-sm bkra-text-gray-500">Sign out</button>
      </div>

      <section aria-label="Upcoming bookings" className="bkra-space-y-2">
        <h3 className="bkra-text-sm bkra-font-semibold">Upcoming</h3>
        {bookings.upcoming.length === 0 && <p className="bkra-text-sm bkra-text-gray-500">No upcoming bookings.</p>}
        {bookings.upcoming.map((b) => (
          <BookingRow key={b.id} booking={b} onChanged={onChanged} onError={onError} />
        ))}
      </section>

      <section aria-label="Past bookings" className="bkra-space-y-2">
        <h3 className="bkra-text-sm bkra-font-semibold">Past</h3>
        {bookings.past.length === 0 && <p className="bkra-text-sm bkra-text-gray-500">No past bookings.</p>}
        {bookings.past.map((b) => (
          <div key={b.id} className="bkra-rounded bkra-bg-gray-50 bkra-p-2 bkra-text-sm">
            {b.service_name ?? 'Service'} — {b.start_at.slice(0, 16)} ({b.status})
          </div>
        ))}
      </section>

      <section aria-label="Profile" className="bkra-space-y-2 bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-p-4">
        <h3 className="bkra-text-sm bkra-font-semibold">Your details</h3>
        <input value={name} onChange={(e) => setName(e.target.value)} aria-label="Name" placeholder="Name" className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-p-2 bkra-text-sm" />
        <input value={phone} onChange={(e) => setPhone(e.target.value)} aria-label="Phone" placeholder="Phone" className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-p-2 bkra-text-sm" />
        <input value={profile.email} readOnly aria-label="Email" className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-200 bkra-bg-gray-50 bkra-p-2 bkra-text-sm bkra-text-gray-500" />
        <button type="button" onClick={saveProfile} className="bkra-rounded bkra-bg-bookora-600 bkra-px-4 bkra-py-2 bkra-text-sm bkra-font-medium bkra-text-white">Save</button>
      </section>
    </div>
  );
}

function BookingRow({ booking, onChanged, onError }: { booking: PortalBooking; onChanged: () => Promise<void> | void; onError: (m: string) => void }) {
  const [rescheduling, setRescheduling] = useState(false);
  const [date, setDate] = useState(booking.start_at.slice(0, 10));
  const [slots, setSlots] = useState<Slot[]>([]);
  const [loadingSlots, setLoadingSlots] = useState(false);

  const loadSlots = async () => {
    setLoadingSlots(true);
    try {
      const r = await apiGet<Availability>(`book/availability?service_id=${booking.service_id}&staff_id=${booking.staff_id ?? ''}&date=${date}`);
      setSlots(r.slots);
    } catch {
      onError('Could not load available times.');
    } finally {
      setLoadingSlots(false);
    }
  };

  const reschedule = async (slot: Slot) => {
    try {
      await apiPost(`portal/bookings/${booking.id}/reschedule`, { start: slot.start_local });
      setRescheduling(false);
      await onChanged();
    } catch (e) {
      onError(e instanceof ApiError ? e.message : 'Could not reschedule.');
    }
  };

  const cancel = async () => {
    // eslint-disable-next-line no-alert
    if (!window.confirm('Cancel this booking?')) {
      return;
    }
    try {
      await apiPost(`portal/bookings/${booking.id}/cancel`);
      await onChanged();
    } catch (e) {
      onError(e instanceof ApiError ? e.message : 'Could not cancel.');
    }
  };

  return (
    <div className="bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-p-3 bkra-text-sm">
      <div className="bkra-flex bkra-flex-wrap bkra-items-center bkra-justify-between bkra-gap-2">
        <span>
          <span className="bkra-font-medium">{booking.service_name ?? 'Service'}</span> — {booking.start_at.slice(0, 16)}
          {booking.staff_name ? ` · ${booking.staff_name}` : ''} · {money(booking.total, booking.currency)}
        </span>
        <span className="bkra-flex bkra-gap-2">
          {booking.can_reschedule && (
            <button type="button" onClick={() => setRescheduling((v) => !v)} className="bkra-text-bookora-700">Reschedule</button>
          )}
          {booking.can_cancel && (
            <button type="button" onClick={cancel} className="bkra-text-red-700">Cancel</button>
          )}
        </span>
      </div>

      {rescheduling && (
        <div className="bkra-mt-3 bkra-space-y-2 bkra-border-t bkra-border-gray-100 bkra-pt-3">
          <div className="bkra-flex bkra-gap-2">
            <input type="date" value={date} min={new Date().toISOString().slice(0, 10)} onChange={(e) => setDate(e.target.value)} aria-label="New date" className="bkra-rounded bkra-border bkra-border-gray-300 bkra-p-2" />
            <button type="button" onClick={loadSlots} className="bkra-rounded bkra-bg-bookora-600 bkra-px-3 bkra-text-white">Find times</button>
          </div>
          {loadingSlots && <p className="bkra-text-xs bkra-text-gray-500">Loading…</p>}
          <div className="bkra-grid bkra-grid-cols-4 bkra-gap-1">
            {slots.map((s) => (
              <button key={`${s.staff_id}-${s.start_utc}`} type="button" onClick={() => reschedule(s)} className="bkra-rounded bkra-border bkra-border-gray-200 bkra-p-1 bkra-text-xs hover:bkra-border-bookora-600">
                {s.start_local.slice(11, 16)}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
