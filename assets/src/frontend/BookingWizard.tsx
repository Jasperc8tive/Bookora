/**
 * Mobile-first, multi-step public booking wizard:
 * service → staff → date → time → details → payment → confirmation.
 */
import { useEffect, useState } from 'react';
import { apiGet, apiPost, ApiError } from './api';
import type { Availability, BookingConfirmation, HoldResult, Service, Slot, StaffOption } from './types';

type Step = 'service' | 'staff' | 'date' | 'time' | 'details' | 'payment' | 'done';

const STEPS: { id: Step; label: string }[] = [
  { id: 'service', label: 'Service' },
  { id: 'staff', label: 'Staff' },
  { id: 'date', label: 'Date' },
  { id: 'time', label: 'Time' },
  { id: 'details', label: 'Details' },
  { id: 'payment', label: 'Payment' },
];

function money(amount: number, currency: string): string {
  return `${currency} ${amount.toLocaleString(undefined, { minimumFractionDigits: 0 })}`;
}

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

export function BookingWizard({ initialServiceId }: { initialServiceId?: number }) {
  const [step, setStep] = useState<Step>('service');
  const [services, setServices] = useState<Service[]>([]);
  const [service, setService] = useState<Service | null>(null);
  const [staffOptions, setStaffOptions] = useState<StaffOption[]>([]);
  const [staffId, setStaffId] = useState<number | null>(null);
  const [date, setDate] = useState<string>(today());
  const [slots, setSlots] = useState<Slot[]>([]);
  const [slot, setSlot] = useState<Slot | null>(null);
  const [token, setToken] = useState<string>('');
  const [details, setDetails] = useState({ name: '', email: '', phone: '', hp: '' });
  const [confirmation, setConfirmation] = useState<BookingConfirmation | null>(null);
  const [gateways, setGateways] = useState<{ key: string; label: string }[]>([]);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    apiGet<{ items: Service[] }>('book/services')
      .then((r) => {
        setServices(r.items);
        if (initialServiceId) {
          const pre = r.items.find((s) => s.id === initialServiceId);
          if (pre) {
            void chooseService(pre);
          }
        }
      })
      .catch(() => setError('Could not load services.'))
      .finally(() => setLoading(false));
    void apiGet<{ gateways: { key: string; label: string }[] }>('book/pay/gateways')
      .then((r) => setGateways(r.gateways))
      .catch(() => undefined);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const chooseService = async (selected: Service) => {
    setService(selected);
    setError('');
    setLoading(true);
    try {
      const r = await apiGet<{ items: StaffOption[] }>(`book/services/${selected.id}/staff`);
      setStaffOptions(r.items);
      setStep('staff');
    } catch {
      setError('Could not load staff.');
    } finally {
      setLoading(false);
    }
  };

  const chooseStaff = (id: number | null) => {
    setStaffId(id);
    setStep('date');
  };

  const loadSlots = async () => {
    if (!service) {
      return;
    }
    setError('');
    setLoading(true);
    try {
      const r = await apiGet<Availability>('book/availability', {
        service_id: service.id,
        staff_id: staffId ?? '',
        date,
      });
      setSlots(r.slots);
      setStep('time');
    } catch {
      setError('Could not load times.');
    } finally {
      setLoading(false);
    }
  };

  const chooseSlot = async (selected: Slot) => {
    if (!service) {
      return;
    }
    setError('');
    setLoading(true);
    try {
      const hold = await apiPost<HoldResult>('book/hold', {
        service_id: service.id,
        staff_id: selected.staff_id,
        start: selected.start_local,
      });
      setSlot(selected);
      setStaffId(selected.staff_id);
      setToken(hold.token);
      setStep('details');
    } catch (e) {
      if (e instanceof ApiError && e.status === 409) {
        setError('Sorry, that time was just taken. Please pick another.');
        void loadSlots();
      } else {
        setError('Could not hold that time.');
      }
    } finally {
      setLoading(false);
    }
  };

  /** Create the (pending) appointment; returns its id or null on failure. */
  const createAppointment = async (): Promise<BookingConfirmation | null> => {
    if (!service || !slot) {
      return null;
    }
    try {
      return await apiPost<BookingConfirmation>('book/appointments', {
        service_id: service.id,
        staff_id: slot.staff_id,
        start: slot.start_local,
        session_token: token,
        customer: { name: details.name, email: details.email, phone: details.phone },
        hp: details.hp,
      });
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Could not complete your booking.');
      return null;
    }
  };

  /** Pay at the venue: just create the booking. */
  const confirmBooking = async () => {
    setError('');
    setLoading(true);
    const result = await createAppointment();
    if (result) {
      setConfirmation(result);
      setStep('done');
    }
    setLoading(false);
  };

  /** Pay online: create the booking, then redirect to the gateway. */
  const payOnline = async (gatewayKey: string) => {
    setError('');
    setLoading(true);
    const result = await createAppointment();
    if (!result) {
      setLoading(false);
      return;
    }
    try {
      const init = await apiPost<{ redirect_url: string }>('book/pay/initialize', {
        appointment_id: result.appointment.id,
        gateway: gatewayKey,
        type: 'full',
        callback_url: window.location.href,
      });
      window.location.href = init.redirect_url;
    } catch (e) {
      // Booking is already reserved; fall back to pay-on-site messaging.
      setConfirmation(result);
      setStep('done');
      setError(e instanceof ApiError ? e.message : 'Online payment could not start; your booking is reserved.');
      setLoading(false);
    }
  };

  const activeIndex = STEPS.findIndex((s) => s.id === step);

  return (
    <div className="bkra-mx-auto bkra-max-w-xl bkra-rounded-xl bkra-border bkra-border-gray-200 bkra-bg-white bkra-p-4 bkra-text-gray-900 sm:bkra-p-6">
      {step !== 'done' && (
        <ol className="bkra-mb-5 bkra-flex bkra-flex-wrap bkra-gap-1 bkra-text-xs">
          {STEPS.map((s, i) => (
            <li
              key={s.id}
              className={`bkra-rounded bkra-px-2 bkra-py-1 ${i <= activeIndex ? 'bkra-bg-bookora-600 bkra-text-white' : 'bkra-bg-gray-100 bkra-text-gray-500'}`}
            >
              {s.label}
            </li>
          ))}
        </ol>
      )}

      {error && (
        <div role="alert" className="bkra-mb-4 bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">
          {error}
        </div>
      )}

      {loading && <p className="bkra-mb-4 bkra-text-sm bkra-text-gray-500">Loading…</p>}

      {step === 'service' && (
        <section aria-label="Choose a service" className="bkra-space-y-2">
          <h2 className="bkra-text-lg bkra-font-semibold">Choose a service</h2>
          {services.length === 0 && !loading && <p className="bkra-text-sm bkra-text-gray-500">No services available.</p>}
          {services.map((s) => (
            <button
              key={s.id}
              type="button"
              onClick={() => void chooseService(s)}
              className="bkra-flex bkra-w-full bkra-items-center bkra-justify-between bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-p-3 bkra-text-left hover:bkra-border-bookora-600"
            >
              <span>
                <span className="bkra-block bkra-font-medium">{s.name}</span>
                <span className="bkra-block bkra-text-xs bkra-text-gray-500">{s.duration_min} min</span>
              </span>
              <span className="bkra-font-medium">{money(s.price, s.currency)}</span>
            </button>
          ))}
        </section>
      )}

      {step === 'staff' && service && (
        <section aria-label="Choose staff" className="bkra-space-y-2">
          <h2 className="bkra-text-lg bkra-font-semibold">Choose a team member</h2>
          <button type="button" onClick={() => chooseStaff(null)} className="bkra-w-full bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-p-3 bkra-text-left hover:bkra-border-bookora-600">
            Any available
          </button>
          {staffOptions.map((m) => (
            <button key={m.id} type="button" onClick={() => chooseStaff(m.id)} className="bkra-w-full bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-p-3 bkra-text-left hover:bkra-border-bookora-600">
              {m.name}
            </button>
          ))}
          <BackButton onClick={() => setStep('service')} />
        </section>
      )}

      {step === 'date' && (
        <section aria-label="Choose a date" className="bkra-space-y-3">
          <h2 className="bkra-text-lg bkra-font-semibold">Pick a date</h2>
          <input
            type="date"
            min={today()}
            value={date}
            onChange={(e) => setDate(e.target.value)}
            aria-label="Date"
            className="bkra-w-full bkra-rounded-lg bkra-border bkra-border-gray-300 bkra-p-3"
          />
          <button type="button" onClick={() => void loadSlots()} className="bkra-w-full bkra-rounded-lg bkra-bg-bookora-600 bkra-p-3 bkra-font-medium bkra-text-white">
            See available times
          </button>
          <BackButton onClick={() => setStep('staff')} />
        </section>
      )}

      {step === 'time' && (
        <section aria-label="Choose a time" className="bkra-space-y-3">
          <h2 className="bkra-text-lg bkra-font-semibold">Pick a time</h2>
          {slots.length === 0 && !loading && <p className="bkra-text-sm bkra-text-gray-500">No times available on this date.</p>}
          <div className="bkra-grid bkra-grid-cols-3 bkra-gap-2">
            {slots.map((s) => (
              <button
                key={`${s.staff_id}-${s.start_utc}`}
                type="button"
                onClick={() => void chooseSlot(s)}
                className="bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-p-2 bkra-text-sm hover:bkra-border-bookora-600"
              >
                {s.start_local.slice(11, 16)}
              </button>
            ))}
          </div>
          <BackButton onClick={() => setStep('date')} />
        </section>
      )}

      {step === 'details' && (
        <form
          aria-label="Your details"
          className="bkra-space-y-3"
          onSubmit={(e) => {
            e.preventDefault();
            setStep('payment');
          }}
        >
          <h2 className="bkra-text-lg bkra-font-semibold">Your details</h2>
          <input required placeholder="Full name" value={details.name} onChange={(e) => setDetails({ ...details, name: e.target.value })} aria-label="Full name" className="bkra-w-full bkra-rounded-lg bkra-border bkra-border-gray-300 bkra-p-3" />
          <input required type="email" placeholder="Email" value={details.email} onChange={(e) => setDetails({ ...details, email: e.target.value })} aria-label="Email" className="bkra-w-full bkra-rounded-lg bkra-border bkra-border-gray-300 bkra-p-3" />
          <input placeholder="Phone (WhatsApp)" value={details.phone} onChange={(e) => setDetails({ ...details, phone: e.target.value })} aria-label="Phone" className="bkra-w-full bkra-rounded-lg bkra-border bkra-border-gray-300 bkra-p-3" />
          {/* Honeypot: hidden from real users. */}
          <input
            type="text"
            tabIndex={-1}
            autoComplete="off"
            value={details.hp}
            onChange={(e) => setDetails({ ...details, hp: e.target.value })}
            className="bkra-hidden"
            aria-hidden="true"
          />
          <button type="submit" className="bkra-w-full bkra-rounded-lg bkra-bg-bookora-600 bkra-p-3 bkra-font-medium bkra-text-white">
            Continue
          </button>
          <BackButton onClick={() => setStep('time')} />
        </form>
      )}

      {step === 'payment' && service && slot && (
        <section aria-label="Confirm and pay" className="bkra-space-y-3">
          <h2 className="bkra-text-lg bkra-font-semibold">Confirm your booking</h2>
          <dl className="bkra-rounded-lg bkra-bg-gray-50 bkra-p-3 bkra-text-sm">
            <div className="bkra-flex bkra-justify-between"><dt>Service</dt><dd>{service.name}</dd></div>
            <div className="bkra-flex bkra-justify-between"><dt>When</dt><dd>{slot.start_local.slice(0, 16)}</dd></div>
            <div className="bkra-flex bkra-justify-between bkra-font-medium"><dt>Total</dt><dd>{money(service.price, service.currency)}</dd></div>
          </dl>
          {gateways.length > 0 && (
            <div className="bkra-space-y-2">
              <p className="bkra-text-xs bkra-text-gray-500">Pay securely online:</p>
              {gateways.map((g) => (
                <button
                  key={g.key}
                  type="button"
                  onClick={() => void payOnline(g.key)}
                  disabled={loading}
                  className="bkra-w-full bkra-rounded-lg bkra-bg-bookora-600 bkra-p-3 bkra-font-medium bkra-text-white disabled:bkra-opacity-50"
                >
                  Pay with {g.label}
                </button>
              ))}
            </div>
          )}
          <button type="button" onClick={() => void confirmBooking()} disabled={loading} className="bkra-w-full bkra-rounded-lg bkra-border bkra-border-bookora-600 bkra-p-3 bkra-font-medium bkra-text-bookora-700 disabled:bkra-opacity-50">
            {gateways.length > 0 ? 'Pay at the venue' : 'Confirm booking'}
          </button>
          <BackButton onClick={() => setStep('details')} />
        </section>
      )}

      {step === 'done' && confirmation && (
        <section aria-label="Booking confirmed" className="bkra-space-y-3 bkra-text-center">
          <div className="bkra-mx-auto bkra-flex bkra-h-12 bkra-w-12 bkra-items-center bkra-justify-center bkra-rounded-full bkra-bg-bookora-50 bkra-text-2xl bkra-text-bookora-700">✓</div>
          <h2 className="bkra-text-lg bkra-font-semibold">You&apos;re booked!</h2>
          <p className="bkra-text-sm bkra-text-gray-600">
            Booking #{confirmation.appointment.id} — {confirmation.appointment.status}. We&apos;ll be in touch on WhatsApp/email with the details.
          </p>
        </section>
      )}
    </div>
  );
}

function BackButton({ onClick }: { onClick: () => void }) {
  return (
    <button type="button" onClick={onClick} className="bkra-w-full bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-p-2 bkra-text-sm bkra-text-gray-600">
      Back
    </button>
  );
}
