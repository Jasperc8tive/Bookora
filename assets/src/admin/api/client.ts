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
    public readonly fields: Record<string, string> = {},
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

function buildUrl(path: string, query?: Record<string, unknown>): string {
  const { restUrl } = config();
  let url = restUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
  if (query) {
    const params = new URLSearchParams();
    Object.entries(query).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, String(value));
      }
    });
    const qs = params.toString();
    if (qs) {
      url += '?' + qs;
    }
  }
  return url;
}

async function request<T>(method: string, path: string, body?: unknown, query?: Record<string, unknown>): Promise<T> {
  const { nonce } = config();
  const response = await fetch(buildUrl(path, query), {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  let payload: unknown = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  if (!response.ok) {
    const err = payload as { message?: string; data?: { fields?: Record<string, string> } } | null;
    throw new ApiError(err?.message ?? `Request failed: ${path}`, response.status, err?.data?.fields ?? {});
  }

  return (payload as ApiEnvelope<T>).data;
}

export const apiGet = <T>(path: string, query?: Record<string, unknown>): Promise<T> =>
  request<T>('GET', path, undefined, query);
export const apiPost = <T>(path: string, body: unknown): Promise<T> => request<T>('POST', path, body);
export const apiPatch = <T>(path: string, body: unknown): Promise<T> => request<T>('PATCH', path, body);
export const apiDelete = <T>(path: string): Promise<T> => request<T>('DELETE', path);
