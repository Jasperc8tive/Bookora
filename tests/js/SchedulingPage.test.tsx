import { render, screen, fireEvent } from '@testing-library/react';
import { SchedulingPage } from '../../assets/src/admin/components/scheduling/SchedulingPage';

const forecast = {
  success: true,
  data: {
    by_weekday: [1, 2, 3, 4, 5, 6, 7],
    next_days: [
      { date: '2026-06-14', weekday: 0, predicted: 1 },
      { date: '2026-06-15', weekday: 1, predicted: 4 },
    ],
  },
};
const workload = { success: true, data: { items: [{ staff_id: 1, name: 'Ada', upcoming: 3 }] } };
const suggestions = {
  success: true,
  data: { items: [{ start_local: '2026-06-15 09:00:00', staff_id: 1, score: 80 }] },
};

function mockFetch() {
  global.fetch = jest.fn((url: string) => {
    let body: unknown = { success: true, data: { items: [] } };
    if (url.includes('/scheduling/forecast')) {
      body = forecast;
    } else if (url.includes('/scheduling/workload')) {
      body = workload;
    } else if (url.includes('/scheduling/suggestions')) {
      body = suggestions;
    }
    return Promise.resolve({ ok: true, status: 200, json: async () => body });
  }) as unknown as typeof fetch;
}

describe('SchedulingPage', () => {
  beforeEach(() => {
    window.BookoraAdmin = { restUrl: 'https://example.test/wp-json/bookora/v1/', nonce: 'n', version: '0.1.0' };
    mockFetch();
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('renders the three panels and loads forecast + workload', async () => {
    render(<SchedulingPage />);

    expect(screen.getByText('Demand forecast (next 14 days)')).toBeInTheDocument();
    expect(screen.getByText('Staff workload balance')).toBeInTheDocument();
    expect(screen.getByText('Smart slot suggestions')).toBeInTheDocument();

    expect(await screen.findByText('Ada')).toBeInTheDocument();
  });

  it('runs a suggestion query and lists scored slots', async () => {
    render(<SchedulingPage />);

    fireEvent.change(screen.getByLabelText('Service ID'), { target: { value: '5' } });
    fireEvent.click(screen.getByText('Suggest'));

    expect(await screen.findByText('2026-06-15 09:00:00')).toBeInTheDocument();
    expect(screen.getByText('score 80')).toBeInTheDocument();
  });
});
