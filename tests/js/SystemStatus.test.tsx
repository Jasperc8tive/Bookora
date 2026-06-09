import { render, screen, waitFor } from '@testing-library/react';
import { SystemStatus } from '../../assets/src/admin/components/SystemStatus';
import type { SystemHealth } from '../../assets/src/admin/types';

const health: SystemHealth = {
  plugin: { name: 'Bookora', version: '0.1.0', db_version: '1' },
  env: { php: '8.2.12', wp: '6.8', prefix: 'wp_bkra_' },
  database: {
    migrated: true,
    applied: ['0001'],
    tables: { services: true, appointments: true, payments: true },
  },
  healthy: true,
  timestamp: '2026-06-08T00:00:00+00:00',
};

describe('SystemStatus', () => {
  beforeEach(() => {
    window.BookoraAdmin = {
      restUrl: 'https://example.test/wp-json/bookora/v1/',
      nonce: 'test-nonce',
      version: '0.1.0',
    };
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('renders healthy status and sends the nonce header', async () => {
    const fetchMock = jest.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: health }),
    });
    global.fetch = fetchMock as unknown as typeof fetch;

    render(<SystemStatus />);

    expect(await screen.findByText('System Status')).toBeInTheDocument();
    expect(screen.getByText('Healthy')).toBeInTheDocument();
    expect(screen.getByText('0.1.0')).toBeInTheDocument();

    const [, options] = fetchMock.mock.calls[0];
    expect(options.headers['X-WP-Nonce']).toBe('test-nonce');
  });

  it('renders an error state when the request fails', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: false,
      status: 500,
      json: async () => ({}),
    }) as unknown as typeof fetch;

    render(<SystemStatus />);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
  });
});
