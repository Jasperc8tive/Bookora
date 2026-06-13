import { render, screen, waitFor } from '@testing-library/react';
import { PortalApp } from '../../assets/src/portal/PortalApp';

const profile = { success: true, data: { id: 1, name: 'Ada', email: 'ada@example.com', phone: '0800', timezone: '', locale: '' } };
const bookings = {
  success: true,
  data: {
    upcoming: [
      { id: 10, service_id: 2, staff_id: 3, start_at: '2030-06-12 09:00:00', end_at: '2030-06-12 09:30:00', status: 'confirmed', total: '50.00', currency: 'NGN', service_name: 'Massage', staff_name: 'Joy', can_reschedule: true, can_cancel: true },
    ],
    past: [],
  },
};

function mockFetch() {
  global.fetch = jest.fn((url: string) => {
    const body = url.includes('/portal/bookings') ? bookings : profile;
    return Promise.resolve({ ok: true, status: 200, json: async () => body });
  }) as unknown as typeof fetch;
}

describe('PortalApp', () => {
  beforeEach(() => {
    window.BookoraPortal = { restUrl: 'https://example.test/wp-json/bookora/v1/', nonce: 'n' };
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraPortal;
    window.localStorage.clear();
  });

  it('shows the login form when there is no token', async () => {
    mockFetch();
    render(<PortalApp />);
    expect(await screen.findByText('Manage your bookings')).toBeInTheDocument();
    expect(screen.getByLabelText('Email')).toBeInTheDocument();
  });

  it('shows the dashboard with bookings when a token is present', async () => {
    window.localStorage.setItem('bookora_portal_token', 'tok');
    mockFetch();
    render(<PortalApp />);

    expect(await screen.findByText('Hi Ada')).toBeInTheDocument();
    await waitFor(() => expect(screen.getByText(/Massage/)).toBeInTheDocument());
    expect(screen.getByText('Reschedule')).toBeInTheDocument();
    expect(screen.getByText('Cancel')).toBeInTheDocument();
  });
});
