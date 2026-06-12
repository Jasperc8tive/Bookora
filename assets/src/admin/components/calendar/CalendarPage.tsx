/**
 * Admin calendar: month / week / day / agenda views with drag-to-reschedule
 * and resize-to-change-duration, backed by the booking engine.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import type { EventDropArg } from '@fullcalendar/core';
import type { EventResizeDoneArg } from '@fullcalendar/interaction';
import { apiGet, apiPatch } from '../../api/client';
import type { Paginated, StaffMember } from '../../types';

interface CalendarEvent {
  id: string;
  title: string;
  start: string;
  end: string;
  backgroundColor: string;
  borderColor: string;
  extendedProps: Record<string, unknown>;
}

const STATUSES = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];

/** Format a Date as a naive site-local "Y-m-d H:i:s" string. */
function fmtLocal(date: Date | null): string {
  if (!date) {
    return '';
  }
  const p = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())} ${p(date.getHours())}:${p(date.getMinutes())}:${p(date.getSeconds())}`;
}

export function CalendarPage() {
  const calendarRef = useRef<FullCalendar>(null);
  const filters = useRef({ staff: '', status: '' });
  const [staff, setStaff] = useState<StaffMember[]>([]);
  const [staffFilter, setStaffFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    void apiGet<Paginated<StaffMember>>('staff', { per_page: 100 })
      .then((r) => setStaff(r.items))
      .catch(() => undefined);
  }, []);

  const loadEvents = useCallback(
    (
      info: { startStr: string; endStr: string },
      success: (events: CalendarEvent[]) => void,
      failure: (error: Error) => void,
    ) => {
      apiGet<{ events: CalendarEvent[] }>('bookings/calendar', {
        from: info.startStr.slice(0, 10),
        to: info.endStr.slice(0, 10),
        staff_id: filters.current.staff,
        status: filters.current.status,
      })
        .then((r) => success(r.events))
        .catch(() => failure(new Error('Could not load bookings.')));
    },
    [],
  );

  const refetch = () => calendarRef.current?.getApi().refetchEvents();

  const onStaffFilter = (value: string) => {
    setStaffFilter(value);
    filters.current.staff = value;
    refetch();
  };
  const onStatusFilter = (value: string) => {
    setStatusFilter(value);
    filters.current.status = value;
    refetch();
  };

  const onChange = async (arg: EventDropArg | EventResizeDoneArg) => {
    setError('');
    try {
      await apiPatch(`bookings/${arg.event.id}`, {
        start: fmtLocal(arg.event.start),
        end: fmtLocal(arg.event.end),
      });
    } catch {
      arg.revert();
      setError('That change conflicts with another booking and was reverted.');
    }
  };

  return (
    <section aria-label="Calendar" className="bkra-space-y-3">
      <div className="bkra-flex bkra-flex-wrap bkra-items-center bkra-gap-2">
        <select value={staffFilter} onChange={(e) => onStaffFilter(e.target.value)} aria-label="Filter by staff" className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm">
          <option value="">All staff</option>
          {staff.map((s) => (
            <option key={s.id} value={s.id}>
              {s.display_name}
            </option>
          ))}
        </select>
        <select value={statusFilter} onChange={(e) => onStatusFilter(e.target.value)} aria-label="Filter by status" className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm">
          <option value="">All statuses</option>
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
      </div>

      {error && <div role="alert" className="bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">{error}</div>}

      <FullCalendar
        ref={calendarRef}
        plugins={[dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin]}
        initialView="timeGridWeek"
        timeZone="local"
        height="auto"
        nowIndicator
        editable
        eventStartEditable
        eventDurationEditable
        headerToolbar={{
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        }}
        events={loadEvents}
        eventDrop={(arg) => void onChange(arg)}
        eventResize={(arg) => void onChange(arg)}
      />
    </section>
  );
}
