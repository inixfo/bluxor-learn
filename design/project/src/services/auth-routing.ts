import type { AuthUser } from '@/services/api/auth';

const GUEST_ONLY_PATHS = new Set(['/login', '/register', '/forgot-password']);

export function isAdminUser(user: AuthUser | null): boolean {
  return !!user?.roles?.some((role) => role.name === 'admin');
}

export function authenticatedHomePath(user: AuthUser | null): string {
  return isAdminUser(user) ? '/admin' : '/account';
}

export function safeInternalReturnTo(value: string | null): string | null {
  if (!value) return null;

  const url = new URL(value, window.location.origin);

  if (url.origin !== window.location.origin || !url.pathname.startsWith('/') || GUEST_ONLY_PATHS.has(url.pathname)) {
    return null;
  }

  return `${url.pathname}${url.search}${url.hash}`;
}
