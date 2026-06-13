import { render, screen } from '@testing-library/react';
import { IntegrationsPage } from '../../assets/src/admin/components/integrations/IntegrationsPage';

const statusBody = { success: true, data: { configured: true, connected: [1] } };
const staffBody = {
  success: true,
  data: {
    items: [
      { id: 1, display_name: 'Ada', email: null, phone: null, bio: null, avatar_url: null, color: null, status: 'active' },
      { id: 2, display_name: 'Tunde', email: null, phone: null, bio: null, avatar_url: null, color: null, status: 'active' },
    ],
    total: 2,
    page: 1,
    per_page: 100,
    total_pages: 1,
  },
};

describe('IntegrationsPage', () => {
  beforeEach(() => {
    window.BookoraAdmin = { restUrl: 'https://example.test/wp-json/bookora/v1/', nonce: 'n', version: '0.1.0' };
    global.fetch = jest.fn((url: string) => {
      const body = url.includes('/staff') ? staffBody : statusBody;
      return Promise.resolve({ ok: true, status: 200, json: async () => body });
    }) as unknown as typeof fetch;
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('shows Google config and per-staff connection state', async () => {
    render(<IntegrationsPage />);

    expect(screen.getByText('Google Calendar')).toBeInTheDocument();
    // Ada (id 1) is connected; Tunde (id 2) is not.
    expect(await screen.findByText('Ada')).toBeInTheDocument();
    expect(screen.getByText('Connected')).toBeInTheDocument();
    expect(screen.getByText('Not connected')).toBeInTheDocument();
  });
});
