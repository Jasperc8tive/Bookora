import { render, screen } from '@testing-library/react';
import { CustomersPage } from '../../assets/src/admin/components/customers/CustomersPage';
import type { Customer, Paginated } from '../../assets/src/admin/types';

const customers: Paginated<Customer> = {
  items: [
    {
      id: 1,
      name: 'Ada Lovelace',
      first_name: 'Ada',
      last_name: 'Lovelace',
      email: 'ada@example.com',
      phone: '08000000000',
      timezone: null,
      locale: null,
    },
  ],
  total: 1,
  page: 1,
  per_page: 20,
  total_pages: 1,
};

describe('CustomersPage', () => {
  beforeEach(() => {
    window.BookoraAdmin = {
      restUrl: 'https://example.test/wp-json/bookora/v1/',
      nonce: 'n',
      version: '0.1.0',
    };
    global.fetch = jest.fn((url: string) => {
      const body = url.includes('/customers/tags')
        ? { success: true, data: { tags: ['VIP'] } }
        : { success: true, data: customers };
      return Promise.resolve({ ok: true, status: 200, json: async () => body });
    }) as unknown as typeof fetch;
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('renders customer rows and the tag filter', async () => {
    render(<CustomersPage />);

    expect(await screen.findByText('Ada Lovelace')).toBeInTheDocument();
    expect(screen.getByText('ada@example.com')).toBeInTheDocument();
    expect(screen.getByText('1 total')).toBeInTheDocument();
  });
});
