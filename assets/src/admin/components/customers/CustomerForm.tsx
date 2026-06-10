/**
 * Create / edit a customer, with notes, booking history, and activity timeline
 * for existing records.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiDelete, apiGet, apiPatch, apiPost, ApiError } from '../../api/client';
import type { Customer, CustomerBooking, CustomerNote, TimelineEvent } from '../../types';

interface Props {
  customer: Customer | null;
  onSaved: () => void;
  onCancel: () => void;
}

export function CustomerForm({ customer, onSaved, onCancel }: Props) {
  const [name, setName] = useState(customer?.name ?? '');
  const [email, setEmail] = useState(customer?.email ?? '');
  const [phone, setPhone] = useState(customer?.phone ?? '');
  const [tags, setTags] = useState((customer?.tags ?? []).join(', '));

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  const [notes, setNotes] = useState<CustomerNote[]>([]);
  const [bookings, setBookings] = useState<CustomerBooking[]>([]);
  const [timeline, setTimeline] = useState<TimelineEvent[]>([]);
  const [newNote, setNewNote] = useState('');

  const id = customer?.id;

  const loadRelations = useCallback(async () => {
    if (!id) {
      return;
    }
    const [notesRes, bookingsRes, timelineRes] = await Promise.all([
      apiGet<{ items: CustomerNote[] }>(`customers/${id}/notes`),
      apiGet<{ items: CustomerBooking[] }>(`customers/${id}/bookings`),
      apiGet<{ items: TimelineEvent[] }>(`customers/${id}/timeline`),
    ]);
    setNotes(notesRes.items);
    setBookings(bookingsRes.items);
    setTimeline(timelineRes.items);
  }, [id]);

  useEffect(() => {
    void loadRelations();
  }, [loadRelations]);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});
    setFormError('');
    const payload = { name, email, phone, tags };
    try {
      if (customer) {
        await apiPatch<Customer>(`customers/${customer.id}`, payload);
      } else {
        await apiPost<Customer>('customers', payload);
      }
      onSaved();
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.fields);
        setFormError(error.message);
      } else {
        setFormError('Something went wrong.');
      }
    } finally {
      setSaving(false);
    }
  };

  const addNote = async () => {
    if (!id || newNote.trim() === '') {
      return;
    }
    await apiPost(`customers/${id}/notes`, { body: newNote });
    setNewNote('');
    void loadRelations();
  };

  const removeNote = async (noteId: number) => {
    if (!id) {
      return;
    }
    await apiDelete(`customers/${id}/notes/${noteId}`);
    void loadRelations();
  };

  return (
    <div className="bkra-space-y-6">
      <form onSubmit={submit} className="bkra-space-y-4" aria-label={customer ? 'Edit customer' : 'Add customer'}>
        <h2 className="bkra-text-lg bkra-font-semibold">{customer ? 'Edit customer' : 'Add customer'}</h2>

        {formError && (
          <div role="alert" className="bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">
            {formError}
          </div>
        )}

        <div className="bkra-grid bkra-grid-cols-2 bkra-gap-4">
          <label className="bkra-block">
            <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Name</span>
            <input value={name ?? ''} onChange={(e) => setName(e.target.value)} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm" />
            {errors.name && <span className="bkra-text-xs bkra-text-red-600">{errors.name}</span>}
          </label>
          <label className="bkra-block">
            <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Email</span>
            <input type="email" value={email ?? ''} onChange={(e) => setEmail(e.target.value)} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm" />
            {errors.email && <span className="bkra-text-xs bkra-text-red-600">{errors.email}</span>}
          </label>
          <label className="bkra-block">
            <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Phone</span>
            <input value={phone ?? ''} onChange={(e) => setPhone(e.target.value)} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm" />
          </label>
          <label className="bkra-block">
            <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Tags (comma separated)</span>
            <input value={tags} onChange={(e) => setTags(e.target.value)} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm" />
          </label>
        </div>

        <div className="bkra-flex bkra-justify-end bkra-gap-2">
          <button type="button" onClick={onCancel} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-4 bkra-py-2 bkra-text-sm">
            Cancel
          </button>
          <button type="submit" disabled={saving} className="bkra-rounded bkra-bg-bookora-600 bkra-px-4 bkra-py-2 bkra-text-sm bkra-font-medium bkra-text-white disabled:bkra-opacity-50">
            {saving ? 'Saving…' : 'Save customer'}
          </button>
        </div>
      </form>

      {customer && (
        <div className="bkra-grid bkra-gap-6 md:bkra-grid-cols-2">
          <section aria-label="Notes" className="bkra-space-y-2">
            <h3 className="bkra-text-sm bkra-font-semibold">Notes</h3>
            <div className="bkra-flex bkra-gap-2">
              <input
                value={newNote}
                onChange={(e) => setNewNote(e.target.value)}
                placeholder="Add a note…"
                aria-label="New note"
                className="bkra-flex-1 bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
              />
              <button type="button" onClick={addNote} className="bkra-rounded bkra-bg-bookora-600 bkra-px-3 bkra-py-2 bkra-text-sm bkra-text-white">
                Add
              </button>
            </div>
            <ul className="bkra-space-y-1">
              {notes.length === 0 && <li className="bkra-text-xs bkra-text-gray-500">No notes yet.</li>}
              {notes.map((note) => (
                <li key={note.id} className="bkra-flex bkra-items-start bkra-justify-between bkra-gap-2 bkra-rounded bkra-bg-gray-50 bkra-p-2 bkra-text-sm">
                  <span>{note.body}</span>
                  <button type="button" onClick={() => removeNote(note.id)} className="bkra-text-red-700" aria-label="Delete note">
                    ✕
                  </button>
                </li>
              ))}
            </ul>
          </section>

          <section aria-label="Booking history" className="bkra-space-y-2">
            <h3 className="bkra-text-sm bkra-font-semibold">Booking history</h3>
            {customer.stats && (
              <p className="bkra-text-xs bkra-text-gray-500">
                {customer.stats.count} bookings · last {customer.stats.last_at ?? '—'}
              </p>
            )}
            <ul className="bkra-space-y-1">
              {bookings.length === 0 && <li className="bkra-text-xs bkra-text-gray-500">No bookings yet.</li>}
              {bookings.map((booking) => (
                <li key={booking.id} className="bkra-rounded bkra-bg-gray-50 bkra-p-2 bkra-text-sm">
                  <span className="bkra-font-medium">{booking.service_name ?? 'Service'}</span> — {booking.start_at} ({booking.status})
                </li>
              ))}
            </ul>
          </section>

          <section aria-label="Activity timeline" className="bkra-space-y-2 md:bkra-col-span-2">
            <h3 className="bkra-text-sm bkra-font-semibold">Activity timeline</h3>
            <ul className="bkra-space-y-1">
              {timeline.length === 0 && <li className="bkra-text-xs bkra-text-gray-500">No activity yet.</li>}
              {timeline.map((event, index) => (
                <li key={index} className="bkra-flex bkra-gap-2 bkra-text-sm">
                  <span className="bkra-w-40 bkra-shrink-0 bkra-text-xs bkra-text-gray-500">{event.at}</span>
                  <span className="bkra-rounded bkra-bg-gray-100 bkra-px-1 bkra-text-xs">{event.type}</span>
                  <span>{event.summary}</span>
                </li>
              ))}
            </ul>
          </section>
        </div>
      )}
    </div>
  );
}
