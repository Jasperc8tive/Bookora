/**
 * Minimal REST client for the Bookora admin SPA.
 *
 * Reads the REST root + nonce injected by PHP via wp_localize_script and adds
 * the X-WP-Nonce header WordPress expects for cookie-authenticated requests.
 */
import type { ApiEnvelope, BookoraAdminGlobal } from '../types';

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

function config(): BookoraAdminGlobal {
  const cfg = window.BookoraAdmin;
  if (!cfg) {
    throw new ApiError('Bookora admin configuration is missing.', 0);
  }
  return cfg;
}

export async function apiGet<T>(path: string): Promise<T> {
  const { restUrl, nonce } = config();
  const url = restUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, '');

  const response = await fetch(url, {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
  });

  if (!response.ok) {
    throw new ApiError(`Request failed: ${path}`, response.status);
  }

  const body = (await response.json()) as ApiEnvelope<T>;
  return body.data;
}
