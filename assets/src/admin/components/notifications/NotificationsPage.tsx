/**
 * Notifications admin: channels, reminders, templates, delivery log, test send.
 */
import { useEffect, useState } from 'react';
import { apiGet, apiPatch, apiPost } from '../../api/client';
import type { NotificationLogRow, NotificationSettings, TemplateBody } from '../../types';

const CHANNELS_FOR_EVENT = ['email', 'sms', 'whatsapp'] as const;

export function NotificationsPage() {
  const [settings, setSettings] = useState<NotificationSettings | null>(null);
  const [log, setLog] = useState<NotificationLogRow[]>([]);
  const [offsets, setOffsets] = useState('');
  const [event, setEvent] = useState('');
  const [templates, setTemplates] = useState<Record<string, Record<string, TemplateBody>>>({});
  const [testEmail, setTestEmail] = useState('');
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    void apiGet<NotificationSettings>('notifications/settings')
      .then((s) => {
        setSettings(s);
        setOffsets(s.reminders.offsets.join(', '));
        setEvent(s.events[0] ?? '');
        setTemplates(s.templates ?? {});
      })
      .catch(() => setError('Could not load settings.'));
    void apiGet<{ items: NotificationLogRow[] }>('notifications/log').then((r) => setLog(r.items)).catch(() => undefined);
  }, []);

  if (!settings) {
    return <p className="bkra-text-sm bkra-text-gray-500">Loading…</p>;
  }

  const setChannel = (key: keyof NotificationSettings['channels'], patch: Record<string, unknown>) =>
    setSettings({ ...settings, channels: { ...settings.channels, [key]: { ...settings.channels[key], ...patch } } });

  const templateValue = (channel: string, field: keyof TemplateBody): string => {
    const custom = templates[event]?.[channel]?.[field];
    if (custom !== undefined && custom !== '') {
      return custom;
    }
    return settings.defaults[event]?.[channel]?.[field] ?? '';
  };

  const setTemplate = (channel: string, field: keyof TemplateBody, value: string) =>
    setTemplates((t) => ({
      ...t,
      [event]: { ...t[event], [channel]: { ...(t[event]?.[channel] ?? { subject: '', body: '' }), [field]: value } },
    }));

  const save = async () => {
    setError('');
    setNotice('');
    try {
      const payload = {
        channels: {
          email: { enabled: settings.channels.email.enabled },
          sms: { enabled: settings.channels.sms.enabled, sender: settings.channels.sms.sender },
          whatsapp: { enabled: settings.channels.whatsapp.enabled, phone_number_id: settings.channels.whatsapp.phone_number_id },
          push: { enabled: settings.channels.push.enabled, endpoint: settings.channels.push.endpoint },
        },
        reminders: { offsets: offsets.split(',').map((o) => Number.parseInt(o.trim(), 10)).filter((n) => !Number.isNaN(n)) },
        templates,
      };
      const updated = await apiPatch<NotificationSettings>('notifications/settings', payload);
      setSettings(updated);
      setNotice('Saved.');
    } catch {
      setError('Could not save settings.');
    }
  };

  const sendTest = async () => {
    setError('');
    setNotice('');
    try {
      await apiPost('notifications/test', { email: testEmail });
      setNotice('Test email sent.');
    } catch {
      setError('Test send failed (check email channel + mail setup).');
    }
  };

  return (
    <div className="bkra-space-y-8">
      {error && <div role="alert" className="bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">{error}</div>}
      {notice && <div className="bkra-rounded bkra-bg-green-50 bkra-p-3 bkra-text-sm bkra-text-green-700">{notice}</div>}

      <section aria-label="Channels" className="bkra-space-y-3">
        <h2 className="bkra-text-lg bkra-font-semibold">Channels</h2>
        <div className="bkra-grid bkra-gap-4 md:bkra-grid-cols-2">
          <label className="bkra-flex bkra-items-center bkra-gap-2 bkra-text-sm"><input type="checkbox" checked={settings.channels.email.enabled} onChange={(e) => setChannel('email', { enabled: e.target.checked })} /> Email</label>
          <div className="bkra-rounded bkra-border bkra-border-gray-200 bkra-p-3 bkra-space-y-2">
            <label className="bkra-flex bkra-items-center bkra-gap-2 bkra-text-sm"><input type="checkbox" checked={settings.channels.sms.enabled} onChange={(e) => setChannel('sms', { enabled: e.target.checked })} /> SMS (Termii)</label>
            <input placeholder="Sender ID" value={settings.channels.sms.sender} onChange={(e) => setChannel('sms', { sender: e.target.value })} aria-label="SMS sender" className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
            <input type="password" placeholder={settings.channels.sms.has_api_key ? 'API key (set)' : 'API key'} onChange={(e) => setChannel('sms', { api_key: e.target.value })} aria-label="SMS api key" className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
          </div>
          <div className="bkra-rounded bkra-border bkra-border-gray-200 bkra-p-3 bkra-space-y-2">
            <label className="bkra-flex bkra-items-center bkra-gap-2 bkra-text-sm"><input type="checkbox" checked={settings.channels.whatsapp.enabled} onChange={(e) => setChannel('whatsapp', { enabled: e.target.checked })} /> WhatsApp (Cloud API)</label>
            <input placeholder="Phone number ID" value={settings.channels.whatsapp.phone_number_id} onChange={(e) => setChannel('whatsapp', { phone_number_id: e.target.value })} aria-label="WhatsApp phone number id" className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
            <input type="password" placeholder={settings.channels.whatsapp.has_access_token ? 'Access token (set)' : 'Access token'} onChange={(e) => setChannel('whatsapp', { access_token: e.target.value })} aria-label="WhatsApp access token" className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
          </div>
          <div className="bkra-rounded bkra-border bkra-border-gray-200 bkra-p-3 bkra-space-y-2">
            <label className="bkra-flex bkra-items-center bkra-gap-2 bkra-text-sm"><input type="checkbox" checked={settings.channels.push.enabled} onChange={(e) => setChannel('push', { enabled: e.target.checked })} /> Push</label>
            <input placeholder="Provider endpoint" value={settings.channels.push.endpoint} onChange={(e) => setChannel('push', { endpoint: e.target.value })} aria-label="Push endpoint" className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
          </div>
        </div>
        <label className="bkra-block bkra-text-sm">
          <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Reminder offsets (minutes before, comma-separated)</span>
          <input value={offsets} onChange={(e) => setOffsets(e.target.value)} aria-label="Reminder offsets" className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
        </label>
      </section>

      <section aria-label="Templates" className="bkra-space-y-3">
        <h2 className="bkra-text-lg bkra-font-semibold">Templates</h2>
        <select value={event} onChange={(e) => setEvent(e.target.value)} aria-label="Event" className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm">
          {settings.events.map((ev) => (
            <option key={ev} value={ev}>{ev}</option>
          ))}
        </select>
        <p className="bkra-text-xs bkra-text-gray-500">Placeholders: {'{{customer_name}} {{service_name}} {{staff_name}} {{date}} {{time}} {{business_name}} {{amount}} {{currency}}'}</p>
        {CHANNELS_FOR_EVENT.map((ch) => (
          <div key={ch} className="bkra-rounded bkra-border bkra-border-gray-200 bkra-p-3 bkra-space-y-2">
            <h3 className="bkra-text-sm bkra-font-semibold bkra-capitalize">{ch}</h3>
            {ch === 'email' && (
              <input value={templateValue(ch, 'subject')} onChange={(e) => setTemplate(ch, 'subject', e.target.value)} aria-label={`${ch} subject`} placeholder="Subject" className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
            )}
            <textarea value={templateValue(ch, 'body')} onChange={(e) => setTemplate(ch, 'body', e.target.value)} aria-label={`${ch} body`} rows={2} className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-2 bkra-py-1 bkra-text-sm" />
          </div>
        ))}
      </section>

      <div className="bkra-flex bkra-flex-wrap bkra-items-center bkra-gap-2">
        <button type="button" onClick={save} className="bkra-rounded bkra-bg-bookora-600 bkra-px-4 bkra-py-2 bkra-text-sm bkra-font-medium bkra-text-white">Save notifications</button>
        <input type="email" placeholder="you@example.com" value={testEmail} onChange={(e) => setTestEmail(e.target.value)} aria-label="Test email" className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm" />
        <button type="button" onClick={sendTest} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-4 bkra-py-2 bkra-text-sm">Send test email</button>
      </div>

      <section aria-label="Delivery log" className="bkra-space-y-2">
        <h2 className="bkra-text-lg bkra-font-semibold">Recent notifications</h2>
        <table className="bkra-w-full bkra-border-collapse bkra-text-sm">
          <thead>
            <tr className="bkra-border-b bkra-text-left bkra-text-gray-500">
              <th className="bkra-p-2">Channel</th>
              <th className="bkra-p-2">Event</th>
              <th className="bkra-p-2">Recipient</th>
              <th className="bkra-p-2">Status</th>
              <th className="bkra-p-2">When</th>
            </tr>
          </thead>
          <tbody>
            {log.length === 0 && (
              <tr><td colSpan={5} className="bkra-p-4 bkra-text-center bkra-text-gray-500">No notifications yet.</td></tr>
            )}
            {log.map((row) => (
              <tr key={row.id} className="bkra-border-b">
                <td className="bkra-p-2">{row.channel}</td>
                <td className="bkra-p-2">{row.event}</td>
                <td className="bkra-p-2">{row.recipient || '—'}</td>
                <td className="bkra-p-2">{row.status}{row.error ? ` (${row.error})` : ''}</td>
                <td className="bkra-p-2">{(row.sent_at ?? row.created_at).slice(0, 16)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </div>
  );
}
