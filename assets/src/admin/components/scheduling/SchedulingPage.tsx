/**
 * AI Scheduling admin: demand forecast, staff workload balance, and a smart
 * slot-suggestion tool backed by the heuristic scorer.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiGet } from '../../api/client';

type ForecastDay = { date: string; weekday: number; predicted: number };
type Forecast = { by_weekday: number[]; next_days: ForecastDay[] };
type WorkloadRow = { staff_id: number; name: string; upcoming: number };
type Suggestion = { start_local: string; staff_id: number; score: number };

const input = 'bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm';

export function SchedulingPage() {
  return (
    <div className="bkra-space-y-8">
      <ForecastPanel />
      <WorkloadPanel />
      <SuggestPanel />
    </div>
  );
}

function ForecastPanel() {
  const [data, setData] = useState<Forecast | null>(null);
  useEffect(() => {
    void apiGet<Forecast>('scheduling/forecast?days=14').then(setData).catch(() => setData(null));
  }, []);
  const max = data ? Math.max(1, ...data.next_days.map((d) => d.predicted)) : 1;

  return (
    <section aria-label="Demand forecast" className="bkra-space-y-3">
      <h2 className="bkra-text-lg bkra-font-semibold bkra-text-gray-800">Demand forecast (next 14 days)</h2>
      {!data && <p className="bkra-text-sm bkra-text-gray-500">Loading…</p>}
      {data && (
        <div className="bkra-flex bkra-items-end bkra-gap-1" style={{ height: 120 }}>
          {data.next_days.map((d) => (
            <div key={d.date} className="bkra-flex bkra-flex-1 bkra-flex-col bkra-items-center bkra-justify-end" title={`${d.date}: ${d.predicted}`}>
              <div className="bkra-w-full bkra-rounded-t bkra-bg-bookora-500" style={{ height: `${(d.predicted / max) * 100}%`, minHeight: 2 }} />
              <span className="bkra-mt-1 bkra-text-[10px] bkra-text-gray-400">{d.date.slice(5)}</span>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

function WorkloadPanel() {
  const [rows, setRows] = useState<WorkloadRow[]>([]);
  useEffect(() => {
    void apiGet<{ items: WorkloadRow[] }>('scheduling/workload').then((r) => setRows(r.items)).catch(() => setRows([]));
  }, []);
  const max = Math.max(1, ...rows.map((r) => r.upcoming));

  return (
    <section aria-label="Staff workload" className="bkra-space-y-3">
      <h2 className="bkra-text-lg bkra-font-semibold bkra-text-gray-800">Staff workload balance</h2>
      {rows.length === 0 && <p className="bkra-text-sm bkra-text-gray-500">No staff yet.</p>}
      <div className="bkra-space-y-2">
        {rows.map((r) => (
          <div key={r.staff_id} className="bkra-flex bkra-items-center bkra-gap-3">
            <span className="bkra-w-40 bkra-truncate bkra-text-sm bkra-text-gray-700">{r.name}</span>
            <div className="bkra-h-3 bkra-flex-1 bkra-rounded bkra-bg-gray-100">
              <div className="bkra-h-3 bkra-rounded bkra-bg-bookora-500" style={{ width: `${(r.upcoming / max) * 100}%` }} />
            </div>
            <span className="bkra-w-8 bkra-text-right bkra-text-sm bkra-tabular-nums bkra-text-gray-600">{r.upcoming}</span>
          </div>
        ))}
      </div>
    </section>
  );
}

function SuggestPanel() {
  const today = new Date().toISOString().slice(0, 10);
  const week = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10);
  const [form, setForm] = useState({ service_id: '', from: today, to: week, prefer: '' });
  const [items, setItems] = useState<Suggestion[] | null>(null);
  const [busy, setBusy] = useState(false);

  const run = useCallback(async () => {
    if (!form.service_id) {
      return;
    }
    setBusy(true);
    try {
      const q = new URLSearchParams({ service_id: form.service_id, from: form.from, to: form.to });
      if (form.prefer) {
        q.set('prefer', form.prefer);
      }
      const r = await apiGet<{ items: Suggestion[] }>(`scheduling/suggestions?${q.toString()}`);
      setItems(r.items);
    } catch {
      setItems([]);
    } finally {
      setBusy(false);
    }
  }, [form]);

  return (
    <section aria-label="Smart slot suggestions" className="bkra-space-y-3">
      <h2 className="bkra-text-lg bkra-font-semibold bkra-text-gray-800">Smart slot suggestions</h2>
      <div className="bkra-flex bkra-flex-wrap bkra-items-end bkra-gap-2">
        <input className={input} type="number" aria-label="Service ID" placeholder="Service ID" value={form.service_id} onChange={(e) => setForm({ ...form, service_id: e.target.value })} />
        <input className={input} type="date" aria-label="From" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} />
        <input className={input} type="date" aria-label="To" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} />
        <select className={input} aria-label="Preferred time" value={form.prefer} onChange={(e) => setForm({ ...form, prefer: e.target.value })}>
          <option value="">Any time</option>
          <option value="morning">Morning</option>
          <option value="afternoon">Afternoon</option>
          <option value="evening">Evening</option>
        </select>
        <button type="button" onClick={run} disabled={busy || !form.service_id} className="bkra-rounded bkra-bg-bookora-600 bkra-px-3 bkra-py-1 bkra-text-sm bkra-text-white disabled:bkra-opacity-50">
          {busy ? 'Scoring…' : 'Suggest'}
        </button>
      </div>
      {items && items.length === 0 && <p className="bkra-text-sm bkra-text-gray-500">No available slots in that range.</p>}
      {items && items.length > 0 && (
        <ol className="bkra-space-y-1">
          {items.map((s, i) => (
            <li key={`${s.start_local}-${s.staff_id}`} className="bkra-flex bkra-items-center bkra-gap-3 bkra-rounded bkra-border bkra-border-gray-200 bkra-p-2 bkra-text-sm">
              <span className="bkra-flex bkra-h-6 bkra-w-6 bkra-items-center bkra-justify-center bkra-rounded-full bkra-bg-bookora-100 bkra-text-xs bkra-font-medium bkra-text-bookora-700">{i + 1}</span>
              <span className="bkra-font-medium bkra-text-gray-800">{s.start_local}</span>
              <span className="bkra-text-gray-500">Staff #{s.staff_id}</span>
              <span className="bkra-ml-auto bkra-tabular-nums bkra-text-gray-400">score {s.score}</span>
            </li>
          ))}
        </ol>
      )}
    </section>
  );
}
