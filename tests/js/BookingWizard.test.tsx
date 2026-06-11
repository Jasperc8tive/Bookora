import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BookingWizard } from '../../assets/src/frontend/BookingWizard';

const services = {
  success: true,
  data: { items: [{ id: 1, name: 'Massage', description: '', duration_min: 60, price: 100, currency: 'NGN', category_id: null }] },
};
const staff = { success: true, data: { items: [{ id: 7, name: 'Ada' }] } };

describe('BookingWizard', () => {
  beforeEach(() => {
    window.BookoraBooking = { restUrl: 'https://example.test/wp-json/bookora/v1/', nonce: 'n' };
    global.fetch = jest.fn((url: string) => {
      const body = url.includes('/staff') ? staff : services;
      return Promise.resolve({ ok: true, status: 200, json: async () => body });
    }) as unknown as typeof fetch;
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.BookoraBooking;
  });

  it('renders the service step and advances to staff selection', async () => {
    render(<BookingWizard />);

    const serviceButton = await screen.findByText('Massage');
    expect(screen.getByText('Choose a service')).toBeInTheDocument();
    expect(screen.getByText('NGN 100')).toBeInTheDocument();

    fireEvent.click(serviceButton);

    await waitFor(() => expect(screen.getByText('Choose a team member')).toBeInTheDocument());
    expect(screen.getByText('Any available')).toBeInTheDocument();
    expect(screen.getByText('Ada')).toBeInTheDocument();
  });
});
