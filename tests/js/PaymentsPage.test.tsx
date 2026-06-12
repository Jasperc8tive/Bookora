import { render, screen } from '@testing-library/react';
import { PaymentsPage } from '../../assets/src/admin/components/payments/PaymentsPage';

const settings = {
  success: true,
  data: {
    payments: {
      paystack: { enabled: true, public_key: 'pk_test', has_secret: true, has_webhook: false },
      flutterwave: { enabled: false, public_key: '', has_secret: false, has_webhook: false },
      stripe: { enabled: false, public_key: '', has_secret: false, has_webhook: false },
    },
  },
};

const payments = {
  success: true,
  data: {
    items: [
      { id: 1, appointment_id: 5, customer_id: 2, gateway: 'paystack', amount: '100.00', currency: 'NGN', status: 'paid', type: 'full', paid_at: '2030-06-12 09:00:00', created_at: '2030-06-12 09:00:00' },
    ],
  },
};

describe('PaymentsPage', () => {
  beforeEach(() => {
    window.BookoraAdmin = { restUrl: 'https://example.test/wp-json/bookora/v1/', nonce: 'n', version: '0.1.0' };
    global.fetch = jest.fn((url: string) => {
      const body = url.includes('/payments/settings') ? settings : payments;
      return Promise.resolve({ ok: true, status: 200, json: async () => body });
    }) as unknown as typeof fetch;
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraAdmin;
  });

  it('renders gateway settings and a refundable payment', async () => {
    render(<PaymentsPage />);

    expect(screen.getByText('Payment gateways')).toBeInTheDocument();
    expect(screen.getByLabelText('Paystack secret key')).toBeInTheDocument();
    expect(await screen.findByText('Refund')).toBeInTheDocument();
    expect(screen.getByText('NGN 100.00')).toBeInTheDocument();
  });
});
