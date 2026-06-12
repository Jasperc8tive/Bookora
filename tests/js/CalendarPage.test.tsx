import { render, screen } from '@testing-library/react';

// Mock FullCalendar (ESM, heavy) and its plugins with light stubs.
jest.mock('@fullcalendar/react', () => ({
  __esModule: true,
  default: () => <div data-testid="fullcalendar" />,
}));
jest.mock('@fullcalendar/daygrid', () => ({ __esModule: true, default: {} }));
jest.mock('@fullcalendar/timegrid', () => ({ __esModule: true, default: {} }));
jest.mock('@fullcalendar/list', () => ({ __esModule: true, default: {} }));
jest.mock('@fullcalendar/interaction', () => ({ __esModule: true, default: {} }));

// eslint-disable-next-line import/first
import { CalendarPage } from '../../assets/src/admin/components/calendar/CalendarPage';

const staff = {
  success: true,
  data: { items: [{ id: 1, display_name: 'Ada', email: null, phone: null, bio: null, avatar_url: null, color: null, status: 'active' }], total: 1, page: 1, per_page: 100, total_pages: 1 },
};

describe('CalendarPage', () => {
  beforeEach(() => {
    window.BookoraAdmin = { restUrl: 'https://example.test/wp-json/bookora/v1/', nonce: 'n', version: '0.1.0' };
    global.fetch = jest.fn(() => Promise.resolve({ ok: true, status: 200, json: async () => staff })) as unknown as typeof fetch;
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('renders the calendar with staff and status filters', async () => {
    render(<CalendarPage />);

    expect(screen.getByTestId('fullcalendar')).toBeInTheDocument();
    expect(screen.getByLabelText('Filter by staff')).toBeInTheDocument();
    expect(screen.getByLabelText('Filter by status')).toBeInTheDocument();
    // Staff option loads asynchronously.
    expect(await screen.findByText('Ada')).toBeInTheDocument();
  });
});
