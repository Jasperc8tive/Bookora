import { render, screen, fireEvent } from '@testing-library/react';
import { AdvancedPage } from '../../assets/src/admin/components/advanced/AdvancedPage';

const empty = { success: true, data: { items: [] } };
const coupons = { success: true, data: { items: [{ id: 1, code: 'SAVE10', type: 'percent', value: '10.00', used: 0, usage_limit: 0, status: 'active' }] } };

describe('AdvancedPage', () => {
  beforeEach(() => {
    window.BookoraAdmin = { restUrl: 'https://example.test/wp-json/bookora/v1/', nonce: 'n', version: '0.1.0' };
    global.fetch = jest.fn((url: string) => {
      const body = url.includes('/coupons') ? coupons : empty;
      return Promise.resolve({ ok: true, status: 200, json: async () => body });
    }) as unknown as typeof fetch;
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('shows tabs and the coupons list, and switches tab', async () => {
    render(<AdvancedPage />);

    expect(screen.getByText('Coupons')).toBeInTheDocument();
    expect(screen.getByText('Gift cards')).toBeInTheDocument();
    expect(await screen.findByText('SAVE10')).toBeInTheDocument();

    fireEvent.click(screen.getByText('Resources'));
    expect(screen.getByLabelText('Resource name')).toBeInTheDocument();
  });
});
