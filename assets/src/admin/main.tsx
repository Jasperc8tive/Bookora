/**
 * Admin SPA entry point. Mounts into #bookora-admin-root rendered by PHP.
 */
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import './index.css';

const container = document.getElementById('bookora-admin-root');
if (container) {
  container.removeAttribute('data-loading');
  createRoot(container).render(
    <StrictMode>
      <App />
    </StrictMode>,
  );
}
