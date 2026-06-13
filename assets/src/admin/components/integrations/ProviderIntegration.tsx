/**
 * One calendar provider's integration card: app credentials + per-staff connect.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPatch, apiPost } from '../../api/client';
import type { GoogleStatus, StaffMember } from '../../types';

interface AppField {
  key: string;
  label: string;
  secret?: boolean;
}

interface Props {
  label: string;
  endpoint: string;
  staff: StaffMember[];
  appFields: AppField[];
}

export function ProviderIntegration({ label, endpoint, staff, appFields }: Props) {
  const [status, setStatus] = useState<GoogleStatus>({ configured: false, connected: [] });
  const [draft, setDraft] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  const refresh = useCallback(async () => {
    try {
      setStatus(await apiGet<GoogleStatus>(endpoint));
    } catch {
      setError(`Could not load ${label} status.`);
    }
  }, [endpoint, label]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const saveApp = async () => {
    setError('');
    setNotice('');
    try {
      await apiPatch(endpoint, draft);
      setDraft((d) => {
        const next = { ...d };
        appFields.filter((f) => f.secret).forEach((f) => delete next[f.key]);
        return next;
      });
      setNotice(`${label} credentials saved.`);
      void refresh();
    } catch {
      setError('Could not save credentials.');
    }
  };

  const connect = async (staffId: number) => {
    try {
      const { auth_url: url } = await apiGet<{ auth_url: string }>(`${endpoint}/connect`, { staff_id: staffId });
      window.location.href = url;
    } catch {
      setError(`Add and save your ${label} app credentials first.`);
    }
  };

  const disconnect = async (staffId: number) => {
    await apiPost(`${endpoint}/${staffId}/disconnect`, {});
    void refresh();
  };

  const sync = async (staffId: number) => {
    await apiPost(`${endpoint}/${staffId}/sync`, {});
    setNotice('Sync requested.');
  };

  return (
    <section aria-label={label} className="bkra-space-y-3 bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-p-4">
      <h2 className="bkra-text-lg bkra-font-semibold">{label}</h2>
      {error && <div role="alert" className="bkra-rounded bkra-bg-red-50 bkra-p-2 bkra-text-sm bkra-text-red-700">{error}</div>}
      {notice && <div className="bkra-rounded bkra-bg-green-50 bkra-p-2 bkra-text-sm bkra-text-green-700">{notice}</div>}
      <p className="bkra-text-sm bkra-text-gray-600">
        Status: {status.configured ? 'App configured' : 'Not configured — add your OAuth credentials.'}
      </p>

      <div className="bkra-grid bkra-gap-3 md:bkra-grid-cols-2">
        {appFields.map((f) => (
          <input
            key={f.key}
            type={f.secret ? 'password' : 'text'}
            value={draft[f.key] ?? ''}
            onChange={(e) => setDraft((d) => ({ ...d, [f.key]: e.target.value }))}
            placeholder={f.label}
            aria-label={`${label} ${f.label}`}
            className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
          />
        ))}
      </div>
      <button type="button" onClick={saveApp} className="bkra-rounded bkra-bg-bookora-600 bkra-px-4 bkra-py-2 bkra-text-sm bkra-font-medium bkra-text-white">
        Save credentials
      </button>

      <table className="bkra-w-full bkra-border-collapse bkra-text-sm">
        <tbody>
          {staff.length === 0 && (
            <tr><td className="bkra-p-2 bkra-text-gray-500">No staff yet.</td></tr>
          )}
          {staff.map((member) => {
            const connected = status.connected.includes(member.id);
            return (
              <tr key={member.id} className="bkra-border-b">
                <td className="bkra-p-2 bkra-font-medium">{member.display_name}</td>
                <td className="bkra-p-2">{connected ? 'Connected' : 'Not connected'}</td>
                <td className="bkra-p-2 bkra-text-right">
                  {connected ? (
                    <>
                      <button type="button" onClick={() => void sync(member.id)} className="bkra-mr-2 bkra-text-bookora-700">Sync</button>
                      <button type="button" onClick={() => void disconnect(member.id)} className="bkra-text-red-700">Disconnect</button>
                    </>
                  ) : (
                    <button type="button" disabled={!status.configured} onClick={() => void connect(member.id)} className="bkra-text-bookora-700 disabled:bkra-opacity-40">Connect</button>
                  )}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </section>
  );
}
