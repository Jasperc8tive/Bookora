/**
 * REST client for the customer portal. Sends the portal bearer token from
 * localStorage on every request.
 */
import type { ApiEnvelope, BookoraPortalGlobal } from './types';

const TOKEN_KEY = 'bookora_portal_token';

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

export function getToken(): string {
  try {
    return window.localStorage.getItem(TOKEN_KEY) ?? '';
  } catch {
    return '';
  }
}

export function setToken(token: string): void {
  try {
    window.localStorage.setItem(TOKEN_KEY, token);
  } catch {
    /* ignore storage errors */
  }
}

export function clearToken(): void {
  try {
    window.localStorage.removeItem(TOKEN_KEY);
  } catch {
    /* ignore */
  }
}

function config(): BookoraPortalGlobal {
  const cfg = window.BookoraPortal;
  if (!cfg) {
    throw new ApiError('Portal configuration is missing.', 0);
  }
  return cfg;
}

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  const { restUrl, nonce } = config();
  const response = await fetch(restUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, ''), {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
      'X-Bookora-Portal-Token': getToken(),
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

export const apiGet = <T>(path: string): Promise<T> => request<T>('GET', path);
export const apiPost = <T>(path: string, body: unknown = {}): Promise<T> => request<T>('POST', path, body);
export const apiPatch = <T>(path: string, body: unknown): Promise<T> => request<T>('PATCH', path, body);
