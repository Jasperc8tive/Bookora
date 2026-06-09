import { render, screen } from '@testing-library/react';
import { StaffPage } from '../../assets/src/admin/components/staff/StaffPage';
import type { Paginated, Service, StaffMember } from '../../assets/src/admin/types';

const staff: Paginated<StaffMember> = {
  items: [
    {
      id: 1,
      display_name: 'Ada Lovelace',
      email: 'ada@example.com',
      phone: '08000000000',
      bio: null,
      avatar_url: null,
      color: null,
      status: 'active',
    },
  ],
  total: 1,
  page: 1,
  per_page: 20,
  total_pages: 1,
};

const services: Paginated<Service> = { items: [], total: 0, page: 1, per_page: 100, total_pages: 0 };

describe('StaffPage', () => {
  beforeEach(() => {
    window.BookoraAdmin = {
      restUrl: 'https://example.test/wp-json/bookora/v1/',
      nonce: 'n',
      version: '0.1.0',
    };
    global.fetch = jest.fn((url: string) => {
      const body = url.includes('/services') ? { success: true, data: services } : { success: true, data: staff };
      return Promise.resolve({ ok: true, status: 200, json: async () => body });
    }) as unknown as typeof fetch;
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('renders staff rows with email and status', async () => {
    render(<StaffPage />);

    expect(await screen.findByText('Ada Lovelace')).toBeInTheDocument();
    expect(screen.getByText('ada@example.com')).toBeInTheDocument();
    expect(screen.getByText('1 total')).toBeInTheDocument();
  });
});
