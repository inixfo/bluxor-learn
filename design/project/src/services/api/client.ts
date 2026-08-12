export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1';
let csrfReady = false;

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly errors?: Record<string, string[]>
  ) {
    super(message);
  }
}

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const method = (options.method || 'GET').toUpperCase();
  if (!csrfReady && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    await fetch(`${API_BASE_URL}/auth/csrf-cookie`, { credentials: 'include' });
    csrfReady = true;
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...csrfHeader(),
      ...options.headers,
    },
    ...options,
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    if (response.status === 401) {
      window.dispatchEvent(new CustomEvent('learn-bluxor:unauthorized'));
    }
    throw new ApiError(payload.message || 'Request failed', response.status, payload.errors);
  }

  return payload as T;
}

export async function apiFormRequest<T>(path: string, formData: FormData, options: RequestInit = {}): Promise<T> {
  if (!csrfReady) {
    await fetch(`${API_BASE_URL}/auth/csrf-cookie`, { credentials: 'include' });
    csrfReady = true;
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...csrfHeader(),
      ...options.headers,
    },
    method: options.method || 'POST',
    body: formData,
    ...options,
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    if (response.status === 401) {
      window.dispatchEvent(new CustomEvent('learn-bluxor:unauthorized'));
    }
    throw new ApiError(payload.message || 'Request failed', response.status, payload.errors);
  }

  return payload as T;
}

export function minorToDisplay(value?: number | null): number | undefined {
  if (value === null || value === undefined) return undefined;
  return Math.round(value / 100);
}

function csrfHeader(): Record<string, string> {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {};
}
