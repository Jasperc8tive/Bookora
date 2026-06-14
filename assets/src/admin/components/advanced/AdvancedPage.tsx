/**
 * Advanced features admin: coupons, gift cards, memberships, resources, waitlist.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiDelete, apiGet, apiPost } from '../../api/client';

type Row = Record<string, unknown>;
type Tab = 'coupons' | 'gift-cards' | 'memberships' | 'resources' | 'waitlist';

const TABS: { id: Tab; label: string }[] = [
  { id: 'coupons', label: 'Coupons' },
  { id: 'gift-cards', label: 'Gift cards' },
  { id: 'memberships', label: 'Memberships' },
  { id: 'resources', label: 'Resources' },
  { id: 'waitlist', label: 'Waitlist' },
];

function useList(path: string) {
  const [rows, setRows] = useState<Row[]>([]);
  const reload = useCallback(() => {
    void apiGet<{ items: Row[] }>(path).then((r) => setRows(r.items)).catch(() => setRows([]));
  }, [path]);
  useEffect(reload, [reload]);
  return { rows, reload };
}

const input = 'bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm';

export function AdvancedPage() {
  const [tab, setTab] = useState<Tab>('coupons');

  return (
    <div className="bkra-space-y-4">
      <div className="bkra-flex bkra-flex-wrap bkra-gap-1 bkra-border-b">
        {TABS.map((t) => (
          <button key={t.id} type="button" onClick={() => setTab(t.id)} className={`bkra-px-3 bkra-py-2 bkra-text-sm ${tab === t.id ? 'bkra-border-b-2 bkra-border-bookora-600 bkra-font-medium' : 'bkra-text-gray-500'}`}>
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'coupons' && <Coupons />}
      {tab === 'gift-cards' && <GiftCards />}
      {tab === 'memberships' && <Memberships />}
      {tab === 'resources' && <Resources />}
      {tab === 'waitlist' && <Waitlist />}
    </div>
  );
}

function Coupons() {
  const { rows, reload } = useList('coupons');
  const [form, setForm] = useState({ code: '', type: 'percent', value: '10', min_amount: '0', usage_limit: '0', expires_at: '' });
  const create = async () => {
    await apiPost('coupons', { ...form, value: Number(form.value), min_amount: Number(form.min_amount), usage_limit: Number(form.usage_limit) });
    setForm({ ...form, code: '' });
    reload();
  };
  return (
    <section aria-label="Coupons" className="bkra-space-y-3">
      <div className="bkra-flex bkra-flex-wrap bkra-gap-2">
        <input className={input} placeholder="CODE" aria-label="Coupon code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
        <select className={input} aria-label="Type" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}><option value="percent">% off</option><option value="fixed">Fixed</option></select>
        <input className={input} type="number" aria-label="Value" value={form.value} onChange={(e) => setForm({ ...form, value: e.target.value })} />
        <input className={input} type="number" aria-label="Min amount" placeholder="Min" value={form.min_amount} onChange={(e) => setForm({ ...form, min_amount: e.target.value })} />
        <input className={input} type="number" aria-label="Usage limit" placeholder="Limit (0=∞)" value={form.usage_limit} onChange={(e) => setForm({ ...form, usage_limit: e.target.value })} />
        <input className={input} type="date" aria-label="Expires" value={form.expires_at} onChange={(e) => setForm({ ...form, expires_at: e.target.value })} />
        <button type="button" onClick={create} className="bkra-rounded bkra-bg-bookora-600 bkra-px-3 bkra-text-sm bkra-text-white">Add</button>
      </div>
      <Table head={['Code', 'Type', 'Value', 'Used', 'Status', '']} rows={rows} render={(r) => [r.code, r.type, r.value, `${r.used}/${r.usage_limit}`, r.status, <Del key="d" path="coupons" id={Number(r.id)} reload={reload} />]} />
    </section>
  );
}

function GiftCards() {
  const { rows, reload } = useList('gift-cards');
  const [form, setForm] = useState({ amount: '50', currency: 'NGN' });
  const create = async () => {
    await apiPost('gift-cards', { amount: Number(form.amount), currency: form.currency });
    reload();
  };
  return (
    <section aria-label="Gift cards" className="bkra-space-y-3">
      <div className="bkra-flex bkra-flex-wrap bkra-gap-2">
        <input className={input} type="number" aria-label="Amount" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
        <input className={input} aria-label="Currency" value={form.currency} onChange={(e) => setForm({ ...form, currency: e.target.value })} />
        <button type="button" onClick={create} className="bkra-rounded bkra-bg-bookora-600 bkra-px-3 bkra-text-sm bkra-text-white">Issue</button>
      </div>
      <Table head={['Code', 'Balance', 'Currency', 'Status', '']} rows={rows} render={(r) => [r.code, r.balance, r.currency, r.status, <Del key="d" path="gift-cards" id={Number(r.id)} reload={reload} />]} />
    </section>
  );
}

function Memberships() {
  const { rows, reload } = useList('memberships');
  const [form, setForm] = useState({ name: '', price: '0', billing_period: 'month', benefit_type: 'discount_percent', benefit_value: '10' });
  const create = async () => {
    await apiPost('memberships', { ...form, price: Number(form.price), benefit_value: Number(form.benefit_value) });
    setForm({ ...form, name: '' });
    reload();
  };
  return (
    <section aria-label="Memberships" className="bkra-space-y-3">
      <div className="bkra-flex bkra-flex-wrap bkra-gap-2">
        <input className={input} placeholder="Plan name" aria-label="Plan name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        <input className={input} type="number" aria-label="Price" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} />
        <select className={input} aria-label="Billing period" value={form.billing_period} onChange={(e) => setForm({ ...form, billing_period: e.target.value })}><option value="month">Monthly</option><option value="year">Yearly</option><option value="none">One-off</option></select>
        <input className={input} type="number" aria-label="Discount percent" placeholder="% off" value={form.benefit_value} onChange={(e) => setForm({ ...form, benefit_value: e.target.value })} />
        <button type="button" onClick={create} className="bkra-rounded bkra-bg-bookora-600 bkra-px-3 bkra-text-sm bkra-text-white">Add</button>
      </div>
      <Table head={['Name', 'Price', 'Period', 'Benefit', '']} rows={rows} render={(r) => [r.name, r.price, r.billing_period, `${r.benefit_value}%`, <Del key="d" path="memberships" id={Number(r.id)} reload={reload} />]} />
    </section>
  );
}

function Resources() {
  const { rows, reload } = useList('resources');
  const [form, setForm] = useState({ name: '', type: 'room', capacity: '1' });
  const create = async () => {
    await apiPost('resources', { ...form, capacity: Number(form.capacity) });
    setForm({ ...form, name: '' });
    reload();
  };
  return (
    <section aria-label="Resources" className="bkra-space-y-3">
      <div className="bkra-flex bkra-flex-wrap bkra-gap-2">
        <input className={input} placeholder="Resource name" aria-label="Resource name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        <input className={input} placeholder="Type" aria-label="Type" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} />
        <input className={input} type="number" aria-label="Capacity" value={form.capacity} onChange={(e) => setForm({ ...form, capacity: e.target.value })} />
        <button type="button" onClick={create} className="bkra-rounded bkra-bg-bookora-600 bkra-px-3 bkra-text-sm bkra-text-white">Add</button>
      </div>
      <Table head={['Name', 'Type', 'Capacity', 'Status', '']} rows={rows} render={(r) => [r.name, r.type, r.capacity, r.status, <Del key="d" path="resources" id={Number(r.id)} reload={reload} />]} />
    </section>
  );
}

function Waitlist() {
  const { rows, reload } = useList('waitlist');
  return (
    <section aria-label="Waitlist">
      <Table head={['Customer', 'Service', 'Status', '']} rows={rows} render={(r) => [r.customer_name, r.service_name, r.status, <Del key="d" path="waitlist" id={Number(r.id)} reload={reload} />]} />
    </section>
  );
}

function Table({ head, rows, render }: { head: string[]; rows: Row[]; render: (r: Row) => unknown[] }) {
  return (
    <table className="bkra-w-full bkra-text-sm">
      <thead><tr className="bkra-border-b bkra-text-left bkra-text-gray-500">{head.map((h, i) => <th key={i} className="bkra-p-2">{h}</th>)}</tr></thead>
      <tbody>
        {rows.length === 0 && <tr><td colSpan={head.length} className="bkra-p-3 bkra-text-center bkra-text-gray-500">Nothing yet.</td></tr>}
        {rows.map((r, ri) => (
          <tr key={ri} className="bkra-border-b">
            {render(r).map((cell, ci) => <td key={ci} className="bkra-p-2">{cell as React.ReactNode}</td>)}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function Del({ path, id, reload }: { path: string; id: number; reload: () => void }) {
  return (
    <button
      type="button"
      className="bkra-text-red-700"
      onClick={async () => {
        await apiDelete(`${path}/${id}`);
        reload();
      }}
    >
      Delete
    </button>
  );
}
