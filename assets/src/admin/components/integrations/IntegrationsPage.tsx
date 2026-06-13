/**
 * Integrations admin: Google Calendar app config + per-staff connect/disconnect.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPatch, apiPost } from '../../api/client';
import type { GoogleStatus, Paginated, StaffMember } from '../../types';

export function IntegrationsPage() {
  const [status, setStatus] = useState<GoogleStatus>({ configured: false, connected: [] });
  const [staff, setStaff] = useState<StaffMember[]>([]);
  const [clientId, setClientId] = useState('');
  const [clientSecret, setClientSecret] = useState('');
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  const refresh = useCallback(async () => {
    try {
      setStatus(await apiGet<GoogleStatus>('integrations/google'));
    } catch {
      setError('Could not load integration status.');
    }
  }, []);

  useEffect(() => {
    void refresh();
    void apiGet<Paginated<StaffMember>>('staff', { per_page: 100 }).then((r) => setStaff(r.items)).catch(() => undefined);
  }, [refresh]);

  const saveApp = async () => {
    setError('');
    setNotice('');
    try {
      await apiPatch('integrations/google', { client_id: clientId, client_secret: clientSecret });
      setClientSecret('');
      setNotice('Google app credentials saved.');
      void refresh();
    } catch {
      setError('Could not save credentials.');
    }
  };

  const connect = async (staffId: number) => {
    try {
      const { auth_url: url } = await apiGet<{ auth_url: string }>('integrations/google/connect', { staff_id: staffId });
      window.location.href = url;
    } catch {
      setError('Add and save your Google app credentials first.');
    }
  };

  const disconnect = async (staffId: number) => {
    await apiPost(`integrations/google/${staffId}/disconnect`, {});
    void refresh();
  };

  const sync = async (staffId: number) => {
    await apiPost(`integrations/google/${staffId}/sync`, {});
    setNotice('Sync requested.');
  };

  return (
    <div className="bkra-space-y-8">
      {error && <div role="alert" className="bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">{error}</div>}
      {notice && <div className="bkra-rounded bkra-bg-green-50 bkra-p-3 bkra-text-sm bkra-text-green-700">{notice}</div>}

      <section aria-label="Google app" className="bkra-space-y-3">
        <h2 className="bkra-text-lg bkra-font-semibold">Google Calendar</h2>
        <p className="bkra-text-sm bkra-text-gray-600">
          Status: {status.configured ? 'App configured' : 'Not configured — add your OAuth client below.'}
        </p>
        <div className="bkra-grid bkra-gap-3 md:bkra-grid-cols-2">
          <input value={clientId} onChange={(e) => setClientId(e.target.value)} placeholder="OAuth client ID" aria-label="Google client id" className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm" />
          <input type="password" value={clientSecret} onChange={(e) => setClientSecret(e.target.value)} placeholder="OAuth client secret" aria-label="Google client secret" className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm" />
        </div>
        <button type="button" onClick={saveApp} className="bkra-rounded bkra-bg-bookora-600 bkra-px-4 bkra-py-2 bkra-text-sm bkra-font-medium bkra-text-white">Save credentials</button>
      </section>

      <section aria-label="Staff connections" className="bkra-space-y-2">
        <h3 className="bkra-text-sm bkra-font-semibold">Staff calendars</h3>
        <table className="bkra-w-full bkra-border-collapse bkra-text-sm">
          <thead>
            <tr className="bkra-border-b bkra-text-left bkra-text-gray-500">
              <th className="bkra-p-2">Staff</th>
              <th className="bkra-p-2">Google</th>
              <th className="bkra-p-2" />
            </tr>
          </thead>
          <tbody>
            {staff.length === 0 && (
              <tr><td colSpan={3} className="bkra-p-4 bkra-text-center bkra-text-gray-500">No staff yet.</td></tr>
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
    </div>
  );
}
