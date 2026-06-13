import { render, screen } from '@testing-library/react';
import { NotificationsPage } from '../../assets/src/admin/components/notifications/NotificationsPage';

const settings = {
  success: true,
  data: {
    channels: {
      email: { enabled: true },
      sms: { enabled: false, sender: '', has_api_key: false },
      whatsapp: { enabled: false, phone_number_id: '', has_access_token: false },
      push: { enabled: false, endpoint: '', has_api_key: false },
    },
    reminders: { offsets: [1440, 60] },
    events: ['booking_created', 'reminder'],
    defaults: {
      booking_created: { email: { subject: 'Confirmed', body: 'Hi {{customer_name}}' }, sms: { subject: '', body: 'Hi' }, whatsapp: { subject: '', body: 'Hi' } },
      reminder: { email: { subject: 'Reminder', body: 'Soon' }, sms: { subject: '', body: 'Soon' }, whatsapp: { subject: '', body: 'Soon' } },
    },
    templates: {},
  },
};

const log = { success: true, data: { items: [{ id: 1, channel: 'email', event: 'booking_created', recipient: 'a@b.com', status: 'sent', error: null, sent_at: '2030-06-12 09:00:00', created_at: '2030-06-12 09:00:00' }] } };

describe('NotificationsPage', () => {
  beforeEach(() => {
    window.BookoraAdmin = { restUrl: 'https://example.test/wp-json/bookora/v1/', nonce: 'n', version: '0.1.0' };
    global.fetch = jest.fn((url: string) => {
      const body = url.includes('/notifications/log') ? log : settings;
      return Promise.resolve({ ok: true, status: 200, json: async () => body });
    }) as unknown as typeof fetch;
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('renders channels, templates, and the delivery log', async () => {
    render(<NotificationsPage />);

    expect(await screen.findByText('Channels')).toBeInTheDocument();
    expect(screen.getByText('WhatsApp (Cloud API)')).toBeInTheDocument();
    expect(screen.getByLabelText('Reminder offsets')).toHaveValue('1440, 60');
    // The delivery log row (unique recipient) confirms the log loaded.
    expect(await screen.findByText('a@b.com')).toBeInTheDocument();
  });
});
