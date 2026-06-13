/**
 * Analytics dashboard: KPIs, revenue trend, status/staff/service breakdowns,
 * and utilisation — with a CSV export.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiGet } from '../../api/client';
import type { ReportOverview, UtilizationRow } from '../../types';

function isoDaysAgo(days: number): string {
  return new Date(Date.now() - days * 86400000).toISOString().slice(0, 10);
}

function Kpi({ label, value }: { label: string; value: string }) {
  return (
    <div className="bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-bg-white bkra-p-3">
      <div className="bkra-text-xs bkra-text-gray-500">{label}</div>
      <div className="bkra-text-xl bkra-font-semibold">{value}</div>
    </div>
  );
}

export function ReportsPage() {
  const [from, setFrom] = useState(isoDaysAgo(30));
  const [to, setTo] = useState(isoDaysAgo(0));
  const [data, setData] = useState<ReportOverview | null>(null);
  const [util, setUtil] = useState<UtilizationRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [overview, utilization] = await Promise.all([
        apiGet<ReportOverview>('reports/overview', { from, to }),
        apiGet<{ items: UtilizationRow[] }>('reports/utilization', { from, to }),
      ]);
      setData(overview);
      setUtil(utilization.items);
    } catch {
      setError('Could not load reports.');
    } finally {
      setLoading(false);
    }
  }, [from, to]);

  useEffect(() => {
    void load();
  }, [load]);

  const exportCsv = async (type: string) => {
    const { csv } = await apiGet<{ csv: string }>('reports/export', { from, to, type });
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `bookora-${type}-${from}_${to}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const maxRevenue = data ? Math.max(1, ...data.revenue_by_day.map((d) => d.revenue)) : 1;

  return (
    <section aria-label="Reports" className="bkra-space-y-5">
      <div className="bkra-flex bkra-flex-wrap bkra-items-end bkra-gap-2">
        <label className="bkra-text-sm">
          <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-text-gray-600">From</span>
          <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} aria-label="From date" className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1" />
        </label>
        <label className="bkra-text-sm">
          <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-text-gray-600">To</span>
          <input type="date" value={to} onChange={(e) => setTo(e.target.value)} aria-label="To date" className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1" />
        </label>
        <button type="button" onClick={() => void exportCsv('revenue')} className="bkra-ml-auto bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-1 bkra-text-sm">Export revenue CSV</button>
        <button type="button" onClick={() => void exportCsv('staff')} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-1 bkra-text-sm">Export staff CSV</button>
      </div>

      {error && <div role="alert" className="bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">{error}</div>}
      {loading && <p className="bkra-text-sm bkra-text-gray-500">Loading…</p>}

      {data && (
        <>
          <div className="bkra-grid bkra-grid-cols-2 bkra-gap-3 md:bkra-grid-cols-4">
            <Kpi label="Revenue" value={String(data.kpis.revenue)} />
            <Kpi label="Collected" value={String(data.kpis.collected)} />
            <Kpi label="Bookings" value={String(data.kpis.total_bookings)} />
            <Kpi label="Avg value" value={String(data.kpis.avg_value)} />
            <Kpi label="Completed" value={String(data.kpis.completed)} />
            <Kpi label="Cancelled" value={`${data.kpis.cancelled} (${data.kpis.cancellation_rate}%)`} />
            <Kpi label="No-shows" value={`${data.kpis.no_show} (${data.kpis.no_show_rate}%)`} />
            <Kpi label="Days" value={`${from} → ${to}`} />
          </div>

          <section aria-label="Revenue by day" className="bkra-space-y-1">
            <h3 className="bkra-text-sm bkra-font-semibold">Revenue by day</h3>
            {data.revenue_by_day.length === 0 && <p className="bkra-text-sm bkra-text-gray-500">No data in range.</p>}
            {data.revenue_by_day.map((d) => (
              <div key={d.date} className="bkra-flex bkra-items-center bkra-gap-2 bkra-text-xs">
                <span className="bkra-w-24 bkra-text-gray-500">{d.date}</span>
                <span className="bkra-h-3 bkra-rounded bkra-bg-bookora-600" style={{ width: `${(d.revenue / maxRevenue) * 100}%`, minWidth: '2px' }} />
                <span>{d.revenue}</span>
              </div>
            ))}
          </section>

          <div className="bkra-grid bkra-gap-4 md:bkra-grid-cols-2">
            <BreakdownTable title="By staff" rows={data.by_staff.map((r) => [r.name || `#${r.staff_id}`, r.bookings, r.revenue])} />
            <BreakdownTable title="By service" rows={data.by_service.map((r) => [r.name || `#${r.service_id}`, r.bookings, r.revenue])} />
          </div>

          <section aria-label="Utilisation" className="bkra-space-y-1">
            <h3 className="bkra-text-sm bkra-font-semibold">Staff utilisation</h3>
            <table className="bkra-w-full bkra-text-sm">
              <thead><tr className="bkra-border-b bkra-text-left bkra-text-gray-500"><th className="bkra-p-2">Staff</th><th className="bkra-p-2">Booked (min)</th><th className="bkra-p-2">Available (min)</th><th className="bkra-p-2">Utilisation</th></tr></thead>
              <tbody>
                {util.length === 0 && <tr><td colSpan={4} className="bkra-p-3 bkra-text-center bkra-text-gray-500">No data.</td></tr>}
                {util.map((u) => (
                  <tr key={u.staff_id} className="bkra-border-b">
                    <td className="bkra-p-2">{u.name || `#${u.staff_id}`}</td>
                    <td className="bkra-p-2">{u.booked_minutes}</td>
                    <td className="bkra-p-2">{u.available_minutes}</td>
                    <td className="bkra-p-2">{u.utilization}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>
        </>
      )}
    </section>
  );
}

function BreakdownTable({ title, rows }: { title: string; rows: (string | number)[][] }) {
  return (
    <section aria-label={title} className="bkra-space-y-1">
      <h3 className="bkra-text-sm bkra-font-semibold">{title}</h3>
      <table className="bkra-w-full bkra-text-sm">
        <thead><tr className="bkra-border-b bkra-text-left bkra-text-gray-500"><th className="bkra-p-2">Name</th><th className="bkra-p-2">Bookings</th><th className="bkra-p-2">Revenue</th></tr></thead>
        <tbody>
          {rows.length === 0 && <tr><td colSpan={3} className="bkra-p-3 bkra-text-center bkra-text-gray-500">No data.</td></tr>}
          {rows.map((r, i) => (
            <tr key={i} className="bkra-border-b">
              <td className="bkra-p-2">{r[0]}</td>
              <td className="bkra-p-2">{r[1]}</td>
              <td className="bkra-p-2">{r[2]}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}
