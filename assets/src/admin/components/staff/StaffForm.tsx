/**
 * Create / edit form for a staff member, including assigned services, skills,
 * weekly working hours, and time off / holidays.
 */
import { useEffect, useState } from 'react';
import { apiGet, apiPatch, apiPost, ApiError } from '../../api/client';
import type { Availability, Service, StaffMember } from '../../types';

interface Props {
  staff: StaffMember | null;
  services: Service[];
  onSaved: () => void;
  onCancel: () => void;
}

interface DayRow {
  weekday: number;
  enabled: boolean;
  start: string;
  end: string;
}

interface DateRow {
  type: 'time_off' | 'holiday';
  start_date: string;
  end_date: string;
  note: string;
}

const DAY_LABELS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function defaultDays(): DayRow[] {
  return DAY_LABELS.map((_, weekday) => ({ weekday, enabled: false, start: '09:00', end: '17:00' }));
}

export function StaffForm({ staff, services, onSaved, onCancel }: Props) {
  const [name, setName] = useState(staff?.display_name ?? '');
  const [email, setEmail] = useState(staff?.email ?? '');
  const [phone, setPhone] = useState(staff?.phone ?? '');
  const [status, setStatus] = useState(staff?.status ?? 'active');
  const [skills, setSkills] = useState((staff?.skills ?? []).join(', '));
  const [serviceIds, setServiceIds] = useState<number[]>(staff?.service_ids ?? []);
  const [days, setDays] = useState<DayRow[]>(defaultDays);
  const [dateRows, setDateRows] = useState<DateRow[]>([]);

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!staff) {
      return;
    }
    void apiGet<Availability>(`staff/${staff.id}/availability`).then((availability) => {
      setDays((current) =>
        current.map((day) => {
          const match = availability.working_hours.find((w) => w.weekday === day.weekday);
          return match
            ? { ...day, enabled: true, start: match.start_time.slice(0, 5), end: match.end_time.slice(0, 5) }
            : day;
        }),
      );
      const ranges: DateRow[] = [
        ...availability.time_off.map((t) => ({ type: 'time_off' as const, start_date: t.start_date, end_date: t.end_date, note: t.note ?? '' })),
        ...availability.holidays.map((h) => ({ type: 'holiday' as const, start_date: h.start_date, end_date: h.end_date, note: h.note ?? '' })),
      ];
      setDateRows(ranges);
    });
  }, [staff]);

  const toggleService = (id: number) =>
    setServiceIds((ids) => (ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]));

  const setDay = (weekday: number, patch: Partial<DayRow>) =>
    setDays((current) => current.map((d) => (d.weekday === weekday ? { ...d, ...patch } : d)));

  const addDateRow = () =>
    setDateRows((rows) => [...rows, { type: 'time_off', start_date: '', end_date: '', note: '' }]);

  const setDateRow = (index: number, patch: Partial<DateRow>) =>
    setDateRows((rows) => rows.map((r, i) => (i === index ? { ...r, ...patch } : r)));

  const removeDateRow = (index: number) => setDateRows((rows) => rows.filter((_, i) => i !== index));

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});
    setFormError('');

    const profile = {
      display_name: name,
      email,
      phone,
      status,
      skills,
      service_ids: serviceIds,
    };

    try {
      const saved = staff
        ? await apiPatch<StaffMember>(`staff/${staff.id}`, profile)
        : await apiPost<StaffMember>('staff', profile);

      const availability = {
        working_hours: days.filter((d) => d.enabled).map((d) => ({ weekday: d.weekday, start_time: d.start, end_time: d.end })),
        time_off: dateRows.filter((r) => r.type === 'time_off').map((r) => ({ start_date: r.start_date, end_date: r.end_date, note: r.note })),
        holidays: dateRows.filter((r) => r.type === 'holiday').map((r) => ({ start_date: r.start_date, end_date: r.end_date, note: r.note })),
      };
      await apiPatch(`staff/${saved.id}/availability`, availability);

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

  return (
    <form onSubmit={submit} className="bkra-space-y-6" aria-label={staff ? 'Edit staff' : 'Add staff'}>
      <h2 className="bkra-text-lg bkra-font-semibold">{staff ? 'Edit staff member' : 'Add staff member'}</h2>

      {formError && (
        <div role="alert" className="bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">
          {formError}
        </div>
      )}

      <fieldset className="bkra-space-y-4">
        <legend className="bkra-text-sm bkra-font-semibold">Profile</legend>
        <label className="bkra-block">
          <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Display name</span>
          <input value={name} onChange={(e) => setName(e.target.value)} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm" />
          {errors.display_name && <span className="bkra-text-xs bkra-text-red-600">{errors.display_name}</span>}
        </label>
        <div className="bkra-grid bkra-grid-cols-3 bkra-gap-4">
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
            <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Status</span>
            <select value={status} onChange={(e) => setStatus(e.target.value)} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </label>
        </div>
        <label className="bkra-block">
          <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Skills (comma separated)</span>
          <input value={skills} onChange={(e) => setSkills(e.target.value)} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm" />
        </label>
      </fieldset>

      <fieldset>
        <legend className="bkra-text-sm bkra-font-semibold">Assigned services</legend>
        <div className="bkra-mt-2 bkra-grid bkra-grid-cols-2 bkra-gap-1 md:bkra-grid-cols-3">
          {services.length === 0 && <p className="bkra-text-xs bkra-text-gray-500">No services yet.</p>}
          {services.map((service) => (
            <label key={service.id} className="bkra-flex bkra-items-center bkra-gap-2 bkra-text-sm">
              <input type="checkbox" checked={serviceIds.includes(service.id)} onChange={() => toggleService(service.id)} />
              {service.name}
            </label>
          ))}
        </div>
      </fieldset>

      <fieldset>
        <legend className="bkra-text-sm bkra-font-semibold">Working hours</legend>
        <div className="bkra-mt-2 bkra-space-y-1">
          {days.map((day) => (
            <div key={day.weekday} className="bkra-flex bkra-items-center bkra-gap-3 bkra-text-sm">
              <label className="bkra-flex bkra-w-32 bkra-items-center bkra-gap-2">
                <input type="checkbox" checked={day.enabled} onChange={(e) => setDay(day.weekday, { enabled: e.target.checked })} />
                {DAY_LABELS[day.weekday]}
              </label>
              <input type="time" value={day.start} disabled={!day.enabled} onChange={(e) => setDay(day.weekday, { start: e.target.value })} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 disabled:bkra-opacity-40" aria-label={`${DAY_LABELS[day.weekday]} start`} />
              <span>–</span>
              <input type="time" value={day.end} disabled={!day.enabled} onChange={(e) => setDay(day.weekday, { end: e.target.value })} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 disabled:bkra-opacity-40" aria-label={`${DAY_LABELS[day.weekday]} end`} />
            </div>
          ))}
        </div>
      </fieldset>

      <fieldset>
        <legend className="bkra-text-sm bkra-font-semibold">Time off &amp; holidays</legend>
        <div className="bkra-mt-2 bkra-space-y-2">
          {dateRows.map((row, index) => (
            <div key={index} className="bkra-flex bkra-flex-wrap bkra-items-center bkra-gap-2 bkra-text-sm">
              <select value={row.type} onChange={(e) => setDateRow(index, { type: e.target.value as DateRow['type'] })} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1" aria-label="Type">
                <option value="time_off">Time off</option>
                <option value="holiday">Holiday</option>
              </select>
              <input type="date" value={row.start_date} onChange={(e) => setDateRow(index, { start_date: e.target.value })} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1" aria-label="Start date" />
              <span>–</span>
              <input type="date" value={row.end_date} onChange={(e) => setDateRow(index, { end_date: e.target.value })} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1" aria-label="End date" />
              <input type="text" placeholder="Note" value={row.note} onChange={(e) => setDateRow(index, { note: e.target.value })} className="bkra-flex-1 bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1" />
              <button type="button" onClick={() => removeDateRow(index)} className="bkra-text-red-700">
                Remove
              </button>
            </div>
          ))}
          <button type="button" onClick={addDateRow} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-1 bkra-text-sm">
            + Add time off / holiday
          </button>
        </div>
      </fieldset>

      <div className="bkra-flex bkra-justify-end bkra-gap-2">
        <button type="button" onClick={onCancel} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-4 bkra-py-2 bkra-text-sm">
          Cancel
        </button>
        <button type="submit" disabled={saving} className="bkra-rounded bkra-bg-bookora-600 bkra-px-4 bkra-py-2 bkra-text-sm bkra-font-medium bkra-text-white disabled:bkra-opacity-50">
          {saving ? 'Saving…' : 'Save staff'}
        </button>
      </div>
    </form>
  );
}
