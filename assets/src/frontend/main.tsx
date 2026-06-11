/**
 * Mounts the public booking wizard into the shortcode container.
 */
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BookingWizard } from './BookingWizard';
import './index.css';

const root = document.getElementById('bookora-booking-root');

if (root) {
  const parsed = Number.parseInt(root.dataset.service ?? '', 10);
  const preselect = Number.isNaN(parsed) ? undefined : parsed;
  createRoot(root).render(
    <StrictMode>
      <BookingWizard initialServiceId={preselect} />
    </StrictMode>,
  );
}
