/**
 * Payments admin: gateway settings + recent payments with refunds.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPatch, apiPost } from '../../api/client';
import type { Payment, PaymentSettingsView } from '../../types';

const GATEWAYS: { key: string; label: string; webhookLabel: string }[] = [
  { key: 'paystack', label: 'Paystack', webhookLabel: 'Webhook (auto)' },
  { key: 'flutterwave', label: 'Flutterwave', webhookLabel: 'Secret hash' },
  { key: 'stripe', label: 'Stripe', webhookLabel: 'Webhook signing secret' },
];

interface DraftGateway {
  enabled: boolean;
  public_key: string;
  secret_key: string;
  webhook_secret: string;
  secret_hash: string;
}

function emptyDraft(): DraftGateway {
  return { enabled: false, public_key: '', secret_key: '', webhook_secret: '', secret_hash: '' };
}

export function PaymentsPage() {
  const [settings, setSettings] = useState<PaymentSettingsView>({});
  const [draft, setDraft] = useState<Record<string, DraftGateway>>({});
  const [payments, setPayments] = useState<Payment[]>([]);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');

  const loadPayments = useCallback(async () => {
    try {
      setPayments((await apiGet<{ items: Payment[] }>('payments')).items);
    } catch {
      setError('Could not load payments.');
    }
  }, []);

  useEffect(() => {
    void apiGet<{ payments: PaymentSettingsView }>('payments/settings')
      .then((r) => {
        setSettings(r.payments);
        const next: Record<string, DraftGateway> = {};
        GATEWAYS.forEach((g) => {
          next[g.key] = { ...emptyDraft(), enabled: r.payments[g.key]?.enabled ?? false, public_key: r.payments[g.key]?.public_key ?? '' };
        });
        setDraft(next);
      })
      .catch(() => setError('Could not load gateway settings.'));
    void loadPayments();
  }, [loadPayments]);

  const setField = (gateway: string, field: keyof DraftGateway, value: string | boolean) =>
    setDraft((d) => ({ ...d, [gateway]: { ...d[gateway], [field]: value } }));

  const saveSettings = async () => {
    setError('');
    setSaved(false);
    try {
      const payload = { payments: draft };
      const r = await apiPatch<{ payments: PaymentSettingsView }>('payments/settings', payload);
      setSettings(r.payments);
      setSaved(true);
    } catch {
      setError('Could not save settings.');
    }
  };

  const refund = async (id: number) => {
    // eslint-disable-next-line no-alert
    if (!window.confirm('Refund this payment?')) {
      return;
    }
    try {
      await apiPost(`payments/${id}/refund`, {});
      void loadPayments();
    } catch {
      setError('Refund failed.');
    }
  };

  return (
    <div className="bkra-space-y-8">
      <section aria-label="Payment gateways" className="bkra-space-y-4">
        <h2 className="bkra-text-lg bkra-font-semibold">Payment gateways</h2>
        {error && <div role="alert" className="bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">{error}</div>}
        {saved && <div className="bkra-rounded bkra-bg-green-50 bkra-p-3 bkra-text-sm bkra-text-green-700">Settings saved.</div>}

        <div className="bkra-grid bkra-gap-4 md:bkra-grid-cols-3">
          {GATEWAYS.map((g) => {
            const d = draft[g.key] ?? emptyDraft();
            const current = settings[g.key];
            return (
              <fieldset key={g.key} className="bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-p-4 bkra-space-y-2">
                <legend className="bkra-px-1 bkra-text-sm bkra-font-semibold">{g.label}</legend>
                <label className="bkra-flex bkra-items-center bkra-gap-2 bkra-text-sm">
                  <input type="checkbox" checked={d.enabled} onChange={(e) => setField(g.key, 'enabled', e.target.checked)} />
                  Enabled
                </label>
                <input placeholder="Public key" value={d.public_key} onChange={(e) => setField(g.key, 'public_key', e.target.value)} aria-label={`${g.label} public key`} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
                <input type="password" placeholder={current?.has_secret ? 'Secret key (set — leave blank to keep)' : 'Secret key'} value={d.secret_key} onChange={(e) => setField(g.key, 'secret_key', e.target.value)} aria-label={`${g.label} secret key`} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
                {g.key === 'flutterwave' ? (
                  <input type="password" placeholder={current?.has_webhook ? 'Secret hash (set)' : g.webhookLabel} value={d.secret_hash} onChange={(e) => setField(g.key, 'secret_hash', e.target.value)} aria-label={`${g.label} secret hash`} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
                ) : g.key === 'stripe' ? (
                  <input type="password" placeholder={current?.has_webhook ? 'Webhook secret (set)' : g.webhookLabel} value={d.webhook_secret} onChange={(e) => setField(g.key, 'webhook_secret', e.target.value)} aria-label={`${g.label} webhook secret`} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
                ) : (
                  <p className="bkra-text-xs bkra-text-gray-500">Webhook is verified with your secret key.</p>
                )}
              </fieldset>
            );
          })}
        </div>
        <button type="button" onClick={saveSettings} className="bkra-rounded bkra-bg-bookora-600 bkra-px-4 bkra-py-2 bkra-text-sm bkra-font-medium bkra-text-white">
          Save gateways
        </button>
      </section>

      <section aria-label="Recent payments" className="bkra-space-y-2">
        <h2 className="bkra-text-lg bkra-font-semibold">Recent payments</h2>
        <table className="bkra-w-full bkra-border-collapse bkra-text-sm">
          <thead>
            <tr className="bkra-border-b bkra-text-left bkra-text-gray-500">
              <th className="bkra-p-2">Gateway</th>
              <th className="bkra-p-2">Amount</th>
              <th className="bkra-p-2">Type</th>
              <th className="bkra-p-2">Status</th>
              <th className="bkra-p-2">Date</th>
              <th className="bkra-p-2" />
            </tr>
          </thead>
          <tbody>
            {payments.length === 0 && (
              <tr>
                <td colSpan={6} className="bkra-p-4 bkra-text-center bkra-text-gray-500">No payments yet.</td>
              </tr>
            )}
            {payments.map((p) => (
              <tr key={p.id} className="bkra-border-b">
                <td className="bkra-p-2">{p.gateway}</td>
                <td className="bkra-p-2">{p.currency} {p.amount}</td>
                <td className="bkra-p-2">{p.type}</td>
                <td className="bkra-p-2">{p.status}</td>
                <td className="bkra-p-2">{(p.paid_at ?? p.created_at).slice(0, 16)}</td>
                <td className="bkra-p-2 bkra-text-right">
                  {p.status === 'paid' && p.type !== 'refund' && (
                    <button type="button" onClick={() => refund(p.id)} className="bkra-text-red-700">Refund</button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </div>
  );
}
