/**
 * Commercial hardening admin: license, feature flags, branding, telemetry,
 * and data import/export + backups.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPost } from '../../api/client';

type Tab = 'license' | 'features' | 'branding' | 'data' | 'telemetry';

const TABS: { id: Tab; label: string }[] = [
  { id: 'license', label: 'License' },
  { id: 'features', label: 'Features' },
  { id: 'branding', label: 'Branding' },
  { id: 'data', label: 'Import / Export' },
  { id: 'telemetry', label: 'Telemetry' },
];

const input = 'bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm';
const btn = 'bkra-rounded bkra-bg-bookora-600 bkra-px-3 bkra-py-1 bkra-text-sm bkra-text-white disabled:bkra-opacity-50';

export function CommercialPage() {
  const [tab, setTab] = useState<Tab>('license');

  return (
    <div className="bkra-space-y-4">
      <div className="bkra-flex bkra-flex-wrap bkra-gap-1 bkra-border-b">
        {TABS.map((t) => (
          <button key={t.id} type="button" onClick={() => setTab(t.id)} className={`bkra-px-3 bkra-py-2 bkra-text-sm ${tab === t.id ? 'bkra-border-b-2 bkra-border-bookora-600 bkra-font-medium' : 'bkra-text-gray-500'}`}>
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'license' && <License />}
      {tab === 'features' && <Features />}
      {tab === 'branding' && <Branding />}
      {tab === 'data' && <DataTools />}
      {tab === 'telemetry' && <TelemetryPanel />}
    </div>
  );
}

type LicenseStatus = { licensed: boolean; valid: boolean; status: string; tier: string; expires_at: string; masked_key: string };

function License() {
  const [status, setStatus] = useState<LicenseStatus | null>(null);
  const [key, setKey] = useState('');
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    void apiGet<LicenseStatus>('commercial/license').then(setStatus).catch(() => setStatus(null));
  }, []);
  useEffect(load, [load]);

  const activate = async () => {
    setBusy(true);
    setMsg('');
    try {
      const r = await apiPost<LicenseStatus>('commercial/license/activate', { key });
      setStatus(r);
      setKey('');
      setMsg('License activated.');
    } catch (e) {
      setMsg(e instanceof Error ? e.message : 'Activation failed.');
    } finally {
      setBusy(false);
    }
  };

  const deactivate = async () => {
    setBusy(true);
    setMsg('');
    try {
      const r = await apiPost<LicenseStatus>('commercial/license/deactivate', {});
      setStatus(r);
      setMsg('License deactivated.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <section aria-label="License" className="bkra-space-y-3">
      {status && (
        <div className="bkra-flex bkra-items-center bkra-gap-3 bkra-text-sm">
          <span className={`bkra-rounded-full bkra-px-2 bkra-py-0.5 bkra-text-xs bkra-font-medium ${status.valid ? 'bkra-bg-green-100 bkra-text-green-800' : 'bkra-bg-gray-100 bkra-text-gray-600'}`}>
            {status.valid ? 'Active' : 'Inactive'}
          </span>
          <span className="bkra-text-gray-700">Tier: <strong className="bkra-uppercase">{status.tier}</strong></span>
          {status.masked_key && <span className="bkra-text-gray-400">{status.masked_key}</span>}
          {status.expires_at && <span className="bkra-text-gray-400">expires {status.expires_at.slice(0, 10)}</span>}
        </div>
      )}
      {!status?.licensed && (
        <div className="bkra-flex bkra-flex-wrap bkra-gap-2">
          <input className={input} placeholder="License key" aria-label="License key" value={key} onChange={(e) => setKey(e.target.value)} />
          <button type="button" className={btn} disabled={busy || !key} onClick={activate}>Activate</button>
        </div>
      )}
      {status?.licensed && (
        <button type="button" className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-1 bkra-text-sm" disabled={busy} onClick={deactivate}>Deactivate</button>
      )}
      {msg && <p className="bkra-text-sm bkra-text-gray-600">{msg}</p>}
    </section>
  );
}

function Features() {
  const [data, setData] = useState<{ tier: string; features: Record<string, boolean> } | null>(null);
  useEffect(() => {
    void apiGet<{ tier: string; features: Record<string, boolean> }>('commercial/features').then(setData).catch(() => setData(null));
  }, []);

  return (
    <section aria-label="Features" className="bkra-space-y-2">
      <p className="bkra-text-sm bkra-text-gray-500">Tier: <strong className="bkra-uppercase">{data?.tier ?? '—'}</strong></p>
      <ul className="bkra-grid bkra-grid-cols-2 bkra-gap-2">
        {data && Object.entries(data.features).map(([feature, on]) => (
          <li key={feature} className="bkra-flex bkra-items-center bkra-gap-2 bkra-text-sm">
            <span className={`bkra-h-2 bkra-w-2 bkra-rounded-full ${on ? 'bkra-bg-green-500' : 'bkra-bg-gray-300'}`} />
            <span className={on ? 'bkra-text-gray-800' : 'bkra-text-gray-400'}>{feature.replace(/_/g, ' ')}</span>
          </li>
        ))}
      </ul>
    </section>
  );
}

type BrandingState = { plugin_name: string; vendor_name: string; support_url: string; logo_url: string; primary_color: string };

function Branding() {
  const blank: BrandingState = { plugin_name: '', vendor_name: '', support_url: '', logo_url: '', primary_color: '' };
  const [form, setForm] = useState<BrandingState>(blank);
  const [editable, setEditable] = useState(false);
  const [msg, setMsg] = useState('');

  useEffect(() => {
    void apiGet<{ editable: boolean; branding: Partial<BrandingState> }>('commercial/branding')
      .then((r) => {
        setEditable(r.editable);
        setForm({ ...blank, ...r.branding });
      })
      .catch(() => undefined);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const save = async () => {
    setMsg('');
    try {
      await apiPost('commercial/branding', { branding: form });
      setMsg('Branding saved.');
    } catch (e) {
      setMsg(e instanceof Error ? e.message : 'Save failed.');
    }
  };

  if (!editable) {
    return <p className="bkra-text-sm bkra-text-gray-500">White-labelling requires an agency license.</p>;
  }

  return (
    <section aria-label="Branding" className="bkra-space-y-2">
      <input className={input} placeholder="Plugin name" aria-label="Plugin name" value={form.plugin_name} onChange={(e) => setForm({ ...form, plugin_name: e.target.value })} />
      <input className={input} placeholder="Vendor name" aria-label="Vendor name" value={form.vendor_name} onChange={(e) => setForm({ ...form, vendor_name: e.target.value })} />
      <input className={input} placeholder="Support URL" aria-label="Support URL" value={form.support_url} onChange={(e) => setForm({ ...form, support_url: e.target.value })} />
      <input className={input} placeholder="Logo URL" aria-label="Logo URL" value={form.logo_url} onChange={(e) => setForm({ ...form, logo_url: e.target.value })} />
      <input className={input} type="text" placeholder="#2563eb" aria-label="Primary color" value={form.primary_color} onChange={(e) => setForm({ ...form, primary_color: e.target.value })} />
      <div><button type="button" className={btn} onClick={save}>Save branding</button></div>
      {msg && <p className="bkra-text-sm bkra-text-gray-600">{msg}</p>}
    </section>
  );
}

type Backup = { id: string; size: number; created_at: string };

function DataTools() {
  const [backups, setBackups] = useState<Backup[]>([]);
  const [msg, setMsg] = useState('');

  const loadBackups = useCallback(() => {
    void apiGet<{ items: Backup[] }>('commercial/backups').then((r) => setBackups(r.items)).catch(() => setBackups([]));
  }, []);
  useEffect(loadBackups, [loadBackups]);

  const exportData = async () => {
    const doc = await apiGet<Record<string, unknown>>('commercial/export');
    const blob = new Blob([JSON.stringify(doc)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'bookora-export.json';
    a.click();
    URL.revokeObjectURL(url);
  };

  const createBackup = async () => {
    setMsg('');
    await apiPost('commercial/backups', {});
    setMsg('Backup created.');
    loadBackups();
  };

  const restore = async (id: string) => {
    if (!window.confirm('Restore this backup? Existing data will be replaced.')) {
      return;
    }
    await apiPost('commercial/backups/restore', { id, confirm: true });
    setMsg('Backup restored.');
  };

  const remove = async (id: string) => {
    await apiPost('commercial/backups/delete', { id });
    loadBackups();
  };

  return (
    <section aria-label="Import and export" className="bkra-space-y-4">
      <div className="bkra-flex bkra-flex-wrap bkra-gap-2">
        <button type="button" className={btn} onClick={exportData}>Export all data</button>
        <button type="button" className={btn} onClick={createBackup}>Create backup</button>
      </div>
      {msg && <p className="bkra-text-sm bkra-text-gray-600">{msg}</p>}

      <div>
        <h3 className="bkra-mb-2 bkra-text-sm bkra-font-semibold bkra-text-gray-700">Backups</h3>
        {backups.length === 0 && <p className="bkra-text-sm bkra-text-gray-500">No backups yet.</p>}
        <ul className="bkra-space-y-1">
          {backups.map((b) => (
            <li key={b.id} className="bkra-flex bkra-items-center bkra-gap-3 bkra-rounded bkra-border bkra-border-gray-200 bkra-p-2 bkra-text-sm">
              <span className="bkra-font-mono bkra-text-gray-700">{b.id}</span>
              <span className="bkra-text-gray-400">{Math.round(b.size / 1024)} KB</span>
              <span className="bkra-ml-auto bkra-flex bkra-gap-2">
                <button type="button" className="bkra-text-bookora-700" onClick={() => restore(b.id)}>Restore</button>
                <button type="button" className="bkra-text-red-700" onClick={() => remove(b.id)}>Delete</button>
              </span>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}

function TelemetryPanel() {
  const [enabled, setEnabled] = useState(false);
  const [preview, setPreview] = useState<Record<string, unknown> | null>(null);

  useEffect(() => {
    void apiGet<{ enabled: boolean; preview: Record<string, unknown> }>('commercial/telemetry')
      .then((r) => {
        setEnabled(r.enabled);
        setPreview(r.preview);
      })
      .catch(() => undefined);
  }, []);

  const toggle = async (next: boolean) => {
    setEnabled(next);
    await apiPost('commercial/telemetry', { enabled: next });
  };

  return (
    <section aria-label="Telemetry" className="bkra-space-y-3">
      <label className="bkra-flex bkra-items-center bkra-gap-2 bkra-text-sm">
        <input type="checkbox" checked={enabled} onChange={(e) => toggle(e.target.checked)} />
        Share anonymous usage data to help improve Bookora
      </label>
      {preview && (
        <div>
          <p className="bkra-mb-1 bkra-text-xs bkra-text-gray-500">What would be sent (no personal data):</p>
          <pre className="bkra-overflow-auto bkra-rounded bkra-bg-gray-50 bkra-p-2 bkra-text-xs bkra-text-gray-600">{JSON.stringify(preview, null, 2)}</pre>
        </div>
      )}
    </section>
  );
}
