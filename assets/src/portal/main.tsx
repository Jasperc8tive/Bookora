/**
 * Mounts the customer portal. Captures a magic-link token from the URL on first
 * load, stores it, and strips it from the address bar.
 */
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { PortalApp } from './PortalApp';
import { setToken } from './api';
import './index.css';

const root = document.getElementById('bookora-portal-root');

if (root) {
  const params = new URLSearchParams(window.location.search);
  const token = params.get('bookora_token');
  if (token) {
    setToken(token);
    params.delete('bookora_token');
    const qs = params.toString();
    window.history.replaceState({}, '', window.location.pathname + (qs ? `?${qs}` : ''));
  }

  createRoot(root).render(
    <StrictMode>
      <PortalApp />
    </StrictMode>,
  );
}
