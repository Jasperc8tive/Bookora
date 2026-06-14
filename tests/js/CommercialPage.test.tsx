import { render, screen, fireEvent } from '@testing-library/react';
import { CommercialPage } from '../../assets/src/admin/components/commercial/CommercialPage';

const license = { success: true, data: { licensed: false, valid: false, status: 'inactive', tier: 'free', expires_at: '', masked_key: '' } };
const features = { success: true, data: { tier: 'free', features: { payments: true, white_label: false, ai_scheduling: true } } };
const branding = { success: true, data: { active: false, editable: false, branding: {} } };
const telemetry = { success: true, data: { enabled: false, preview: { plugin_version: '0.1.0', counts: { services: 2 } } } };
const backups = { success: true, data: { items: [{ id: '20260614-101010-abc123', size: 2048, created_at: '2026-06-14T10:10:10Z' }] } };

function mockFetch() {
  global.fetch = jest.fn((url: string) => {
    let body: unknown = { success: true, data: {} };
    if (url.includes('/commercial/license')) {
      body = license;
    } else if (url.includes('/commercial/features')) {
      body = features;
    } else if (url.includes('/commercial/branding')) {
      body = branding;
    } else if (url.includes('/commercial/telemetry')) {
      body = telemetry;
    } else if (url.includes('/commercial/backups')) {
      body = backups;
    }
    return Promise.resolve({ ok: true, status: 200, json: async () => body });
  }) as unknown as typeof fetch;
}

describe('CommercialPage', () => {
  beforeEach(() => {
    window.BookoraAdmin = { restUrl: 'https://example.test/wp-json/bookora/v1/', nonce: 'n', version: '0.1.0' };
    mockFetch();
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('shows the license tab with an activation field on the free tier', async () => {
    render(<CommercialPage />);
    expect(await screen.findByText('Inactive')).toBeInTheDocument();
    expect(screen.getByLabelText('License key')).toBeInTheDocument();
  });

  it('lists feature flags for the current tier', async () => {
    render(<CommercialPage />);
    fireEvent.click(screen.getByText('Features'));
    expect(await screen.findByText('payments')).toBeInTheDocument();
    expect(screen.getByText('white label')).toBeInTheDocument();
  });

  it('locks branding behind an agency license', async () => {
    render(<CommercialPage />);
    fireEvent.click(screen.getByText('Branding'));
    expect(await screen.findByText(/agency license/i)).toBeInTheDocument();
  });

  it('lists existing backups under Import / Export', async () => {
    render(<CommercialPage />);
    fireEvent.click(screen.getByText('Import / Export'));
    expect(await screen.findByText('20260614-101010-abc123')).toBeInTheDocument();
  });
});
