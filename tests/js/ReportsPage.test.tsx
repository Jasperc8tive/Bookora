import { render, screen } from '@testing-library/react';
import { ReportsPage } from '../../assets/src/admin/components/reports/ReportsPage';

const overview = {
  success: true,
  data: {
    kpis: { total_bookings: 12, completed: 8, cancelled: 2, no_show: 1, revenue: 1500, collected: 1200, cancellation_rate: 16.7, no_show_rate: 8.3, avg_value: 150 },
    revenue_by_day: [{ date: '2030-06-10', revenue: 500, bookings: 3 }],
    by_status: { completed: 8, confirmed: 1, cancelled: 2, no_show: 1 },
    by_staff: [{ staff_id: 1, name: 'Ada', bookings: 7, revenue: 900 }],
    by_service: [{ service_id: 1, name: 'Massage', bookings: 5, revenue: 600 }],
  },
};
const util = { success: true, data: { items: [{ staff_id: 1, name: 'Ada', booked_minutes: 420, available_minutes: 2400, utilization: 17.5 }] } };

describe('ReportsPage', () => {
  beforeEach(() => {
    window.BookoraAdmin = { restUrl: 'https://example.test/wp-json/bookora/v1/', nonce: 'n', version: '0.1.0' };
    global.fetch = jest.fn((url: string) => {
      const body = url.includes('/reports/utilization') ? util : overview;
      return Promise.resolve({ ok: true, status: 200, json: async () => body });
    }) as unknown as typeof fetch;
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('renders KPIs, revenue trend and utilisation', async () => {
    render(<ReportsPage />);

    expect(await screen.findByText('1500')).toBeInTheDocument(); // revenue KPI
    expect(screen.getByText('Revenue by day')).toBeInTheDocument();
    expect(screen.getByText('2030-06-10')).toBeInTheDocument();
    expect(await screen.findByText('17.5%')).toBeInTheDocument(); // utilisation row
  });
});
