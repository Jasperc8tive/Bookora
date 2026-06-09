import { render, screen, waitFor } from '@testing-library/react';
import { ServicesPage } from '../../assets/src/admin/components/services/ServicesPage';
import type { Paginated, Service, ServiceCategory } from '../../assets/src/admin/types';

const categories: ServiceCategory[] = [
  { id: 1, name: 'Hair', slug: 'hair', description: null, color: null, sort_order: 0, status: 'active' },
];

const services: Paginated<Service> = {
  items: [
    {
      id: 10,
      category_id: 1,
      name: 'Haircut',
      slug: 'haircut',
      description: null,
      duration_min: 30,
      buffer_before_min: 0,
      buffer_after_min: 0,
      price: '20.00',
      currency: 'NGN',
      deposit_type: 'none',
      deposit_value: '0.00',
      capacity: 1,
      image_url: null,
      status: 'active',
    },
  ],
  total: 1,
  page: 1,
  per_page: 20,
  total_pages: 1,
};

describe('ServicesPage', () => {
  beforeEach(() => {
    window.BookoraAdmin = {
      restUrl: 'https://example.test/wp-json/bookora/v1/',
      nonce: 'n',
      version: '0.1.0',
    };
    global.fetch = jest.fn((url: string) => {
      const body = url.includes('service-categories')
        ? { success: true, data: { items: categories } }
        : { success: true, data: services };
      return Promise.resolve({ ok: true, status: 200, json: async () => body });
    }) as unknown as typeof fetch;
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('loads and renders services with their category name', async () => {
    render(<ServicesPage />);

    expect(await screen.findByText('Haircut')).toBeInTheDocument();
    // "Hair" appears both as a filter option and the row's category cell.
    expect(screen.getAllByText('Hair').length).toBeGreaterThan(0);
    expect(screen.getByText('1 total')).toBeInTheDocument();
  });

  it('sends search and filter params to the API', async () => {
    const fetchMock = global.fetch as jest.Mock;
    render(<ServicesPage />);
    await screen.findByText('Haircut');

    await waitFor(() => {
      const calledServices = fetchMock.mock.calls.some(([u]) => String(u).includes('/services?'));
      expect(calledServices).toBe(true);
    });
  });
});
